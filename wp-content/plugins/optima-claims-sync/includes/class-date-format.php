<?php
/**
 * Normalizes API and generated datetimes to d-m-y H:i:s (site timezone).
 * Date-only values use 00:00:00 for the time part.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Date_Format {

	/**
	 * Format a Unix timestamp using the site timezone (e.g. sync time).
	 */
	public static function format_timestamp( int $timestamp ): string {
		return wp_date( 'd-m-y H:i:s', $timestamp );
	}

	/**
	 * If the string is parseable as a date/datetime, return d-m-y H:i:s or d-m-y 00:00:00 for date-only.
	 * Otherwise return the original string.
	 */
	public static function format_if_datetime( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return $value;
		}

		if ( preg_match( '/^\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}

		if ( strlen( $value ) > 120 ) {
			return $value;
		}

		$lead = ltrim( $value );
		if ( $lead !== '' && ( $lead[0] === '{' || $lead[0] === '[' ) ) {
			return $value;
		}

		try {
			$dt = new \DateTimeImmutable( $value );
		} catch ( \Exception $e ) {
			return $value;
		}

		$has_time = (bool) preg_match( '/[T ]\d{1,2}:\d{2}/', $value );

		if ( $has_time ) {
			return wp_date( 'd-m-y H:i:s', $dt->getTimestamp() );
		}

		return wp_date( 'd-m-y 00:00:00', $dt->getTimestamp() );
	}
}
