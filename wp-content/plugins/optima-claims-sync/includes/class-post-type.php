<?php
/**
 * Registers the claim custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Post_Type {

	public const POST_TYPE = 'claim';

	public static function register(): void {
		$labels = array(
			'name'                  => _x( 'Claims', 'Post type general name', 'optima-claims-sync' ),
			'singular_name'         => _x( 'Claim', 'Post type singular name', 'optima-claims-sync' ),
			'menu_name'             => _x( 'Claims', 'Admin Menu text', 'optima-claims-sync' ),
			'name_admin_bar'        => _x( 'Claim', 'Add New on Toolbar', 'optima-claims-sync' ),
			'add_new'               => __( 'Add New', 'optima-claims-sync' ),
			'add_new_item'          => __( 'Add New Claim', 'optima-claims-sync' ),
			'new_item'              => __( 'New Claim', 'optima-claims-sync' ),
			'edit_item'             => __( 'Edit Claim', 'optima-claims-sync' ),
			'view_item'             => __( 'View Claim', 'optima-claims-sync' ),
			'all_items'             => __( 'All Claims', 'optima-claims-sync' ),
			'search_items'          => __( 'Search Claims', 'optima-claims-sync' ),
			'not_found'             => __( 'No claims found.', 'optima-claims-sync' ),
			'not_found_in_trash'    => __( 'No claims found in Trash.', 'optima-claims-sync' ),
		);

		$args = array(
			'labels'                => $labels,
			'public'                => false,
			'publicly_queryable'    => false,
			'exclude_from_search'   => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_nav_menus'     => false,
			'show_in_admin_bar'     => true,
			'query_var'             => false,
			'rewrite'               => false,
			'capability_type'       => 'post',
			'has_archive'           => false,
			'hierarchical'          => false,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-clipboard',
			'supports'              => array( 'title' ),
			'show_in_rest'          => false,
		);

		register_post_type( self::POST_TYPE, $args );

		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'block_public_frontend_access' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots_noindex_claim_templates' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'exclude_from_sitemaps' ) );
	}

	/**
	 * Ensure the block editor is never used for claims (content comes from API meta).
	 *
	 * @param bool   $use_block_editor Whether the post type can use the block editor.
	 * @param string $post_type        Post type slug.
	 */
	public static function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		if ( $post_type === self::POST_TYPE ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Block singular/archive views if something still resolves a claim on the front end.
	 */
	public static function block_public_frontend_access(): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_singular( self::POST_TYPE ) && ! is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'This content is not available.', 'optima-claims-sync' ),
			esc_html__( 'Not found', 'optima-claims-sync' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public static function robots_noindex_claim_templates( array $robots ): array {
		if ( is_admin() ) {
			return $robots;
		}
		if ( is_singular( self::POST_TYPE ) || is_post_type_archive( self::POST_TYPE ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types Post types keyed by name (core sitemaps).
	 * @return array<string, \WP_Post_Type>
	 */
	public static function exclude_from_sitemaps( array $post_types ): array {
		unset( $post_types[ self::POST_TYPE ] );
		return $post_types;
	}
}
