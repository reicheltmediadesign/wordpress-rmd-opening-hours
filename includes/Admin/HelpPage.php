<?php
/**
 * Opening Hours → Help: explains sets, fields, holiday rules, blocks,
 * shortcodes and settings for site owners.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Capabilities;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Repository\SetRepository;

defined( 'ABSPATH' ) || exit;

final class HelpPage {

	public const SLUG = 'rmd-oh-help';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'styles' ] );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::NAME,
			__( 'Opening Hours Help', 'rmd-opening-hours' ),
			__( 'Help', 'rmd-opening-hours' ),
			Capabilities::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function styles( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_add_inline_style(
			'wp-admin',
			'.rmd-oh-help{max-width:60em}.rmd-oh-help h2{margin-top:2em;padding-top:1em;border-top:1px solid #dcdcde}.rmd-oh-help h2:first-of-type{border:0;margin-top:1em;padding-top:0}.rmd-oh-help table{margin:1em 0}.rmd-oh-help td,.rmd-oh-help th{vertical-align:top}.rmd-oh-help code{white-space:nowrap}.rmd-oh-help dt{font-weight:600;margin-top:.8em}.rmd-oh-help dd{margin:.2em 0 0 0}.rmd-oh-help .rmd-oh-help__toc{columns:2;column-gap:2em;margin:0 0 1em}.rmd-oh-help .rmd-oh-help__toc li{margin:0 0 .3em}'
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'rmd-opening-hours' ) );
		}

		$example = self::example_slug();

		echo '<div class="wrap rmd-oh-help"><h1>' . esc_html__( 'Opening Hours Help', 'rmd-opening-hours' ) . '</h1>';
		echo '<p>' . esc_html__( 'This plugin lets you maintain your opening hours in one place and show them anywhere on the website. Changes such as holidays or seasonal hours are planned ahead and announced automatically.', 'rmd-opening-hours' ) . '</p>';

		self::toc();
		self::section_quickstart();
		self::section_sets();
		self::section_regular();
		self::section_periods();
		self::section_holidays();
		self::section_display();
		self::section_schema();
		self::section_blocks();
		self::section_shortcodes( $example );
		self::section_notice_template();
		self::section_settings();
		self::section_import_export();
		self::section_faq();

		echo '</div>';
	}

	private static function example_slug(): string {
		$set = SetRepository::default_set();
		return $set ? $set->slug : 'shop';
	}

	private static function toc(): void {
		$items = [
			'quickstart' => __( 'Quick start', 'rmd-opening-hours' ),
			'sets'       => __( 'Sets', 'rmd-opening-hours' ),
			'regular'    => __( 'Regular hours', 'rmd-opening-hours' ),
			'periods'    => __( 'Periods', 'rmd-opening-hours' ),
			'holidays'   => __( 'Holidays', 'rmd-opening-hours' ),
			'display'    => __( 'Display', 'rmd-opening-hours' ),
			'schema'     => __( 'Structured data', 'rmd-opening-hours' ),
			'blocks'     => __( 'Blocks', 'rmd-opening-hours' ),
			'shortcodes' => __( 'Shortcodes', 'rmd-opening-hours' ),
			'template'   => __( 'Notice text and placeholders', 'rmd-opening-hours' ),
			'settings'   => __( 'Settings', 'rmd-opening-hours' ),
			'import'     => __( 'Import/Export', 'rmd-opening-hours' ),
			'faq'        => __( 'Questions and answers', 'rmd-opening-hours' ),
		];
		echo '<ul class="rmd-oh-help__toc">';
		foreach ( $items as $anchor => $label ) {
			printf( '<li><a href="#rmd-oh-help-%s">%s</a></li>', esc_attr( $anchor ), esc_html( $label ) );
		}
		echo '</ul>';
	}

	private static function heading( string $anchor, string $title ): void {
		printf( '<h2 id="rmd-oh-help-%s">%s</h2>', esc_attr( $anchor ), esc_html( $title ) );
	}

	private static function p( string $text ): void {
		echo '<p>' . wp_kses(
			$text,
			[
				'strong' => [],
				'em'     => [],
				'code'   => [],
				'br'     => [],
			]
		) . '</p>';
	}

	/**
	 * @param array<int, array{0: string, 1: string}> $rows term => description (may contain code/strong/em).
	 */
	private static function dl( array $rows ): void {
		echo '<dl>';
		foreach ( $rows as [ $term, $description ] ) {
			echo '<dt>' . esc_html( $term ) . '</dt><dd>' . wp_kses(
				$description,
				[
					'strong' => [],
					'em'     => [],
					'code'   => [],
					'br'     => [],
				]
			) . '</dd>';
		}
		echo '</dl>';
	}

	/**
	 * @param string[]                   $headers
	 * @param array<int, array<int, string>> $rows
	 */
	private static function table( array $headers, array $rows ): void {
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				echo '<td>' . wp_kses(
					$cell,
					[
						'code'   => [],
						'strong' => [],
						'em'     => [],
						'br'     => [],
					]
				) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function section_quickstart(): void {
		self::heading( 'quickstart', __( 'Quick start', 'rmd-opening-hours' ) );
		echo '<ol>';
		echo '<li>' . esc_html__( 'Go to Opening Hours → Add set, give it a name (e.g. “Shop”) and enter your weekly hours.', 'rmd-opening-hours' ) . '</li>';
		echo '<li>' . esc_html__( 'Check the Holidays tab: choose your federal state and decide for each holiday whether you are closed.', 'rmd-opening-hours' ) . '</li>';
		echo '<li>' . esc_html__( 'Save. Then edit the page where the hours should appear and add the block “Opening Hours” (or paste the shortcode shown next to the set).', 'rmd-opening-hours' ) . '</li>';
		echo '<li>' . esc_html__( 'Add the block “Opening Hours Notice” to the start page or the header area once; it stays invisible until a planned change is due.', 'rmd-opening-hours' ) . '</li>';
		echo '<li>' . esc_html__( 'Before a holiday or vacation, add a period in the Periods tab. The notice appears automatically ahead of time and disappears afterwards.', 'rmd-opening-hours' ) . '</li>';
		echo '</ol>';
	}

	private static function section_sets(): void {
		self::heading( 'sets', __( 'Sets', 'rmd-opening-hours' ) );
		self::p( __( 'A set is one complete collection of opening hours. Most sites need just one. Create several sets if you have different hours for different things, for example “Practice” and “Phone hours”, or two locations. Blocks and shortcodes always refer to one set; the <strong>default set</strong> (see Settings) is used when nothing is specified.', 'rmd-opening-hours' ) );
		self::p( __( 'The set name is what visitors may see as a heading. The <strong>slug</strong> shown in the list and in the sidebar box is the technical name used in shortcodes; it is derived from the name when the set is first saved and does not change afterwards.', 'rmd-opening-hours' ) );
	}

	private static function section_regular(): void {
		self::heading( 'regular', __( 'Regular hours', 'rmd-opening-hours' ) );
		self::p( __( 'The weekly hours that apply whenever no period and no holiday says otherwise. Each weekday has one of three modes:', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'Closed', 'rmd-opening-hours' ), __( 'The day is shown as closed.', 'rmd-opening-hours' ) ],
				[ __( 'Open', 'rmd-opening-hours' ), __( 'Enter one or more <strong>time slots</strong>, e.g. 08:00–12:00 and 14:00–18:00 for a lunch break. Times use the 24-hour format HH:MM. An end time earlier than the start time (e.g. 18:00–02:00) means the slot runs into the next day; use 24:00 for “until midnight”.', 'rmd-opening-hours' ) ],
				[ __( 'Text', 'rmd-opening-hours' ), __( 'Shows a text instead of times, e.g. “by appointment” or “on request”.', 'rmd-opening-hours' ) ],
				[ __( 'Note', 'rmd-opening-hours' ), __( 'Optional remark shown next to the hours of that day, e.g. “Emergency service only”. Notes can be hidden in the display options.', 'rmd-opening-hours' ) ],
				[ __( 'Copy to Tue–Fri', 'rmd-opening-hours' ), __( 'Copies Monday’s entry to Tuesday through Friday to save typing.', 'rmd-opening-hours' ) ],
				[ __( 'Import from text…', 'rmd-opening-hours' ), __( 'Paste the opening hours from your old website (a copied table, a list or a sentence such as “Dienstag, Donnerstag: 7 – 16 Uhr”). The editor recognises day names, ranges, several time slots, “geschlossen”, “nach Vereinbarung” and a holiday line, shows a preview and fills in the week. Check the result before saving.', 'rmd-opening-hours' ) ],
			]
		);
	}

	private static function section_periods(): void {
		self::heading( 'periods', __( 'Periods', 'rmd-opening-hours' ) );
		self::p( __( 'Periods are date ranges with different hours: vacation, company holidays, reduced summer hours, extended hours before Christmas, a training day. They are planned in advance and take effect on their own. Past periods are hidden in the editor but kept.', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'Name', 'rmd-opening-hours' ), __( 'Shown in the notice and used as the row label in the editor, e.g. “Summer break”.', 'rmd-opening-hours' ) ],
				[ __( 'From / Until (inclusive)', 'rmd-opening-hours' ), __( 'First and last day of the period. A single day has the same start and end date.', 'rmd-opening-hours' ) ],
				[ __( 'During this period', 'rmd-opening-hours' ), __( '<strong>Closed</strong> – closed on every day of the range. <strong>Same hours every day</strong> – one set of time slots that applies to every day, e.g. 10:00–14:00 between the years. <strong>Different weekly hours</strong> – a complete alternative week, e.g. summer hours.', 'rmd-opening-hours' ) ],
				[ __( 'Lead time for the notice (days)', 'rmd-opening-hours' ), __( 'How many days before the start the notice block starts announcing this period. Empty uses the value from the settings.', 'rmd-opening-hours' ) ],
				[ __( 'Note', 'rmd-opening-hours' ), __( 'Optional text shown with the hours and available as {note} in the notice, e.g. an emergency phone number.', 'rmd-opening-hours' ) ],
				[ __( 'Repeats every year (same dates)', 'rmd-opening-hours' ), __( 'For fixed yearly closures such as 24 December – 2 January. The year of the dates does not matter; ranges may span the turn of the year.', 'rmd-opening-hours' ) ],
				[ __( 'Also open on public holidays in this period', 'rmd-opening-hours' ), __( 'Normally a public holiday inside a period with alternative hours still follows the holiday rule (usually closed). Tick this if the period’s hours should apply on holidays too, e.g. a Christmas market stall.', 'rmd-opening-hours' ) ],
			]
		);
		self::p( __( '<strong>Which hours win?</strong> On any given day the plugin checks in this order: 1. a period marked “closed”, 2. a public holiday (unless its rule is “regular hours” or the period ignores holidays), 3. a period with alternative hours (the shorter, more specific one first), 4. the regular weekly hours.', 'rmd-opening-hours' ) );
	}

	private static function section_holidays(): void {
		self::heading( 'holidays', __( 'Holidays', 'rmd-opening-hours' ) );
		self::p( __( 'German public holidays are calculated automatically for the chosen <strong>federal state</strong>, including moveable feasts such as Easter and Whitsun, and the Day of Repentance in Saxony. Some holidays apply only in parts of a state (e.g. Corpus Christi in Catholic communities of Saxony and Thuringia, Assumption Day and the Augsburg Peace Festival in Bavaria); tick them under <strong>regional holidays</strong> if they apply to you.', 'rmd-opening-hours' ) );
		self::p( __( 'First choose what applies <strong>on all holidays</strong> (closed by default). This rule is shown as a “Public holidays” row after Sunday in the opening hours; it can be switched off under Display, per block or with holidays="no" in the shortcode. Then adjust single holidays where needed; “As all holidays” means no override.', 'rmd-opening-hours' ) );
		self::p( __( 'For each holiday choose a rule:', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'Closed', 'rmd-opening-hours' ), __( 'The default. The day is shown as closed with the holiday’s name as a note.', 'rmd-opening-hours' ) ],
				[ __( 'Regular hours', 'rmd-opening-hours' ), __( 'The holiday is ignored; the normal weekday hours apply (e.g. restaurants, petrol stations).', 'rmd-opening-hours' ) ],
				[ __( 'Special hours', 'rmd-opening-hours' ), __( 'Enter the time slots that apply on this holiday, e.g. a bakery open 07:00–10:00 on Easter Sunday.', 'rmd-opening-hours' ) ],
			]
		);
		self::p( __( 'The table shows the dates for the current and the next year so you can check them. Holidays are included in the “current week” view, in the notice-free status and in the structured data; they do not create notices.', 'rmd-opening-hours' ) );
	}

	private static function section_display(): void {
		self::heading( 'display', __( 'Display', 'rmd-opening-hours' ) );
		self::p( __( 'Defaults for how this set is shown. Every block and shortcode can override each option individually.', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'Layout', 'rmd-opening-hours' ), __( '<strong>Table</strong> – one row per day (group), best for sidebars and contact pages. <strong>List</strong> – definition list with the same content, useful when tables look heavy in your theme. <strong>Compact</strong> – everything in a single line, e.g. “Mon–Fri 9–18 h · Sat 9–12 h”, for footers. <strong>Paragraphs</strong> – written out with full day names in bold, one line per time slot and the note below, e.g. for contact pages.', 'rmd-opening-hours' ) ],
				[ __( 'Time format', 'rmd-opening-hours' ), __( '24-hour (09:00–18:00), 24-hour with unit (08:00 – 12:00 h), short 24-hour (9–18 h, minutes only when needed) or 12-hour (9:00 am–6:00 pm).', 'rmd-opening-hours' ) ],
				[ __( 'Day names', 'rmd-opening-hours' ), __( '<strong>Abbreviated</strong> (Mon–Fri) or <strong>full</strong> (Monday – Friday). <strong>By layout</strong> abbreviates everywhere except in the paragraphs layout.', 'rmd-opening-hours' ) ],
				[ __( 'Week shown', 'rmd-opening-hours' ), __( '<strong>Current week</strong> shows Monday to Sunday of this week with holidays and periods already applied, so a visitor sees what really applies. <strong>Regular hours only</strong> always shows the plain weekly schedule; combine it with the notice block to announce deviations.', 'rmd-opening-hours' ) ],
				[ __( 'Group days with equal hours', 'rmd-opening-hours' ), __( 'Merges consecutive days with identical hours into one row, e.g. “Mon–Fri”. Days that differ (a holiday, a note) start a new row.', 'rmd-opening-hours' ) ],
				[ __( 'Highlight today', 'rmd-opening-hours' ), __( 'Marks today’s row so it can be styled (bold background by default).', 'rmd-opening-hours' ) ],
				[ __( 'Show notes', 'rmd-opening-hours' ), __( 'Shows the notes of days, periods and holiday names below the hours.', 'rmd-opening-hours' ) ],
			]
		);
	}

	private static function section_schema(): void {
		self::heading( 'schema', __( 'Structured data', 'rmd-opening-hours' ) );
		self::p( __( 'Optionally the plugin adds machine-readable opening hours (schema.org “openingHoursSpecification”, including upcoming exceptions) to the page so search engines can show them. Enter the business type, name and website URL. Leave this <strong>off</strong> if an SEO plugin (e.g. Yoast Local SEO, Rank Math) already describes your business, otherwise search engines may see two different businesses.', 'rmd-opening-hours' ) );
	}

	private static function section_blocks(): void {
		self::heading( 'blocks', __( 'Blocks', 'rmd-opening-hours' ) );
		self::p( __( 'In the block editor search for “Opening Hours”. All three blocks pick the set in the sidebar and support colors, typography and spacing from your theme.', 'rmd-opening-hours' ) );
		self::table(
			[ __( 'Block', 'rmd-opening-hours' ), __( 'Shows', 'rmd-opening-hours' ), __( 'Options', 'rmd-opening-hours' ) ],
			[
				[ __( 'Opening Hours', 'rmd-opening-hours' ), __( 'The hours of a set.', 'rmd-opening-hours' ), __( 'Set, set name as heading, layout, week shown, time format, day names, grouping, highlight today, notes. “Set default” uses the set’s display settings.', 'rmd-opening-hours' ) ],
				[ __( 'Opening Hours Notice', 'rmd-opening-hours' ), __( 'Announcements for upcoming and running periods. Empty when nothing is due.', 'rmd-opening-hours' ), __( 'Set (or all sets), lead time, dismissible, own text template.', 'rmd-opening-hours' ) ],
				[ __( 'Open Now Status', 'rmd-opening-hours' ), __( '“Open now · closes at 18:00” or “Closed now · opens tomorrow at 09:00”, updated live in the visitor’s browser.', 'rmd-opening-hours' ), __( 'Set, show next opening/closing time.', 'rmd-opening-hours' ) ],
			]
		);
		self::p( __( 'The notice block shows a grey hint in the editor when no notice is due; visitors never see that hint. Place the block once on the start page or in the header/footer template so every announcement appears there automatically.', 'rmd-opening-hours' ) );
	}

	private static function section_shortcodes( string $example ): void {
		self::heading( 'shortcodes', __( 'Shortcodes', 'rmd-opening-hours' ) );
		self::p( __( 'Shortcodes do the same as the blocks and can be used in classic editors, widgets, page builders and theme templates. Attributes are optional; omitted ones use the set’s display settings.', 'rmd-opening-hours' ) );
		echo '<pre><code>[rmd_opening_hours set="' . esc_html( $example ) . '"]' . "\n"
			. '[rmd_opening_hours set="' . esc_html( $example ) . '" layout="compact" week="regular" time_style="24h-short"]' . "\n"
			. '[rmd_opening_hours_notice set="' . esc_html( $example ) . '" lead_days="14"]' . "\n"
			. '[rmd_opening_hours_status set="' . esc_html( $example ) . '" next="no"]</code></pre>';
		self::table(
			[ __( 'Attribute', 'rmd-opening-hours' ), __( 'Values', 'rmd-opening-hours' ), __( 'Shortcode', 'rmd-opening-hours' ) ],
			[
				[ '<code>set</code>', __( 'Slug or ID of the set. Empty = default set. <code>all</code> = every set (notice only).', 'rmd-opening-hours' ), __( 'all', 'rmd-opening-hours' ) ],
				[ '<code>layout</code>', '<code>table</code>, <code>list</code>, <code>compact</code>', 'rmd_opening_hours' ],
				[ '<code>week</code>', '<code>current</code>, <code>regular</code>', 'rmd_opening_hours' ],
				[ '<code>group</code>', '<code>yes</code>, <code>no</code> – ' . __( 'merge consecutive days with equal hours', 'rmd-opening-hours' ), 'rmd_opening_hours' ],
				[ '<code>today</code>', '<code>yes</code>, <code>no</code> – ' . __( 'highlight today', 'rmd-opening-hours' ), 'rmd_opening_hours' ],
				[ '<code>notes</code>', '<code>yes</code>, <code>no</code> – ' . __( 'show notes', 'rmd-opening-hours' ), 'rmd_opening_hours' ],
				[ '<code>time_style</code>', '<code>24h</code>, <code>24h-short</code>, <code>12h</code>', 'rmd_opening_hours' ],
				[ '<code>days</code>', '<code>short</code>, <code>long</code>, <code>auto</code> – ' . __( 'abbreviated or full day names', 'rmd-opening-hours' ), 'rmd_opening_hours' ],
				[ '<code>title</code>', '<code>yes</code>, <code>no</code> – ' . __( 'show the set name as heading', 'rmd-opening-hours' ), 'rmd_opening_hours' ],
				[ '<code>lead_days</code>', __( 'Days before a period starts; <code>0</code> = plugin setting', 'rmd-opening-hours' ), 'rmd_opening_hours_notice' ],
				[ '<code>template</code>', __( 'Own text with placeholders, see below', 'rmd-opening-hours' ), 'rmd_opening_hours_notice' ],
				[ '<code>dismissible</code>', '<code>yes</code>, <code>no</code> – ' . __( 'visitors may close the notice', 'rmd-opening-hours' ), 'rmd_opening_hours_notice' ],
				[ '<code>next</code>', '<code>yes</code>, <code>no</code> – ' . __( 'show next opening or closing time', 'rmd-opening-hours' ), 'rmd_opening_hours_status' ],
				[ '<code>class</code>', __( 'Additional CSS classes for styling', 'rmd-opening-hours' ), __( 'all', 'rmd-opening-hours' ) ],
			]
		);
	}

	private static function section_notice_template(): void {
		self::heading( 'template', __( 'Notice text and placeholders', 'rmd-opening-hours' ) );
		self::p( __( 'The notice text is defined once in the settings and can be overridden per block or shortcode. It may contain the HTML tags strong, em, br, a and span. Placeholders are replaced for each period:', 'rmd-opening-hours' ) );
		self::table(
			[ __( 'Placeholder', 'rmd-opening-hours' ), __( 'Replaced by', 'rmd-opening-hours' ) ],
			[
				[ '<code>{name}</code>', __( 'Name of the period', 'rmd-opening-hours' ) ],
				[ '<code>{start}</code>', __( 'First day, in the site’s date format', 'rmd-opening-hours' ) ],
				[ '<code>{end}</code>', __( 'Last day', 'rmd-opening-hours' ) ],
				[ '<code>{hours}</code>', __( '“closed”, the daily hours, or a summary of the alternative week such as “Mon–Fri 10:00–14:00”', 'rmd-opening-hours' ) ],
				[ '<code>{note}</code>', __( 'The period’s note (empty if none)', 'rmd-opening-hours' ) ],
				[ '<code>{set}</code>', __( 'Name of the set', 'rmd-opening-hours' ) ],
			]
		);
		self::p( __( 'Example: <code>&lt;strong&gt;{name}&lt;/strong&gt; from {start} to {end}: {hours}. {note}</code>', 'rmd-opening-hours' ) );
		self::p( __( 'A notice becomes visible the configured number of days before the period starts, stays visible while it runs and disappears at the end of its last day. If visitors may dismiss notices, a closed notice stays hidden in that browser until the period is over.', 'rmd-opening-hours' ) );
	}

	private static function section_settings(): void {
		self::heading( 'settings', __( 'Settings', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'Default set', 'rmd-opening-hours' ), __( 'Used by blocks and shortcodes that do not name a set.', 'rmd-opening-hours' ) ],
				[ __( 'Federal state for new sets', 'rmd-opening-hours' ), __( 'Preselected in the Holidays tab of new sets.', 'rmd-opening-hours' ) ],
				[ __( 'Time format for the status', 'rmd-opening-hours' ), __( 'How times appear in “closes at 18:00”.', 'rmd-opening-hours' ) ],
				[ __( 'Lead time (days)', 'rmd-opening-hours' ), __( 'Default number of days a notice appears before a period; each period can override it.', 'rmd-opening-hours' ) ],
				[ __( 'Notice text', 'rmd-opening-hours' ), __( 'Template with placeholders, see above.', 'rmd-opening-hours' ) ],
				[ __( 'Visitors can dismiss notices', 'rmd-opening-hours' ), __( 'Adds a close button to notices.', 'rmd-opening-hours' ) ],
				[ __( 'Pre-render horizon / Status horizon', 'rmd-opening-hours' ), __( 'Technical settings for caching plugins: how far ahead notices and opening intervals are embedded in the page so that cached pages still switch at the right time. The defaults (30 and 14 days) suit almost every site.', 'rmd-opening-hours' ) ],
				[ __( 'Output JSON-LD on every page', 'rmd-opening-hours' ), __( 'Adds the structured data of enabled sets to every page instead of only pages that show the set.', 'rmd-opening-hours' ) ],
				[ __( 'Roles that may manage opening hours', 'rmd-opening-hours' ), __( 'Administrators always have access. Tick further roles (e.g. Editor or Shop Manager) that should see this menu. Only administrators can change this.', 'rmd-opening-hours' ) ],
				[ __( 'Delete all sets and settings when the plugin is deleted', 'rmd-opening-hours' ), __( 'Off by default so that deleting the plugin by accident does not lose your data.', 'rmd-opening-hours' ) ],
			]
		);
	}

	private static function section_import_export(): void {
		self::heading( 'import', __( 'Import/Export', 'rmd-opening-hours' ) );
		self::p( __( 'Export downloads all sets and the settings as a JSON file – handy as a backup or to copy the configuration from a staging site to the live site. Import shows a preview first; sets are matched by their slug, existing sets are overwritten and new ones created. Nothing is deleted. Site-specific settings (default set, roles, uninstall behaviour) are never changed by an import.', 'rmd-opening-hours' ) );
	}

	private static function section_faq(): void {
		self::heading( 'faq', __( 'Questions and answers', 'rmd-opening-hours' ) );
		self::dl(
			[
				[ __( 'The website still shows old hours after I saved.', 'rmd-opening-hours' ), __( 'Your site probably uses a caching plugin. The plugin asks the common caches to refresh when you save, but some need a manual “purge cache”. The “open now” status and notices switch correctly even on cached pages.', 'rmd-opening-hours' ) ],
				[ __( 'I want to announce a change without changing the hours yet.', 'rmd-opening-hours' ), __( 'Create a period with a start date in the future. The notice block announces it from the lead time onwards while the regular hours keep applying until the start date.', 'rmd-opening-hours' ) ],
				[ __( 'We are open on a holiday this year only.', 'rmd-opening-hours' ), __( 'Add a period for that single day with “same hours every day” and tick “also open on public holidays in this period”.', 'rmd-opening-hours' ) ],
				[ __( 'We close early on one specific day.', 'rmd-opening-hours' ), __( 'Add a single-day period with “same hours every day” and the reduced time slot. Add a short lead time so visitors are warned.', 'rmd-opening-hours' ) ],
				[ __( 'How do I style the output?', 'rmd-opening-hours' ), __( 'Use the block’s color and typography settings, or add CSS in your theme: the table uses the class <code>rmd-oh</code>, notices <code>rmd-oh-notice</code>, the status <code>rmd-oh-status</code>. Colors and spacing are exposed as CSS custom properties, see the README on GitHub.', 'rmd-opening-hours' ) ],
				[ __( 'Where do I get help?', 'rmd-opening-hours' ), __( 'Contact reichelt media.design (https://reicheltmedia.design). Please mention which set and which page is affected.', 'rmd-opening-hours' ) ],
			]
		);
	}
}
