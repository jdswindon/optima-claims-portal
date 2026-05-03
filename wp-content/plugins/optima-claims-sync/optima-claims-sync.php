<?php
/**
 * Plugin Name: Optima Claims Sync
 * Description: Connects to an external claims API and syncs data into the Claims custom post type.
 * Version: 1.0.0
 * Author: Optima
 * Text Domain: optima-claims-sync
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPTIMA_CLAIMS_SYNC_VERSION', '1.0.0' );
define( 'OPTIMA_CLAIMS_SYNC_FILE', __FILE__ );
define( 'OPTIMA_CLAIMS_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'OPTIMA_CLAIMS_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'OPTIMA_CLAIMS_SYNC_OPTION', 'optima_claims_sync_settings' );

require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-plugin.php';

/**
 * @return Optima_Claims_Plugin
 */
function optima_claims_sync() {
	return Optima_Claims_Plugin::instance();
}

optima_claims_sync();

register_activation_hook(
	OPTIMA_CLAIMS_SYNC_FILE,
	function () {
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-post-type.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-admin-settings.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-api-client.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-sync-service.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-cron.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-secure-portal-media.php';
		Optima_Claims_Post_Type::register();
		Optima_Claims_Secure_Portal_Media::install_htaccess();
		flush_rewrite_rules();
		Optima_Claims_Cron::reschedule();
	}
);

register_deactivation_hook(
	OPTIMA_CLAIMS_SYNC_FILE,
	function () {
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-cron.php';
		Optima_Claims_Cron::clear();
		flush_rewrite_rules();
	}
);
