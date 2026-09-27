<?php
/**
 * "Now" in the site's timezone, overridable for testing via the
 * RMD_OH_FAKE_NOW constant (only honoured while WP_DEBUG is on).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class Clock {

	public static function timezone(): DateTimeZone {
		return wp_timezone();
	}

	public static function now(): DateTimeImmutable {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'RMD_OH_FAKE_NOW' ) && is_string( RMD_OH_FAKE_NOW ) ) {
			$fake = date_create_immutable( RMD_OH_FAKE_NOW, self::timezone() );
			if ( $fake ) {
				return $fake;
			}
		}
		return new DateTimeImmutable( 'now', self::timezone() );
	}

	public static function today(): string {
		return self::now()->format( 'Y-m-d' );
	}

	public static function timestamp(): int {
		return self::now()->getTimestamp();
	}
}
