<?php
/**
 * Single source of truth for the shape of stored data. Every write path
 * (meta box, import, meta sanitizers, settings) runs through here. Input is
 * untrusted; output is a canonical array plus a list of field errors.
 *
 * Errors are returned as ['path' => 'periods.2.end', 'code' => 'invalid_date',
 * 'args' => []] and translated into messages by the WordPress layer.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Validator {

	public const MAX_SLOTS_PER_DAY = 6;
	public const MAX_PERIODS       = 100;
	public const MAX_NAME          = 100;
	public const MAX_TEXT          = 200;
	public const MAX_NOTE          = 500;
	public const MAX_TEMPLATE      = 1000;
	public const MAX_URL           = 500;
	public const MAX_LEAD_DAYS     = 365;

	public const LAYOUTS     = [ 'table', 'list', 'compact', 'paragraphs' ];
	public const TIME_STYLES = [ '24h', '24h-suffix', '24h-short', '12h' ];
	public const DAY_NAMES   = [ 'auto', 'short', 'long' ];

	public const SCHEMA_TYPES = [
		'LocalBusiness',
		'Store',
		'Florist',
		'BikeStore',
		'Bakery',
		'Restaurant',
		'FastFoodRestaurant',
		'CafeOrCoffeeShop',
		'BarOrPub',
		'MedicalBusiness',
		'Physician',
		'Dentist',
		'Physiotherapy',
		'Pharmacy',
		'HealthAndBeautyBusiness',
		'HairSalon',
		'BeautySalon',
		'DaySpa',
		'HomeAndConstructionBusiness',
		'Electrician',
		'Plumber',
		'HVACBusiness',
		'RoofingContractor',
		'GeneralContractor',
		'AutoRepair',
		'ProfessionalService',
		'Attorney',
		'AccountingService',
		'FinancialService',
		'RealEstateAgent',
		'LodgingBusiness',
		'Hotel',
		'BedAndBreakfast',
		'SportsActivityLocation',
		'ChildCare',
		'Library',
		'Museum',
		'NGO',
		'Organization',
	];

	/** @var callable(string): string */
	private $sanitize_text;

	/** @var callable(string): string */
	private $sanitize_html;

	/** @var callable(): string */
	private $uuid;

	/** @var list<array{path: string, code: string, args: array}> */
	private array $errors = [];

	/**
	 * @param callable|null $sanitize_text Cleans a single-line string (default: strip tags, trim).
	 * @param callable|null $sanitize_html Cleans limited HTML (default: strip tags).
	 * @param callable|null $uuid          Returns a new UUID v4.
	 */
	public function __construct( ?callable $sanitize_text = null, ?callable $sanitize_html = null, ?callable $uuid = null ) {
		$this->sanitize_text = $sanitize_text ?? [ self::class, 'default_sanitize_text' ];
		$this->sanitize_html = $sanitize_html ?? [ self::class, 'default_sanitize_text' ];
		$this->uuid          = $uuid ?? [ self::class, 'default_uuid' ];
	}

	public static function default_sanitize_text( string $value ): string {
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- The domain layer must not depend on WordPress.
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}

	public static function default_uuid(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Defaults                                                            */
	/* ------------------------------------------------------------------ */

	public static function empty_week(): array {
		$week = [];
		foreach ( Dates::WEEKDAYS as $weekday ) {
			$week[ $weekday ] = DaySpec::closed()->to_array();
		}
		return $week;
	}

	/**
	 * Starting point for a new set: Monday to Friday 09:00–18:00.
	 */
	public static function default_week(): array {
		$week = self::empty_week();
		foreach ( [ 'mon', 'tue', 'wed', 'thu', 'fri' ] as $weekday ) {
			$week[ $weekday ] = ( new DaySpec( DaySpec::OPEN, [ new Slot( '09:00', '18:00' ) ] ) )->to_array();
		}
		return $week;
	}

	public static function display_defaults(): array {
		return [
			'layout'          => 'table',
			'group_days'      => true,
			'highlight_today' => true,
			'show_notes'      => true,
			'time_style'      => '24h',
			'day_names'       => 'auto',
			'week_mode'       => 'current',
			'show_holidays'   => true,
		];
	}

	public static function schema_defaults(): array {
		return [
			'enabled' => false,
			'type'    => 'LocalBusiness',
			'name'    => '',
			'url'     => '',
		];
	}

	public static function holidays_defaults( string $state = 'SN' ): array {
		return [
			'state'    => $state,
			'default'  => [
				'mode' => HolidaySettings::CLOSED,
				'day'  => null,
			],
			'rules'    => [],
			'regional' => [],
		];
	}

	public static function settings_defaults(): array {
		return [
			'version'             => '',
			'default_set'         => 0,
			'lead_days'           => 7,
			'notice_template'     => '<strong>{name}</strong> ({start} – {end}): {hours} {note}',
			'notice_dismissible'  => false,
			'notice_horizon_days' => 30,
			'status_horizon_days' => 14,
			'time_style'          => '24h',
			'jsonld_everywhere'   => false,
			'delete_on_uninstall' => false,
			'default_state'       => 'SN',
			'roles'               => [ 'editor' ],
		];
	}

	/* ------------------------------------------------------------------ */
	/* Entry points                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array{data: array, errors: list<array{path: string, code: string, args: array}>}
	 */
	public function normalize_set( array $raw, string $default_state = 'SN' ): array {
		$this->errors = [];

		$data = [
			'regular'  => $this->week( (array) ( $raw['regular'] ?? [] ), 'regular' ),
			'periods'  => $this->periods( (array) ( $raw['periods'] ?? [] ), 'periods' ),
			'holidays' => $this->holidays( (array) ( $raw['holidays'] ?? [] ), 'holidays', $default_state ),
			'display'  => $this->display( (array) ( $raw['display'] ?? [] ), 'display' ),
			'schema'   => $this->schema( (array) ( $raw['schema'] ?? [] ), 'schema' ),
		];

		return $this->result( $data );
	}

	public function normalize_week( array $raw ): array {
		$this->errors = [];
		return $this->result( $this->week( $raw, 'regular' ) );
	}

	public function normalize_periods( array $raw ): array {
		$this->errors = [];
		return $this->result( $this->periods( $raw, 'periods' ) );
	}

	public function normalize_holidays( array $raw, string $default_state = 'SN' ): array {
		$this->errors = [];
		return $this->result( $this->holidays( $raw, 'holidays', $default_state ) );
	}

	public function normalize_display( array $raw ): array {
		$this->errors = [];
		return $this->result( $this->display( $raw, 'display' ) );
	}

	public function normalize_schema( array $raw ): array {
		$this->errors = [];
		return $this->result( $this->schema( $raw, 'schema' ) );
	}

	public function normalize_settings( array $raw ): array {
		$this->errors = [];
		$defaults     = self::settings_defaults();

		$template = ( $this->sanitize_html )( (string) ( $raw['notice_template'] ?? '' ) );
		if ( '' === trim( $template ) ) {
			$template = $defaults['notice_template'];
		}
		$template = $this->truncate( $template, self::MAX_TEMPLATE );

		$data = [
			'version'             => $this->text( (string) ( $raw['version'] ?? $defaults['version'] ), 20 ),
			'default_set'         => max( 0, (int) ( $raw['default_set'] ?? 0 ) ),
			'lead_days'           => $this->int_in_range( $raw['lead_days'] ?? $defaults['lead_days'], 0, self::MAX_LEAD_DAYS, $defaults['lead_days'], 'settings.lead_days' ),
			'notice_template'     => $template,
			'notice_dismissible'  => $this->bool( $raw['notice_dismissible'] ?? $defaults['notice_dismissible'] ),
			'notice_horizon_days' => $this->int_in_range( $raw['notice_horizon_days'] ?? $defaults['notice_horizon_days'], 1, 90, $defaults['notice_horizon_days'], 'settings.notice_horizon_days' ),
			'status_horizon_days' => $this->int_in_range( $raw['status_horizon_days'] ?? $defaults['status_horizon_days'], 2, 60, $defaults['status_horizon_days'], 'settings.status_horizon_days' ),
			'time_style'          => $this->enum( (string) ( $raw['time_style'] ?? $defaults['time_style'] ), self::TIME_STYLES, $defaults['time_style'], 'settings.time_style', 'invalid_time_style' ),
			'jsonld_everywhere'   => $this->bool( $raw['jsonld_everywhere'] ?? $defaults['jsonld_everywhere'] ),
			'delete_on_uninstall' => $this->bool( $raw['delete_on_uninstall'] ?? $defaults['delete_on_uninstall'] ),
			'default_state'       => $this->enum( strtoupper( (string) ( $raw['default_state'] ?? $defaults['default_state'] ) ), Holidays::STATES, $defaults['default_state'], 'settings.default_state', 'invalid_state' ),
			'roles'               => $this->role_slugs( $raw['roles'] ?? $defaults['roles'] ),
		];

		return $this->result( $data );
	}

	/**
	 * Role slugs that additionally receive the plugin capability. The
	 * administrator role always has it and is therefore never stored.
	 *
	 * @param mixed $value
	 * @return string[]
	 */
	private function role_slugs( $value ): array {
		$roles = [];
		foreach ( (array) $value as $slug ) {
			$slug = strtolower( trim( (string) $slug ) );
			if ( '' === $slug || 'administrator' === $slug || ! preg_match( '/^[a-z0-9_-]{1,60}$/', $slug ) ) {
				continue;
			}
			if ( ! in_array( $slug, $roles, true ) ) {
				$roles[] = $slug;
			}
		}
		return array_slice( $roles, 0, 50 );
	}

	/* ------------------------------------------------------------------ */
	/* Sections                                                            */
	/* ------------------------------------------------------------------ */

	private function week( array $raw, string $path ): array {
		$week = [];
		foreach ( Dates::WEEKDAYS as $weekday ) {
			$week[ $weekday ] = $this->day( (array) ( $raw[ $weekday ] ?? [] ), $path . '.' . $weekday );
		}
		return $week;
	}

	private function day( array $raw, string $path ): array {
		$mode = $this->enum( (string) ( $raw['mode'] ?? DaySpec::CLOSED ), DaySpec::MODES, DaySpec::CLOSED, $path . '.mode', 'invalid_mode' );
		$text = $this->text( (string) ( $raw['text'] ?? '' ), self::MAX_TEXT );
		$note = $this->text( (string) ( $raw['note'] ?? '' ), self::MAX_NOTE );

		$slots = [];
		if ( DaySpec::OPEN === $mode ) {
			$slots = $this->slots( (array) ( $raw['slots'] ?? [] ), $path . '.slots' );
			if ( [] === $slots ) {
				$this->error( $path . '.slots', 'slots_required' );
				$mode = DaySpec::CLOSED;
			}
		} elseif ( DaySpec::TEXT === $mode && '' === $text ) {
			$this->error( $path . '.text', 'text_required' );
			$mode = DaySpec::CLOSED;
		}

		return [
			'mode'  => $mode,
			'slots' => $slots,
			'text'  => DaySpec::TEXT === $mode ? $text : '',
			'note'  => $note,
		];
	}

	/**
	 * @return list<array{start: string, end: string}>
	 */
	private function slots( array $raw, string $path ): array {
		$raw = array_values( array_filter( $raw, 'is_array' ) );
		if ( count( $raw ) > self::MAX_SLOTS_PER_DAY ) {
			$this->error( $path, 'too_many_slots', [ self::MAX_SLOTS_PER_DAY ] );
			$raw = array_slice( $raw, 0, self::MAX_SLOTS_PER_DAY );
		}

		$slots = [];
		foreach ( $raw as $index => $slot ) {
			$slot_path = $path . '.' . $index;
			$start     = $this->time( (string) ( $slot['start'] ?? '' ), $slot_path . '.start', false );
			$end       = $this->time( (string) ( $slot['end'] ?? '' ), $slot_path . '.end', true );
			if ( null === $start || null === $end ) {
				continue;
			}
			if ( $start === $end ) {
				$this->error( $slot_path . '.end', 'zero_length' );
				continue;
			}
			$slots[] = ( new Slot( $start, $end ) )->to_array();
		}

		usort( $slots, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );

		$previous_end = null;
		foreach ( $slots as $index => $slot ) {
			$object = Slot::from_array( $slot );
			if ( null !== $previous_end && $object->start_minutes() < $previous_end ) {
				$this->error( $path . '.' . $index . '.start', 'slots_overlap' );
			}
			$previous_end = max( $previous_end ?? 0, $object->end_minutes() );
		}

		return $slots;
	}

	private function periods( array $raw, string $path ): array {
		$raw = array_values( array_filter( $raw, 'is_array' ) );
		if ( count( $raw ) > self::MAX_PERIODS ) {
			$this->error( $path, 'too_many_periods', [ self::MAX_PERIODS ] );
			$raw = array_slice( $raw, 0, self::MAX_PERIODS );
		}

		$periods = [];
		$uids    = [];
		foreach ( $raw as $index => $period ) {
			$normalized = $this->period( $period, $path . '.' . $index );
			if ( null === $normalized ) {
				continue;
			}
			if ( isset( $uids[ $normalized['uid'] ] ) ) {
				$normalized['uid'] = ( $this->uuid )();
			}
			$uids[ $normalized['uid'] ] = true;
			$periods[]                  = $normalized;
		}

		usort( $periods, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );

		return $periods;
	}

	private function period( array $raw, string $path ): ?array {
		$uid = strtolower( (string) ( $raw['uid'] ?? '' ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uid ) ) {
			$uid = ( $this->uuid )();
		}

		$recurring = $this->bool( $raw['recurring'] ?? false );
		$start     = $this->date( (string) ( $raw['start'] ?? '' ), $path . '.start' );
		$end       = $this->date( (string) ( $raw['end'] ?? '' ), $path . '.end' );
		if ( null === $start || null === $end ) {
			return null;
		}
		if ( ! $recurring && $end < $start ) {
			$this->error( $path . '.end', 'end_before_start' );
			return null;
		}
		if ( $recurring && Dates::diff_days( $start, $end ) > 365 ) {
			$this->error( $path . '.end', 'recurring_too_long' );
			return null;
		}

		$mode = $this->enum( (string) ( $raw['mode'] ?? Period::CLOSED ), Period::MODES, Period::CLOSED, $path . '.mode', 'invalid_mode' );

		$name = $this->text( (string) ( $raw['name'] ?? '' ), self::MAX_NAME );
		if ( '' === $name ) {
			$this->error( $path . '.name', 'name_required' );
		}

		$lead_days = null;
		if ( isset( $raw['lead_days'] ) && '' !== $raw['lead_days'] && null !== $raw['lead_days'] ) {
			$lead_days = $this->int_in_range( $raw['lead_days'], 0, self::MAX_LEAD_DAYS, null, $path . '.lead_days' );
		}

		$day  = null;
		$week = null;
		if ( Period::DAILY === $mode ) {
			$day = $this->day( (array) ( $raw['day'] ?? [] ), $path . '.day' );
		} elseif ( Period::WEEKLY === $mode ) {
			$week = $this->week( (array) ( $raw['week'] ?? [] ), $path . '.week' );
		}

		return [
			'uid'             => $uid,
			'name'            => $name,
			'start'           => $start,
			'end'             => $end,
			'recurring'       => $recurring,
			'mode'            => $mode,
			'day'             => $day,
			'week'            => $week,
			'note'            => $this->text( (string) ( $raw['note'] ?? '' ), self::MAX_NOTE ),
			'lead_days'       => $lead_days,
			'ignore_holidays' => $this->bool( $raw['ignore_holidays'] ?? false ),
		];
	}

	private function holidays( array $raw, string $path, string $default_state ): array {
		$state = $this->enum( strtoupper( (string) ( $raw['state'] ?? $default_state ) ), Holidays::STATES, $default_state, $path . '.state', 'invalid_state' );

		$regional = [];
		$options  = Holidays::regional_options( $state );
		foreach ( (array) ( $raw['regional'] ?? [] ) as $id ) {
			$id = (string) $id;
			if ( in_array( $id, $options, true ) && ! in_array( $id, $regional, true ) ) {
				$regional[] = $id;
			}
		}

		$default_raw  = (array) ( $raw['default'] ?? [] );
		$default_mode = $this->enum( (string) ( $default_raw['mode'] ?? HolidaySettings::CLOSED ), HolidaySettings::MODES, HolidaySettings::CLOSED, $path . '.default.mode', 'invalid_mode' );
		$default      = [
			'mode' => $default_mode,
			'day'  => HolidaySettings::CUSTOM === $default_mode ? $this->day( (array) ( $default_raw['day'] ?? [] ), $path . '.default.day' ) : null,
		];

		$rules = [];
		foreach ( (array) ( $raw['rules'] ?? [] ) as $id => $rule ) {
			$id = (string) $id;
			if ( ! Holidays::is_valid_id( $id ) ) {
				$this->error( $path . '.rules.' . $id, 'invalid_holiday' );
				continue;
			}
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$mode = (string) ( $rule['mode'] ?? HolidaySettings::USE_DEFAULT );
			if ( '' === $mode || HolidaySettings::USE_DEFAULT === $mode ) {
				continue; // Follows the default rule; nothing to store.
			}
			$mode         = $this->enum( $mode, HolidaySettings::MODES, HolidaySettings::CLOSED, $path . '.rules.' . $id . '.mode', 'invalid_mode' );
			$rules[ $id ] = [
				'mode' => $mode,
				'day'  => HolidaySettings::CUSTOM === $mode ? $this->day( (array) ( $rule['day'] ?? [] ), $path . '.rules.' . $id . '.day' ) : null,
			];
		}

		return [
			'state'    => $state,
			'default'  => $default,
			'rules'    => $rules,
			'regional' => $regional,
		];
	}

	private function display( array $raw, string $path ): array {
		$defaults = self::display_defaults();
		return [
			'layout'          => $this->enum( (string) ( $raw['layout'] ?? $defaults['layout'] ), self::LAYOUTS, $defaults['layout'], $path . '.layout', 'invalid_layout' ),
			'group_days'      => $this->bool( $raw['group_days'] ?? $defaults['group_days'] ),
			'highlight_today' => $this->bool( $raw['highlight_today'] ?? $defaults['highlight_today'] ),
			'show_notes'      => $this->bool( $raw['show_notes'] ?? $defaults['show_notes'] ),
			'time_style'      => $this->enum( (string) ( $raw['time_style'] ?? $defaults['time_style'] ), self::TIME_STYLES, $defaults['time_style'], $path . '.time_style', 'invalid_time_style' ),
			'day_names'       => $this->enum( (string) ( $raw['day_names'] ?? $defaults['day_names'] ), self::DAY_NAMES, $defaults['day_names'], $path . '.day_names', 'invalid_day_names' ),
			'week_mode'       => $this->enum( (string) ( $raw['week_mode'] ?? $defaults['week_mode'] ), [ 'current', 'regular' ], $defaults['week_mode'], $path . '.week_mode', 'invalid_mode' ),
			'show_holidays'   => $this->bool( $raw['show_holidays'] ?? $defaults['show_holidays'] ),
		];
	}

	private function schema( array $raw, string $path ): array {
		$defaults = self::schema_defaults();

		$url = trim( (string) ( $raw['url'] ?? '' ) );
		if ( '' !== $url && ( strlen( $url ) > self::MAX_URL || ! preg_match( '#^https?://[^\s<>"\']+$#i', $url ) ) ) {
			$this->error( $path . '.url', 'invalid_url' );
			$url = '';
		}

		return [
			'enabled' => $this->bool( $raw['enabled'] ?? $defaults['enabled'] ),
			'type'    => $this->enum( (string) ( $raw['type'] ?? $defaults['type'] ), self::SCHEMA_TYPES, $defaults['type'], $path . '.type', 'invalid_schema_type' ),
			'name'    => $this->text( (string) ( $raw['name'] ?? '' ), self::MAX_NAME * 2 ),
			'url'     => $url,
		];
	}

	/* ------------------------------------------------------------------ */
	/* Primitives                                                          */
	/* ------------------------------------------------------------------ */

	private function time( string $value, string $path, bool $allow_midnight_end ): ?string {
		$value = trim( $value );
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $value, $m ) ) {
			$value = sprintf( '%02d:%s', (int) $m[1], $m[2] );
		}
		if ( ! Dates::is_valid_time( $value, $allow_midnight_end ) ) {
			$this->error( $path, 'invalid_time' );
			return null;
		}
		return $value;
	}

	private function date( string $value, string $path ): ?string {
		$value = trim( $value );
		if ( ! Dates::is_valid( $value ) ) {
			$this->error( $path, 'invalid_date' );
			return null;
		}
		return $value;
	}

	private function text( string $value, int $max_length ): string {
		return $this->truncate( ( $this->sanitize_text )( $value ), $max_length );
	}

	private function truncate( string $value, int $max_length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max_length );
		}
		return substr( $value, 0, $max_length );
	}

	/**
	 * @param mixed $value
	 */
	private function bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), [ '1', 'true', 'yes', 'on' ], true );
		}
		return (bool) $value;
	}

	/**
	 * @param mixed    $value
	 * @param int|null $fallback
	 */
	private function int_in_range( $value, int $min, int $max, ?int $fallback, string $path ): ?int {
		if ( ! is_numeric( $value ) ) {
			$this->error( $path, 'invalid_number' );
			return $fallback;
		}
		$int = (int) $value;
		if ( $int < $min || $int > $max ) {
			$this->error( $path, 'out_of_range', [ $min, $max ] );
			return $fallback;
		}
		return $int;
	}

	/**
	 * @param string[] $allowed
	 */
	private function enum( string $value, array $allowed, string $fallback, string $path, string $code ): string {
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}
		if ( '' !== $value ) {
			$this->error( $path, $code );
		}
		return $fallback;
	}

	private function error( string $path, string $code, array $args = [] ): void {
		$this->errors[] = [
			'path' => $path,
			'code' => $code,
			'args' => $args,
		];
	}

	private function result( array $data ): array {
		return [
			'data'   => $data,
			'errors' => $this->errors,
		];
	}
}
