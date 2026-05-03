<?php
/**
 * Settings UI and storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Admin_Settings {

	public const FIELD_DISCOVERY_OPTION = 'optima_claims_field_discovery';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_discover_fields' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_sync' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_scripts' ) );
		add_action( 'wp_ajax_optima_claims_sync_stream', array( __CLASS__, 'ajax_sync_stream' ) );
	}

	/**
	 * @param string $hook_suffix
	 */
	public static function enqueue_settings_scripts( string $hook_suffix ): void {
		if ( $hook_suffix !== 'toplevel_page_optima-claims-sync' ) {
			return;
		}
		$rel = 'assets/js/sync-progress.js';
		$fs  = OPTIMA_CLAIMS_SYNC_PATH . $rel;
		$ver = is_readable( $fs ) ? (string) filemtime( $fs ) : OPTIMA_CLAIMS_SYNC_VERSION;
		wp_enqueue_script(
			'optima-claims-sync-progress',
			OPTIMA_CLAIMS_SYNC_URL . $rel,
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'optima-claims-sync-progress',
			'optimaClaimsSync',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'optima_claims_sync_run_now' ),
				'strings' => array(
					'lastSyncPrefix' => __( 'Last sync finished at (UTC):', 'optima-claims-sync' ),
					'fetching'       => __( 'Fetching claims from API…', 'optima-claims-sync' ),
					'fetchingElapsed' => __( 'Fetching claims from API… (%d s)', 'optima-claims-sync' ),
					'fetchPage'      => __( 'API page %1$s (%2$s claims retrieved)…', 'optima-claims-sync' ),
					'fetchPageOf'    => __( 'API page %1$s of %2$s (%3$s claims retrieved)…', 'optima-claims-sync' ),
					'apiReturned'    => __( 'API returned %s claim(s). Saving…', 'optima-claims-sync' ),
					'processing'     => __( 'Processing claim %1$s of %2$s…', 'optima-claims-sync' ),
					'statsInline'    => __( '%1$d created, %2$d updated, %3$d skipped.', 'optima-claims-sync' ),
					'syncFailed'     => __( 'Sync failed.', 'optima-claims-sync' ),
				),
			)
		);
	}

	public static function ajax_sync_stream(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'optima-claims-sync' ) ), 403 );
		}
		check_ajax_referer( 'optima_claims_sync_run_now', 'nonce' );

		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/x-ndjson; charset=utf-8' );
		header( 'X-Accel-Buffering: no' );
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', '0' );

		$send = static function ( array $payload ): void {
			echo wp_json_encode( $payload ) . "\n";
			if ( function_exists( 'wp_ob_end_flush_all' ) ) {
				wp_ob_end_flush_all();
			} elseif ( function_exists( 'ob_flush' ) ) {
				@ob_flush();
			}
			flush();
		};

		// Prime the stream so some proxies deliver the first chunk before the long API call.
		$send( array( 'type' => 'ready' ) );

		$service = new Optima_Claims_Sync_Service();
		$result  = $service->sync_all(
			static function ( array $p ) use ( $send ): void {
				$send( array_merge( array( 'type' => 'progress' ), $p ) );
			}
		);

		if ( is_wp_error( $result ) ) {
			$send(
				array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				)
			);
		} else {
			$finished = get_option( 'optima_claims_sync_last_run_at', '' );
			if ( ! is_string( $finished ) || $finished === '' ) {
				$finished = current_time( 'mysql', true );
			}
			$send(
				array(
					'type'            => 'complete',
					'stats'           => $result,
					'finished_at_utc' => $finished,
				)
			);
		}
		exit;
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Claims API', 'optima-claims-sync' ),
			__( 'Claims API', 'optima-claims-sync' ),
			'manage_options',
			'optima-claims-sync',
			array( __CLASS__, 'render_page' ),
			'dashicons-update',
			58
		);
	}

	/**
	 * @return array
	 */
	public static function get_settings(): array {
		$stored = get_option( OPTIMA_CLAIMS_SYNC_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @return array
	 */
	private static function defaults(): array {
		return array(
			'api_base_url'        => '',
			'endpoint_path'       => '/claims',
			'http_method'         => 'GET',
			'auth_type'           => 'none',
			'api_key'             => '',
			'api_key_header_name' => 'X-API-Key',
			'basic_auth_user'     => '',
			'basic_auth_password' => '',
			'post_body_mode'      => 'json',
			'post_body_json'      => '{}',
			'extra_headers_json'  => '',
			'claims_array_path'   => '',
			'timeout_seconds'       => 30,
			'claims_import_scope'   => 'all',
			'claims_sync_type_allowlist' => '',
			'claims_sync_type_json_path' => '',
			'sync_enabled'          => false,
			'cron_interval'         => 'daily',
			'import_paths'          => array(),
		);
	}

	public static function handle_discover_fields(): void {
		if ( ! isset( $_POST['optima_claims_discover_fields'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'optima_claims_discover_fields', '_wpdiscover_nonce' );

		$client = new Optima_Claims_Api_Client();
		$root   = $client->fetch_claims();

		if ( is_wp_error( $root ) ) {
			add_settings_error(
				'optima_claims_sync',
				'discover_failed',
				$root->get_error_message(),
				'error'
			);
			self::redirect_with_settings_notices();
			exit;
		}

		$settings = self::get_settings();
		$path     = isset( $settings['claims_array_path'] ) ? trim( (string) $settings['claims_array_path'] ) : '';
		$items    = Optima_Claims_Sync_Service::array_path( $root, $path );

		if ( null === $items ) {
			add_settings_error(
				'optima_claims_sync',
				'discover_no_list',
				__( 'Could not find the claims list. Check “Claims array path” and try again.', 'optima-claims-sync' ),
				'error'
			);
			self::redirect_with_settings_notices();
			exit;
		}

		$items = Optima_Claims_Sync_Service::normalize_to_list( $items );
		if ( $items === array() ) {
			add_settings_error(
				'optima_claims_sync',
				'discover_empty',
				__( 'The API returned no claim rows to inspect.', 'optima-claims-sync' ),
				'error'
			);
			self::redirect_with_settings_notices();
			exit;
		}

		$first = $items[0];
		if ( ! is_array( $first ) ) {
			add_settings_error(
				'optima_claims_sync',
				'discover_bad_row',
				__( 'The first claim row was not an object.', 'optima-claims-sync' ),
				'error'
			);
			self::redirect_with_settings_notices();
			exit;
		}

		$paths = Optima_Claims_Field_Discovery::discover_paths( $first );

		update_option(
			self::FIELD_DISCOVERY_OPTION,
			array(
				'captured_at' => current_time( 'mysql', true ),
				'paths'       => $paths,
			),
			false
		);

		add_settings_error(
			'optima_claims_sync',
			'discover_ok',
			sprintf(
				/* translators: %d: number of paths found */
				__( 'Discovered %d fields from the first claim in the API response.', 'optima-claims-sync' ),
				count( $paths )
			),
			'success'
		);

		self::redirect_with_settings_notices();
		exit;
	}

	/**
	 * Persist settings_errors across redirect (same pattern as manual sync).
	 */
	private static function redirect_with_settings_notices(): void {
		set_transient( 'optima_claims_sync_settings_errors', get_settings_errors( 'optima_claims_sync' ), 30 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'optima-claims-sync',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
	}

	public static function handle_manual_sync(): void {
		if ( ! isset( $_POST['optima_claims_sync_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( (string) $_POST['optima_claims_sync_action'] ) );

		if ( $action === 'run_now' ) {
			check_admin_referer( 'optima_claims_sync_run_now' );

			$service = new Optima_Claims_Sync_Service();
			$result  = $service->sync_all();

			if ( is_wp_error( $result ) ) {
				add_settings_error(
					'optima_claims_sync',
					'sync_failed',
					$result->get_error_message(),
					'error'
				);
			} else {
				$msg = sprintf(
					/* translators: 1: created count, 2: updated count, 3: skipped count */
					__( 'Sync finished. Created: %1$d, updated: %2$d, skipped: %3$d.', 'optima-claims-sync' ),
					(int) $result['created'],
					(int) $result['updated'],
					(int) $result['skipped']
				);
				if ( ! empty( $result['errors'] ) ) {
					$msg .= ' ' . __( 'Some rows reported errors:', 'optima-claims-sync' ) . ' ' . esc_html( implode( '; ', $result['errors'] ) );
				}
				add_settings_error(
					'optima_claims_sync',
					'sync_ok',
					$msg,
					'success'
				);
			}

			self::redirect_with_settings_notices();
			exit;
		}

		if ( $action === 'delete_closed_claims' ) {
			check_admin_referer( 'optima_claims_sync_delete_closed' );

			$service = new Optima_Claims_Sync_Service();
			$result  = $service->delete_closed_claim_posts();

			$msg = sprintf(
				/* translators: 1: number deleted, 2: number left untouched */
				__( 'Removed %1$d closed claim(s). Left %2$d other claim(s) (open, unknown status, or non-matching).', 'optima-claims-sync' ),
				(int) $result['deleted'],
				(int) $result['skipped']
			);
			if ( ! empty( $result['errors'] ) ) {
				$msg .= ' ' . __( 'Errors:', 'optima-claims-sync' ) . ' ' . esc_html( implode( '; ', $result['errors'] ) );
			}
			add_settings_error(
				'optima_claims_sync',
				'delete_closed_ok',
				$msg,
				empty( $result['errors'] ) ? 'success' : 'warning'
			);

			self::redirect_with_settings_notices();
			exit;
		}
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			$errors = get_transient( 'optima_claims_sync_settings_errors' );
			if ( is_array( $errors ) ) {
				foreach ( $errors as $err ) {
					add_settings_error(
						$err['setting'] ?? 'optima_claims_sync',
						$err['code'] ?? '',
						$err['message'] ?? '',
						$err['type'] ?? 'info'
					);
				}
				delete_transient( 'optima_claims_sync_settings_errors' );
			}
		}

		if ( isset( $_POST['optima_claims_sync_save'] ) && check_admin_referer( 'optima_claims_sync_save_settings' ) ) {
			self::save_from_post();
			add_settings_error(
				'optima_claims_sync',
				'saved',
				__( 'Settings saved.', 'optima-claims-sync' ),
				'success'
			);
		}

		settings_errors( 'optima_claims_sync' );

		$s              = self::get_settings();
		$next           = wp_next_scheduled( Optima_Claims_Cron::HOOK );
		$last_run       = get_option( 'optima_claims_sync_last_run_at', '' );
		$last_stats     = get_option( 'optima_claims_sync_last_stats', null );
		$discovery      = get_option( self::FIELD_DISCOVERY_OPTION, null );

		include OPTIMA_CLAIMS_SYNC_PATH . 'includes/views/settings-page.php';
	}

	private static function save_from_post(): void {
		$raw = array(
			'api_base_url'        => isset( $_POST['api_base_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['api_base_url'] ) ) : '',
			'endpoint_path'       => isset( $_POST['endpoint_path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['endpoint_path'] ) ) : '/claims',
			'http_method'         => isset( $_POST['http_method'] ) && $_POST['http_method'] === 'POST' ? 'POST' : 'GET',
			'auth_type'           => isset( $_POST['auth_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['auth_type'] ) ) : 'none',
			'api_key'             => isset( $_POST['api_key'] ) ? self::sanitize_api_secret( wp_unslash( (string) $_POST['api_key'] ) ) : '',
			'api_key_header_name' => isset( $_POST['api_key_header_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['api_key_header_name'] ) ) : 'X-API-Key',
			'basic_auth_user'     => isset( $_POST['basic_auth_user'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['basic_auth_user'] ) ) : '',
			'basic_auth_password' => isset( $_POST['basic_auth_password'] ) ? self::sanitize_api_secret( wp_unslash( (string) $_POST['basic_auth_password'] ) ) : '',
			'post_body_mode'      => isset( $_POST['post_body_mode'] ) && $_POST['post_body_mode'] === 'form' ? 'form' : 'json',
			'post_body_json'      => isset( $_POST['post_body_json'] ) ? self::sanitize_json_textarea( wp_unslash( (string) $_POST['post_body_json'] ) ) : '{}',
			'extra_headers_json'  => isset( $_POST['extra_headers_json'] ) ? self::sanitize_json_textarea( wp_unslash( (string) $_POST['extra_headers_json'] ) ) : '',
			'claims_array_path'   => isset( $_POST['claims_array_path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['claims_array_path'] ) ) : '',
			'claims_import_scope' => ( isset( $_POST['claims_import_scope'] ) && wp_unslash( (string) $_POST['claims_import_scope'] ) === 'open' ) ? 'open' : 'all',
			'claims_sync_type_allowlist' => isset( $_POST['claims_sync_type_allowlist'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['claims_sync_type_allowlist'] ) ) : '',
			'claims_sync_type_json_path' => isset( $_POST['claims_sync_type_json_path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['claims_sync_type_json_path'] ) ) : '',
			'timeout_seconds'     => isset( $_POST['timeout_seconds'] ) ? absint( $_POST['timeout_seconds'] ) : 30,
			'sync_enabled'        => ! empty( $_POST['sync_enabled'] ),
			'cron_interval'       => isset( $_POST['cron_interval'] ) ? sanitize_key( wp_unslash( (string) $_POST['cron_interval'] ) ) : 'daily',
		);

		if ( ! in_array( $raw['cron_interval'], array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$raw['cron_interval'] = 'daily';
		}

		if ( ! in_array( $raw['auth_type'], array( 'none', 'bearer', 'api_key_header', 'basic' ), true ) ) {
			$raw['auth_type'] = 'none';
		}

		$prev         = self::get_settings();
		$incoming_key = isset( $_POST['api_key'] ) ? trim( (string) wp_unslash( (string) $_POST['api_key'] ) ) : '';
		if ( $incoming_key === '' ) {
			$raw['api_key'] = $prev['api_key'];
		}

		$incoming_pass = isset( $_POST['basic_auth_password'] ) ? trim( (string) wp_unslash( (string) $_POST['basic_auth_password'] ) ) : '';
		if ( $incoming_pass === '' ) {
			$raw['basic_auth_password'] = $prev['basic_auth_password'];
		}

		if ( ! empty( $_POST['optima_claims_field_import_present'] ) ) {
			$paths_in = array();
			if ( ! empty( $_POST['import_paths'] ) && is_array( $_POST['import_paths'] ) ) {
				foreach ( wp_unslash( $_POST['import_paths'] ) as $p ) {
					$p = sanitize_text_field( (string) $p );
					if ( $p !== '' ) {
						$paths_in[] = $p;
					}
				}
			}
			$raw['import_paths'] = array_values( array_unique( $paths_in ) );
		} else {
			$raw['import_paths'] = isset( $prev['import_paths'] ) && is_array( $prev['import_paths'] ) ? $prev['import_paths'] : array();
		}

		update_option( OPTIMA_CLAIMS_SYNC_OPTION, $raw, false );

		Optima_Claims_Cron::reschedule();
	}

	private static function sanitize_api_secret( string $value ): string {
		return trim( $value );
	}

	private static function sanitize_json_textarea( string $json ): string {
		$json = trim( $json );
		if ( $json === '' ) {
			return '{}';
		}
		json_decode( $json );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return '{}';
		}
		return $json;
	}
}
