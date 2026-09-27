<?php
/**
 * What applies on one day: closed, open during one or more slots, or a free
 * text such as "by appointment".
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class DaySpec {

	public const CLOSED = 'closed';
	public const OPEN   = 'open';
	public const TEXT   = 'text';

	public const MODES = [ self::CLOSED, self::OPEN, self::TEXT ];

	/**
	 * @param Slot[] $slots
	 */
	public function __construct(
		public readonly string $mode,
		public readonly array $slots = [],
		public readonly string $text = '',
		public readonly string $note = ''
	) {}

	public static function closed( string $note = '' ): self {
		return new self( self::CLOSED, [], '', $note );
	}

	/**
	 * Builds a spec from already validated storage data.
	 */
	public static function from_array( array $data ): self {
		$mode  = (string) ( $data['mode'] ?? self::CLOSED );
		$slots = [];
		foreach ( (array) ( $data['slots'] ?? [] ) as $slot ) {
			if ( is_array( $slot ) ) {
				$slots[] = Slot::from_array( $slot );
			}
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = self::CLOSED;
		}
		return new self( $mode, $slots, (string) ( $data['text'] ?? '' ), (string) ( $data['note'] ?? '' ) );
	}

	public function to_array(): array {
		return [
			'mode'  => $this->mode,
			'slots' => array_map( static fn( Slot $slot ): array => $slot->to_array(), $this->slots ),
			'text'  => $this->text,
			'note'  => $this->note,
		];
	}

	public function is_open(): bool {
		return self::OPEN === $this->mode && [] !== $this->slots;
	}

	public function is_closed(): bool {
		return self::CLOSED === $this->mode || ( self::OPEN === $this->mode && [] === $this->slots );
	}

	public function with_note( string $note ): self {
		return new self( $this->mode, $this->slots, $this->text, $note );
	}

	/**
	 * Identity used to group equal days; the note is part of it so days with
	 * different remarks are never merged into one row.
	 */
	public function signature(): string {
		$slots = array_map( static fn( Slot $slot ): string => $slot->start . '-' . $slot->end, $this->slots );
		return $this->mode . '|' . implode( ',', $slots ) . '|' . $this->text . '|' . $this->note;
	}
}
