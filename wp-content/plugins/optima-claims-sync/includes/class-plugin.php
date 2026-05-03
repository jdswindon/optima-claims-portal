<?php
/**
 * Bootstrap plugin services.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Plugin {

	private static ?Optima_Claims_Plugin $instance = null;

	public static function instance(): Optima_Claims_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		add_action( 'init', array( Optima_Claims_Post_Type::class, 'register' ), 5 );
		Optima_Claims_Secure_Portal_Media::init();
		Optima_Claims_Frontend_Lookup::init();
		Optima_Claims_Cron::init();
		if ( is_admin() ) {
			Optima_Claims_Admin_Settings::init();
			Optima_Claims_Claim_Admin_Meta::init();
		}
	}

	private function load_dependencies(): void {
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-post-type.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-field-discovery.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-secure-portal-media.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-frontend-lookup.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-date-format.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-api-client.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-sync-service.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-cron.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-admin-settings.php';
		require_once OPTIMA_CLAIMS_SYNC_PATH . 'includes/class-claim-admin-meta.php';
	}
}
