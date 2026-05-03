<?php
/**
 * Read-only claim data meta box in the post editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Claim_Admin_Meta {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_claim_edit_assets' ) );
		add_action( 'wp_ajax_optima_claims_delete_portal_attachment', array( __CLASS__, 'ajax_delete_portal_attachment' ) );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'optima_claims_api_data',
			__( 'Claim data (API)', 'optima-claims-sync' ),
			array( __CLASS__, 'render_meta_box' ),
			Optima_Claims_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Lightbox for full-size portal uploads (admin-ajax image URLs; avoids core Thickbox iframe sizing).
	 *
	 * @param string $hook_suffix
	 */
	public static function enqueue_claim_edit_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$post_type = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->post_type ) ) {
				$post_type = (string) $screen->post_type;
			}
		}
		if ( $post_type === '' ) {
			global $typenow;
			if ( is_string( $typenow ) && $typenow !== '' ) {
				$post_type = $typenow;
			}
		}
		if ( $post_type !== Optima_Claims_Post_Type::POST_TYPE ) {
			return;
		}

		$lightbox_js = 'assets/js/claim-portal-lightbox.js';
		$lightbox_fs = OPTIMA_CLAIMS_SYNC_PATH . $lightbox_js;
		$ver_lb      = is_readable( $lightbox_fs ) ? (string) filemtime( $lightbox_fs ) : OPTIMA_CLAIMS_SYNC_VERSION;

		wp_enqueue_script(
			'optima-claims-portal-lightbox',
			OPTIMA_CLAIMS_SYNC_URL . $lightbox_js,
			array(),
			$ver_lb,
			true
		);
		wp_localize_script(
			'optima-claims-portal-lightbox',
			'optimaClaimsPortalLightbox',
			array(
				'closeLabel' => __( 'Close', 'optima-claims-sync' ),
			)
		);

		$uploads_js = 'assets/js/claim-portal-uploads-admin.js';
		$uploads_fs = OPTIMA_CLAIMS_SYNC_PATH . $uploads_js;
		$ver_up     = is_readable( $uploads_fs ) ? (string) filemtime( $uploads_fs ) : OPTIMA_CLAIMS_SYNC_VERSION;
		wp_enqueue_script(
			'optima-claims-portal-uploads-admin',
			OPTIMA_CLAIMS_SYNC_URL . $uploads_js,
			array(),
			$ver_up,
			true
		);
		wp_localize_script(
			'optima-claims-portal-uploads-admin',
			'optimaClaimsPortalUploads',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'deleteAction'  => 'optima_claims_delete_portal_attachment',
				'confirmRemove' => __( 'Remove this image and its description from the claim? This cannot be undone.', 'optima-claims-sync' ),
				'errorGeneric'  => __( 'Could not remove the file.', 'optima-claims-sync' ),
				'emptyMessage'  => __( 'No portal images yet.', 'optima-claims-sync' ),
			)
		);

		wp_register_style( 'optima-claims-portal-uploads', false, array(), OPTIMA_CLAIMS_SYNC_VERSION );
		wp_enqueue_style( 'optima-claims-portal-uploads' );
		wp_add_inline_style(
			'optima-claims-portal-uploads',
			'.optima-claims-portal-uploads { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
			.optima-claims-portal-uploads__item { width: 12.5rem; max-width: 100%; border: 1px solid #c3c4c7; border-radius: 2px; padding: 0.5rem; background: #fff; box-sizing: border-box; }
			.optima-claims-portal-uploads__thumb { display: block; line-height: 0; margin-bottom: 0.5rem; cursor: zoom-in; }
			.optima-claims-portal-uploads__thumb img { width: 100%; height: auto; vertical-align: middle; border-radius: 2px; }
			.optima-claims-portal-uploads__thumb:focus { outline: 2px solid #2271b1; outline-offset: 2px; }
			.optima-claims-portal-uploads__desc { font-size: 12px; color: #1d2327; line-height: 1.45; margin: 0; }
			.optima-claims-portal-uploads__tools { margin-top: 0.35rem; margin-bottom: 0; }
			.optima-claims-portal-remove:disabled { opacity: 0.55; cursor: not-allowed; }
			.optima-claims-portal-lightbox-backdrop { position: fixed; inset: 0; z-index: 1000500; background: rgba(0,0,0,0.88); display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
			.optima-claims-portal-lightbox-backdrop__inner { max-width: 100%; max-height: 100%; overflow: auto; line-height: 0; }
			.optima-claims-portal-lightbox-backdrop__img { display: block; margin: 0 auto; width: auto; height: auto; max-width: calc(100vw - 48px); max-height: calc(100vh - 48px); object-fit: contain; }
			.optima-claims-portal-lightbox-backdrop__close { position: fixed; top: 8px; right: 8px; z-index: 1000501; width: 40px; height: 40px; margin: 0; padding: 0; border: none; border-radius: 4px; background: #1d2327; color: #fff; font-size: 28px; line-height: 1; cursor: pointer; }
			.optima-claims-portal-lightbox-backdrop__close:hover, .optima-claims-portal-lightbox-backdrop__close:focus { background: #2271b1; color: #fff; outline: 2px solid #fff; outline-offset: 2px; }'
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$core_id      = get_post_meta( $post->ID, Optima_Claims_Sync_Service::META_API_ID, true );
		$core_created = get_post_meta( $post->ID, Optima_Claims_Sync_Service::META_CORE_CREATED_AT, true );

		echo '<div class="optima-claims-admin-meta">';
		echo '<p class="description">' . esc_html__( 'Values are read-only and updated when you run a sync.', 'optima-claims-sync' ) . '</p>';

		echo '<h4>' . esc_html__( 'Core', 'optima-claims-sync' ) . '</h4>';
		echo '<table class="widefat striped" style="margin-top:0.35em;">';
		echo '<tbody>';
		self::output_row( 'id', (string) $core_id, Optima_Claims_Sync_Service::META_API_ID );
		self::output_row( 'created_at', (string) $core_created, Optima_Claims_Sync_Service::META_CORE_CREATED_AT );
		echo '</tbody></table>';

		$custom = get_post_custom( $post->ID );
		$rows   = array();
		foreach ( $custom as $key => $values ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}
			if ( strpos( $key, 'optima_claim_' ) !== 0 ) {
				continue;
			}
			if ( $key === Optima_Claims_Sync_Service::META_CORE_CREATED_AT ) {
				continue;
			}
			if ( $key === Optima_Claims_Sync_Service::META_REFERENCE ) {
				continue;
			}
			$rows[ $key ] = isset( $values[0] ) && is_string( $values[0] ) ? $values[0] : ( isset( $values[0] ) ? (string) $values[0] : '' );
		}

		$label_map = array_merge( self::built_in_imported_field_labels(), self::import_path_label_map() );

		uksort(
			$rows,
			static function ( string $a, string $b ) use ( $label_map ): int {
				$la = self::display_label_for_meta_key( $a, $label_map );
				$lb = self::display_label_for_meta_key( $b, $label_map );
				return strnatcasecmp( $la, $lb );
			}
		);

		if ( $rows !== array() ) {
			echo '<h4 style="margin-top:1.25em;">' . esc_html__( 'Imported and synced fields', 'optima-claims-sync' ) . '</h4>';
			echo '<table class="widefat striped" style="margin-top:0.35em;">';
			echo '<tbody>';
			foreach ( $rows as $meta_key => $value ) {
				$display_label = self::display_label_for_meta_key( $meta_key, $label_map );
				self::output_row( $display_label, $value, $meta_key );
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description" style="margin-top:1em;">' . esc_html__( 'No extra claim meta yet. Choose fields under Claims API → Field Import and run a sync, or use automatic mapping.', 'optima-claims-sync' ) . '</p>';
		}

		self::render_portal_uploads_section( $post->ID );

		echo '</div>';
	}

	/**
	 * Image attachments for this claim shown as portal uploads.
	 *
	 * Includes `_optima_claims_portal_upload = 1` (new uploads) and legacy rows with no flag
	 * (uploads from before that meta existed).
	 *
	 * @param int $claim_post_id
	 */
	private static function render_portal_uploads_section( int $claim_post_id ): void {
		$portal_meta = Optima_Claims_Frontend_Lookup::ATTACHMENT_META_PORTAL;

		$attachments = get_posts(
			array(
				'post_parent'    => $claim_post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => $portal_meta,
						'value' => '1',
					),
					array(
						'key'     => $portal_meta,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		echo '<h4 style="margin-top:1.25em;">' . esc_html__( 'Portal uploads', 'optima-claims-sync' ) . '</h4>';

		if ( $attachments === array() ) {
			echo '<p class="description" style="margin-top:0.5em;">' . esc_html__( 'No portal images yet.', 'optima-claims-sync' ) . '</p>';
			return;
		}

		echo '<div class="optima-claims-portal-uploads">';
		foreach ( $attachments as $attachment ) {
			if ( ! $attachment instanceof WP_Post ) {
				continue;
			}
			$att_id = (int) $attachment->ID;
			$path   = Optima_Claims_Secure_Portal_Media::get_resolved_file_path( $att_id, 'full' );
			if ( ! $path ) {
				continue;
			}
			$thumb_url = Optima_Claims_Secure_Portal_Media::get_view_url( $att_id, 'thumbnail' );
			$full_view = Optima_Claims_Secure_Portal_Media::get_view_url( $att_id, 'full' );
			$thumb_html = sprintf(
				'<img src="%s" class="optima-claims-portal-thumb-img" loading="lazy" alt="" />',
				esc_url( $thumb_url )
			);
			$description = isset( $attachment->post_content ) ? trim( (string) $attachment->post_content ) : '';

			echo '<div class="optima-claims-portal-uploads__item">';
			printf(
				'<a class="optima-claims-portal-uploads__thumb optima-claims-portal-lightbox" href="%s" title="%s">%s</a>',
				esc_url( $full_view ),
				esc_attr( wp_strip_all_tags( $description !== '' ? $description : get_the_title( $att_id ) ) ),
				$thumb_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_url() for src.
			);
			if ( $description !== '' ) {
				echo '<p class="optima-claims-portal-uploads__desc">' . esc_html( $description ) . '</p>';
			} else {
				echo '<p class="optima-claims-portal-uploads__desc"><em>' . esc_html__( 'No description provided.', 'optima-claims-sync' ) . '</em></p>';
			}
			if ( current_user_can( 'edit_post', $claim_post_id ) ) {
				printf(
					'<p class="optima-claims-portal-uploads__tools"><button type="button" class="button-link button-link-delete optima-claims-portal-remove" data-attachment-id="%d" data-nonce="%s">%s</button></p>',
					$att_id,
					esc_attr( wp_create_nonce( 'optima_claims_delete_portal_' . $att_id ) ),
					esc_html__( 'Remove', 'optima-claims-sync' )
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Delete a portal-upload attachment (file + post row, including description in post_content).
	 */
	public static function ajax_delete_portal_attachment(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'optima-claims-sync' ) ), 403 );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'optima-claims-sync' ) ) );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'optima_claims_delete_portal_' . $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'optima-claims-sync' ) ) );
		}
		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || $post->post_type !== 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'optima-claims-sync' ) ) );
		}
		$claim_id = (int) $post->post_parent;
		if ( $claim_id <= 0 || get_post_type( $claim_id ) !== Optima_Claims_Post_Type::POST_TYPE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'optima-claims-sync' ) ) );
		}
		if ( ! current_user_can( 'edit_post', $claim_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'optima-claims-sync' ) ), 403 );
		}
		if ( strpos( (string) $post->post_mime_type, 'image/' ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'optima-claims-sync' ) ) );
		}
		if ( ! self::attachment_is_listed_portal_upload( $attachment_id, $claim_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This file cannot be removed from here.', 'optima-claims-sync' ) ) );
		}
		if ( ! wp_delete_attachment( $attachment_id, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not remove the file.', 'optima-claims-sync' ) ) );
		}
		wp_send_json_success();
	}

	/**
	 * Same inclusion rules as {@see render_portal_uploads_section()} (portal flag or legacy with no flag).
	 */
	private static function attachment_is_listed_portal_upload( int $attachment_id, int $claim_post_id ): bool {
		$att = get_post( $attachment_id );
		if ( ! $att instanceof WP_Post || $att->post_type !== 'attachment' ) {
			return false;
		}
		if ( (int) $att->post_parent !== $claim_post_id ) {
			return false;
		}
		$portal_meta = Optima_Claims_Frontend_Lookup::ATTACHMENT_META_PORTAL;
		if ( ! metadata_exists( 'post', $attachment_id, $portal_meta ) ) {
			return true;
		}
		return get_post_meta( $attachment_id, $portal_meta, true ) === '1';
	}

	/**
	 * Labels for legacy auto-mapped and system meta (not tied to Field Import paths).
	 *
	 * @return array<string, string>
	 */
	private static function built_in_imported_field_labels(): array {
		return array(
			Optima_Claims_Sync_Service::META_SYNCED_AT       => 'synced_at',
			Optima_Claims_Sync_Service::META_STATUS         => 'status',
			Optima_Claims_Sync_Service::META_CLAIMANT       => 'claimant_name',
			Optima_Claims_Sync_Service::META_POLICY         => 'policy_number',
			Optima_Claims_Sync_Service::META_DATE_OF_LOSS   => 'date_of_loss',
			Optima_Claims_Sync_Service::META_EMAIL          => 'contact_email',
			Optima_Claims_Sync_Service::META_PHONE         => 'contact_phone',
			Optima_Claims_Sync_Service::META_EXTERNAL_STATUS => 'external_status',
		);
	}

	/**
	 * Map meta_key => API path from current Field Import settings.
	 *
	 * @return array<string, string>
	 */
	private static function import_path_label_map(): array {
		$settings = Optima_Claims_Admin_Settings::get_settings();
		$out      = array();
		if ( empty( $settings['import_paths'] ) || ! is_array( $settings['import_paths'] ) ) {
			return $out;
		}
		foreach ( $settings['import_paths'] as $p ) {
			$p = is_string( $p ) ? trim( $p ) : '';
			if ( $p === '' ) {
				continue;
			}
			$key          = Optima_Claims_Field_Discovery::path_to_meta_key( $p );
			$out[ $key ] = $p;
		}
		return $out;
	}

	/**
	 * Human-readable API-style label for a stored meta key.
	 *
	 * @param array<string, string> $label_map
	 */
	private static function display_label_for_meta_key( string $meta_key, array $label_map ): string {
		if ( isset( $label_map[ $meta_key ] ) ) {
			return $label_map[ $meta_key ];
		}
		$prefix = 'optima_claim_';
		if ( strpos( $meta_key, $prefix ) !== 0 ) {
			return $meta_key;
		}
		$rest = substr( $meta_key, strlen( $prefix ) );
		if ( preg_match( '/^[a-f0-9]{32}$/', $rest ) ) {
			return 'imported_field_' . substr( $rest, 0, 8 );
		}
		return $rest;
	}

	/**
	 * @param string      $label        Shown in the first column (API field name).
	 * @param string      $value        Cell value.
	 * @param string      $storage_key  Post meta key (tooltip for support).
	 */
	private static function output_row( string $label, string $value, string $storage_key = '' ): void {
		$display = $value;
		if ( $display === '' ) {
			$display = '—';
		}
		$title = $storage_key !== '' ? ' title="' . esc_attr(
			/* translators: %s: post meta key */
			sprintf( __( 'Stored as post meta: %s', 'optima-claims-sync' ), $storage_key )
		) . '"' : '';
		echo '<tr>';
		echo '<th scope="row" style="width:14rem;vertical-align:top;"><code' . $title . '>' . esc_html( $label ) . '</code></th>';
		echo '<td>';
		if ( self::looks_like_json( $value ) ) {
			echo '<pre style="margin:0;white-space:pre-wrap;word-break:break-word;max-height:20em;overflow:auto;">' . esc_html( $value ) . '</pre>';
		} else {
			echo '<code style="word-break:break-word;">' . esc_html( $display ) . '</code>';
		}
		echo '</td>';
		echo '</tr>';
	}

	private static function looks_like_json( string $value ): bool {
		if ( $value === '' ) {
			return false;
		}
		$t = ltrim( $value );
		return ( $t !== '' && ( $t[0] === '{' || $t[0] === '[' ) );
	}
}
