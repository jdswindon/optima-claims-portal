<?php
/**
 * Front-end claim lookup by imported meta fields and image uploads for matched claims.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Frontend_Lookup {

	public const LOOKUP_PATH_CLAIMABLE    = 'claimable_id';
	public const LOOKUP_PATH_REGISTRATION = 'subject.registration_number';

	/** Marks attachments created via the front-end portal (admin list filters on this). */
	public const ATTACHMENT_META_PORTAL = '_optima_claims_portal_upload';

	private const TRANSIENT_PREFIX = 'optima_cl_sess_';
	private const SESSION_TTL      = HOUR_IN_SECONDS;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'handle_requests' ), 20 );
	}

	public static function meta_key_claimable(): string {
		return Optima_Claims_Field_Discovery::path_to_meta_key( self::LOOKUP_PATH_CLAIMABLE );
	}

	public static function meta_key_registration(): string {
		return Optima_Claims_Field_Discovery::path_to_meta_key( self::LOOKUP_PATH_REGISTRATION );
	}

	/**
	 * Find a claim post where both meta values match (exact, after trim).
	 */
	public static function find_claim_id( string $claimable_id, string $registration ): int {
		$claimable_id = sanitize_text_field( $claimable_id );
		$registration = sanitize_text_field( $registration );
		if ( $claimable_id === '' || $registration === '' ) {
			return 0;
		}

		$args = array(
			'post_type'              => Optima_Claims_Post_Type::POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'     => self::meta_key_claimable(),
					'value'   => $claimable_id,
					'compare' => '=',
				),
				array(
					'key'     => self::meta_key_registration(),
					'value'   => $registration,
					'compare' => '=',
				),
			),
		);

		/**
		 * @param array  $args           {@see WP_Query} arguments.
		 * @param string $claimable_id  Trimmed claimable id.
		 * @param string $registration  Trimmed registration number.
		 */
		$args = apply_filters( 'optima_claims_lookup_query_args', $args, $claimable_id, $registration );

		$q = new WP_Query( $args );
		return $q->have_posts() ? (int) $q->posts[0] : 0;
	}

	public static function handle_requests(): void {
		if ( is_admin() ) {
			return;
		}

		if ( isset( $_POST['optima_claim_lookup_submit'] ) ) {
			self::handle_lookup_submit();
		}

		if ( isset( $_POST['optima_claim_upload_submit'] ) ) {
			self::handle_upload_submit();
		}
	}

	private static function handle_lookup_submit(): void {
		if ( ! isset( $_POST['optima_claim_lookup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['optima_claim_lookup_nonce'] ) ), 'optima_claim_lookup' ) ) {
			return;
		}

		$claimable    = isset( $_POST['claimable_id'] ) ? wp_unslash( (string) $_POST['claimable_id'] ) : '';
		$registration = isset( $_POST['subject_registration_number'] ) ? wp_unslash( (string) $_POST['subject_registration_number'] ) : '';
		$redirect     = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );
		$redirect     = remove_query_arg( array( 'lookup_err', 'claim_session', 'upload_err', 'upload_ok' ), $redirect );

		$post_id = self::find_claim_id( $claimable, $registration );
		if ( ! $post_id ) {
			wp_safe_redirect( add_query_arg( 'lookup_err', 'no_match', $redirect ) );
			exit;
		}

		$token = bin2hex( random_bytes( 16 ) );
		set_transient( self::TRANSIENT_PREFIX . $token, $post_id, self::SESSION_TTL );

		wp_safe_redirect( add_query_arg( 'claim_session', $token, $redirect ) );
		exit;
	}

	/**
	 * @param string $token Hex session token from query string.
	 */
	public static function get_session_post_id( string $token ): int {
		$token = strtolower( preg_replace( '/[^a-f0-9]/', '', $token ) );
		if ( strlen( $token ) !== 32 ) {
			return 0;
		}
		$pid = get_transient( self::TRANSIENT_PREFIX . $token );
		return $pid ? (int) $pid : 0;
	}

	public static function is_valid_session( string $token ): bool {
		$pid = self::get_session_post_id( $token );
		if ( ! $pid ) {
			return false;
		}
		return get_post_type( $pid ) === Optima_Claims_Post_Type::POST_TYPE;
	}

	private static function handle_upload_submit(): void {
		$token = isset( $_POST['claim_session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['claim_session_token'] ) ) : '';
		if ( $token === '' || ! isset( $_POST['optima_claim_upload_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['optima_claim_upload_nonce'] ) ), 'optima_claim_upload_' . $token ) ) {
			return;
		}

		$redirect_base = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );
		$redirect_base = remove_query_arg( array( 'upload_err', 'upload_ok' ), $redirect_base );

		$post_id = self::get_session_post_id( $token );
		if ( ! $post_id || get_post_type( $post_id ) !== Optima_Claims_Post_Type::POST_TYPE ) {
			wp_safe_redirect( add_query_arg( array( 'lookup_err' => 'session_expired' ), remove_query_arg( 'claim_session', $redirect_base ) ) );
			exit;
		}

		if ( empty( $_FILES['claim_image_files'] ) || ! isset( $_FILES['claim_image_files']['name'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'claim_session' => $token, 'upload_err' => 'no_file' ), $redirect_base ) );
			exit;
		}

		$normalized = self::normalize_indexed_uploaded_files( $_FILES['claim_image_files'] );
		if ( $normalized === array() ) {
			wp_safe_redirect( add_query_arg( array( 'claim_session' => $token, 'upload_err' => 'no_file' ), $redirect_base ) );
			exit;
		}

		$descriptions_raw = isset( $_POST['claim_image_descriptions'] ) ? wp_unslash( $_POST['claim_image_descriptions'] ) : array();
		if ( ! is_array( $descriptions_raw ) ) {
			$descriptions_raw = array();
		}

		foreach ( array_keys( $normalized ) as $idx ) {
			$desc = isset( $descriptions_raw[ $idx ] ) ? sanitize_textarea_field( (string) $descriptions_raw[ $idx ] ) : '';
			if ( $desc === '' ) {
				wp_safe_redirect( add_query_arg( array( 'claim_session' => $token, 'upload_err' => 'description_required' ), $redirect_base ) );
				exit;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		Optima_Claims_Secure_Portal_Media::begin_portal_upload_directory();
		$uploaded = 0;
		try {
			foreach ( $normalized as $idx => $fileinfo ) {
				if ( ! is_array( $fileinfo ) || (int) $fileinfo['error'] !== UPLOAD_ERR_OK ) {
					continue;
				}
				$description = isset( $descriptions_raw[ $idx ] ) ? sanitize_textarea_field( (string) $descriptions_raw[ $idx ] ) : '';

				$check = wp_check_filetype_and_ext( $fileinfo['tmp_name'], $fileinfo['name'] );
				if ( empty( $check['type'] ) || strpos( $check['type'], 'image/' ) !== 0 ) {
					continue;
				}

				$movefile = wp_handle_upload(
					$fileinfo,
					array(
						'test_form' => false,
						'mimes'     => array(
							'jpg|jpeg|jpe' => 'image/jpeg',
							'gif'          => 'image/gif',
							'png'          => 'image/png',
							'webp'         => 'image/webp',
						),
					)
				);
				if ( ! $movefile || isset( $movefile['error'] ) ) {
					continue;
				}

				$filetype = wp_check_filetype( basename( $movefile['file'] ), null );
				if ( ! $filetype['type'] || strpos( $filetype['type'], 'image/' ) !== 0 ) {
					wp_delete_file( $movefile['file'] );
					continue;
				}

				$attachment = array(
					'post_mime_type' => $filetype['type'],
					'post_title'     => sanitize_file_name( pathinfo( $movefile['file'], PATHINFO_FILENAME ) ),
					'post_content'   => $description,
					'post_status'    => 'inherit',
				);
				$attach_id = wp_insert_attachment( $attachment, $movefile['file'], $post_id, true );
				if ( is_wp_error( $attach_id ) || ! $attach_id ) {
					wp_delete_file( $movefile['file'] );
					continue;
				}
				$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				update_post_meta( $attach_id, self::ATTACHMENT_META_PORTAL, '1' );
				++$uploaded;
			}
		} finally {
			Optima_Claims_Secure_Portal_Media::end_portal_upload_directory();
		}

		if ( $uploaded === 0 ) {
			wp_safe_redirect( add_query_arg( array( 'claim_session' => $token, 'upload_err' => 'upload_failed' ), $redirect_base ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'claim_session' => $token, 'upload_ok' => $uploaded ), $redirect_base ) );
		exit;
	}

	/**
	 * @param array<string, mixed> $files $_FILES['claim_image_files'] (fields named claim_image_files[n]).
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_indexed_uploaded_files( array $files ): array {
		if ( ! isset( $files['name'] ) ) {
			return array();
		}
		if ( ! is_array( $files['name'] ) ) {
			if ( is_string( $files['name'] ) && $files['name'] !== '' ) {
				return array( 0 => $files );
			}
			return array();
		}
		$out = array();
		foreach ( $files['name'] as $idx => $name ) {
			if ( ! is_string( $name ) || $name === '' ) {
				continue;
			}
			$key = (int) $idx;
			$out[ $key ] = array(
				'name'     => $files['name'][ $idx ],
				'type'     => $files['type'][ $idx ],
				'tmp_name' => $files['tmp_name'][ $idx ],
				'error'    => $files['error'][ $idx ],
				'size'     => $files['size'][ $idx ],
			);
		}
		ksort( $out, SORT_NUMERIC );
		return $out;
	}
}
