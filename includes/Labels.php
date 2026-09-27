<?php
/**
 * Translatable labels for domain identifiers (holidays, states, weekdays).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Domain\Dates;

defined( 'ABSPATH' ) || exit;

final class Labels {

	/**
	 * @return array<string, string> Holiday id => label.
	 */
	public static function holidays(): array {
		return [
			'neujahr'                   => __( 'New Year’s Day', 'rmd-opening-hours' ),
			'heilige_drei_koenige'      => __( 'Epiphany', 'rmd-opening-hours' ),
			'frauentag'                 => __( 'International Women’s Day', 'rmd-opening-hours' ),
			'karfreitag'                => __( 'Good Friday', 'rmd-opening-hours' ),
			'ostersonntag'              => __( 'Easter Sunday', 'rmd-opening-hours' ),
			'ostermontag'               => __( 'Easter Monday', 'rmd-opening-hours' ),
			'tag_der_arbeit'            => __( 'Labour Day', 'rmd-opening-hours' ),
			'christi_himmelfahrt'       => __( 'Ascension Day', 'rmd-opening-hours' ),
			'pfingstsonntag'            => __( 'Whit Sunday', 'rmd-opening-hours' ),
			'pfingstmontag'             => __( 'Whit Monday', 'rmd-opening-hours' ),
			'fronleichnam'              => __( 'Corpus Christi', 'rmd-opening-hours' ),
			'friedensfest'              => __( 'Augsburg Peace Festival', 'rmd-opening-hours' ),
			'mariae_himmelfahrt'        => __( 'Assumption Day', 'rmd-opening-hours' ),
			'weltkindertag'             => __( 'World Children’s Day', 'rmd-opening-hours' ),
			'tag_der_deutschen_einheit' => __( 'German Unity Day', 'rmd-opening-hours' ),
			'reformationstag'           => __( 'Reformation Day', 'rmd-opening-hours' ),
			'allerheiligen'             => __( 'All Saints’ Day', 'rmd-opening-hours' ),
			'buss_und_bettag'           => __( 'Day of Repentance and Prayer', 'rmd-opening-hours' ),
			'erster_weihnachtstag'      => __( 'Christmas Day', 'rmd-opening-hours' ),
			'zweiter_weihnachtstag'     => __( 'Boxing Day', 'rmd-opening-hours' ),
		];
	}

	public static function holiday( string $id ): string {
		return self::holidays()[ $id ] ?? $id;
	}

	/**
	 * @return array<string, string> State code => label.
	 */
	public static function states(): array {
		return [
			'BW' => __( 'Baden-Württemberg', 'rmd-opening-hours' ),
			'BY' => __( 'Bavaria', 'rmd-opening-hours' ),
			'BE' => __( 'Berlin', 'rmd-opening-hours' ),
			'BB' => __( 'Brandenburg', 'rmd-opening-hours' ),
			'HB' => __( 'Bremen', 'rmd-opening-hours' ),
			'HH' => __( 'Hamburg', 'rmd-opening-hours' ),
			'HE' => __( 'Hesse', 'rmd-opening-hours' ),
			'MV' => __( 'Mecklenburg-Vorpommern', 'rmd-opening-hours' ),
			'NI' => __( 'Lower Saxony', 'rmd-opening-hours' ),
			'NW' => __( 'North Rhine-Westphalia', 'rmd-opening-hours' ),
			'RP' => __( 'Rhineland-Palatinate', 'rmd-opening-hours' ),
			'SL' => __( 'Saarland', 'rmd-opening-hours' ),
			'SN' => __( 'Saxony', 'rmd-opening-hours' ),
			'ST' => __( 'Saxony-Anhalt', 'rmd-opening-hours' ),
			'SH' => __( 'Schleswig-Holstein', 'rmd-opening-hours' ),
			'TH' => __( 'Thuringia', 'rmd-opening-hours' ),
		];
	}

	/**
	 * Weekday names from the site locale.
	 *
	 * @return array<string, array{long: string, short: string}>
	 */
	public static function weekdays(): array {
		global $wp_locale;
		$names = [];
		foreach ( Dates::WEEKDAYS as $index => $weekday ) {
			$number            = ( $index + 1 ) % 7; // WP_Locale numbers Sunday as 0.
			$long              = $wp_locale->get_weekday( $number );
			$names[ $weekday ] = [
				'long'  => $long,
				'short' => $wp_locale->get_weekday_abbrev( $long ),
			];
		}
		return $names;
	}

	/**
	 * @return array<string, string>
	 */
	public static function layouts(): array {
		return [
			'table'      => __( 'Table', 'rmd-opening-hours' ),
			'list'       => __( 'List', 'rmd-opening-hours' ),
			'compact'    => __( 'Compact (one line)', 'rmd-opening-hours' ),
			'paragraphs' => __( 'Paragraphs (written out)', 'rmd-opening-hours' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function time_styles(): array {
		return [
			'24h'        => __( '24-hour (09:00–18:00)', 'rmd-opening-hours' ),
			'24h-suffix' => __( '24-hour with unit (08:00 – 12:00 h)', 'rmd-opening-hours' ),
			'24h-short'  => __( '24-hour, short (9–18)', 'rmd-opening-hours' ),
			'12h'        => __( '12-hour (9:00 am–6:00 pm)', 'rmd-opening-hours' ),
		];
	}
}
