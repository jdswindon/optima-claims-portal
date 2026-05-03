<?php
/**
 * Schedules periodic sync via WP-Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Cron {

	public const HOOK = 'optima_claims_sync_run';

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run_sync' ) );
	}

	public static function run_sync(): void {
		$settings = Optima_Claims_Admin_Settings::get_settings();
		if ( empty( $settings['sync_enabled'] ) ) {
			return;
		}
		$service = new Optima_Claims_Sync_Service();
		$service->sync_all();
	}

	/**
	 * Ensures a single cron event exists when sync is enabled in settings.
	 */
	public static function schedule(): void {
		$settings = Optima_Claims_Admin_Settings::get_settings();
		if ( empty( $settings['sync_enabled'] ) ) {
			return;
		}
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$interval = isset( $settings['cron_interval'] ) ? (string) $settings['cron_interval'] : 'daily';
		if ( ! in_array( $interval, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$interval = 'daily';
		}
		wp_schedule_event( time() + 60, $interval, self::HOOK );
	}

	public static function reschedule(): void {
		self::clear();
		self::schedule();
	}

	public static function clear(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}
}
