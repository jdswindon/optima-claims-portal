<?php
/**
 * Store portal-uploaded images outside direct web access and serve them only to authorized staff.
 *
 * - New files go under wp-content/uploads/optima-claims-private/...
 * - Apache: creates optima-claims-private/.htaccess with "Require all denied" on activation / first upload.
 * - Nginx/IIS: deny that location manually (see plugin readme or hosting docs).
 * - Admin UI uses admin-ajax.php with nonce + capability check.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Secure_Portal_Media {

	public const PRIVATE_SUBDIR = 'optima-claims-private';

	private static int $upload_dir_depth = 0;

	public static function init(): void {
		add_action( 'wp_ajax_optima_claims_view_attachment', array( __CLASS__, 'ajax_serve_attachment' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_public_attachment_pages_for_claim_media' ), 1 );
	}

	/**
	 * Wrap portal uploads so wp_handle_upload uses a protected subdirectory.
	 */
	public static function begin_portal_upload_directory(): void {
		++self::$upload_dir_depth;
		if ( self::$upload_dir_depth === 1 ) {
			add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ), 99 );
			self::install_htaccess();
		}
	}

	public static function end_portal_upload_directory(): void {
		--self::$upload_dir_depth;
		if ( self::$upload_dir_depth === 0 ) {
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ), 99 );
		}
		if ( self::$upload_dir_depth < 0 ) {
			self::$upload_dir_depth = 0;
		}
	}

	/**
	 * @param array<string, string> $uploads {@see wp_upload_dir}.
	 * @return array<string, string>
	 */
	public static function filter_upload_dir( array $uploads ): array {
		$uploads['subdir'] = '/' . self::PRIVATE_SUBDIR . $uploads['subdir'];
		$uploads['path']    = $uploads['basedir'] . $uploads['subdir'];
		$uploads['url']     = $uploads['baseurl'] . $uploads['subdir'];
		return $uploads;
	}

	/**
	 * Create optima-claims-private/.htaccess to block direct HTTP access (Apache).
	 */
	public static function install_htaccess(): void {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . self::PRIVATE_SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		$htaccess = $dir . '/.htaccess';
		if ( is_file( $htaccess ) ) {
			$existing = (string) file_get_contents( $htaccess );
			if ( strpos( $existing, 'Optima Claims private uploads' ) !== false ) {
				return;
			}
		}
		$rules = "# Optima Claims private uploads\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
		file_put_contents( $htaccess, $rules, LOCK_EX );
	}

	/**
	 * URL for staff to view/download an attachment file (use in img src and thickbox href).
	 */
	public static function get_view_url( int $attachment_id, string $size = 'full' ): string {
		$size = sanitize_key( $size );
		if ( $size === '' ) {
			$size = 'full';
		}
		return add_query_arg(
			array(
				'action'   => 'optima_claims_view_attachment',
				'id'       => $attachment_id,
				'size'     => $size,
				'_wpnonce' => wp_create_nonce( 'optima_claims_view_attachment_' . $attachment_id ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * @param int $attachment_id
	 * @return string|null Absolute filesystem path, or null.
	 */
	public static function get_resolved_file_path( int $attachment_id, string $size = 'full' ): ?string {
		$full = get_attached_file( $attachment_id );
		if ( ! $full || ! is_string( $full ) || ! is_readable( $full ) ) {
			return null;
		}
		if ( $size === 'full' ) {
			return $full;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['sizes'][ $size ]['file'] ) ) {
			return $full;
		}
		$intermediate = path_join( dirname( $full ), $meta['sizes'][ $size ]['file'] );
		return is_readable( $intermediate ) ? $intermediate : $full;
	}

	public static function ajax_serve_attachment(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$attachment_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $attachment_id <= 0 ) {
			wp_die( '', '', array( 'response' => 400 ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'optima_claims_view_attachment_' . $attachment_id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$size = isset( $_GET['size'] ) ? sanitize_key( (string) wp_unslash( $_GET['size'] ) ) : 'full';
		if ( $size === '' ) {
			$size = 'full';
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || $post->post_type !== 'attachment' ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}
		$parent_id = (int) $post->post_parent;
		if ( $parent_id <= 0 || get_post_type( $parent_id ) !== Optima_Claims_Post_Type::POST_TYPE ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'edit_post', $parent_id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		if ( strpos( (string) $post->post_mime_type, 'image/' ) !== 0 ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		$path = self::get_resolved_file_path( $attachment_id, $size );
		if ( ! $path ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}

		$mime = $post->post_mime_type;
		if ( ! is_string( $mime ) || $mime === '' ) {
			$mime = 'application/octet-stream';
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $path ) ) . '"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary output
		readfile( $path );
		exit;
	}

	/**
	 * Block /?attachment_id= for media attached to claims (prevents attachment permalink leakage).
	 */
	public static function block_public_attachment_pages_for_claim_media(): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_attachment() ) {
			return;
		}
		$obj = get_queried_object();
		if ( ! $obj instanceof WP_Post ) {
			return;
		}
		$parent = (int) $obj->post_parent;
		if ( $parent <= 0 || get_post_type( $parent ) !== Optima_Claims_Post_Type::POST_TYPE ) {
			return;
		}
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'Not found.', 'optima-claims-sync' ),
			esc_html__( 'Not found', 'optima-claims-sync' ),
			array( 'response' => 404 )
		);
	}
}
