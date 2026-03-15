<?php

/*
---------------------------------------------------------------------------------
Adtrak reset resets some key elements that are standard across all Adtrak websites
---------------------------------------------------------------------------------
*/

// Disable WordPress built-in lazy loading
add_filter( 'wp_lazy_loading_enabled', '__return_false' );

// Set up Options Page

if( function_exists('acf_add_options_page') ) {
	
	acf_add_options_page(array(
    'page_title' 	=> 'Site Options',
    'menu_title' 	=> 'Site Options',
    'menu_slug' 	=> 'site-options',
    'position' 		=> 75,
    'capability' 	=> 'update_core',
    'icon_url' 		=> 'dashicons-hammer',
    'redirect' 		=> false
	));
	
};

// Setup the theme, register navs here, adds HTML5 support still
add_action('after_setup_theme', function () {
    // Hide the admin bar.
    show_admin_bar(false);

    // Enable plugins to manage the document title
    add_theme_support('title-tag');

    // Enable post thumbnails
    add_theme_support('post-thumbnails');

    // Enable HTML5 markup support
    add_theme_support('html5', ['caption', 'comment-form', 'comment-list', 'gallery', 'search-form']);

});

// Allow SVGs to be uploaded in media
add_filter('upload_mimes', function($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

// Completely remove comments
add_action('admin_init', function () {
    // Redirect any user trying to access comments page
    global $pagenow;
    
    if ($pagenow === 'edit-comments.php') {
        wp_redirect(admin_url());
        exit;
    }

    // Remove comments metabox from dashboard
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

    // Disable support for comments and trackbacks in post types
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

// Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove comments page in menu
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

// Remove comments links from admin bar
add_action('init', function () {
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
});

//  Filters the page title appropriately depending on the current page. This will 90% of the time be overwritten by Yoast, but we have this here just incase.
add_filter('wp_title', function () {
	global $post;

	$name = get_bloginfo('name');
	$description = get_bloginfo('description');
	
	if($post === NULL){
		return $name;
	}

	if (is_front_page() || is_home()) {
		if ($description) {
			return sprintf('%s - %s', $name, $description);
		}
		return $name;
	}

	if (is_category()) {
		return sprintf('%s - %s', trim(single_cat_title('', false)), $name);
	}

	return sprintf('%s - %s', trim($post->post_title), $name);
});

//  Remove the WordPress version from RSS feeds
add_filter('the_generator', '__return_false');

/**
 * Wrap embedded media as suggested by Readability
 *
 * @link https://gist.github.com/965956
 * @link http://www.readability.com/publishers/guidelines#publisher
 */
add_filter('embed_oembed_html', function ($cache) {
	return '<div class="entry-content-asset">' . $cache . '</div>';
});

/**
 * Don't return the default description in the RSS feed if it hasn't been changed
 */
function remove_default_description($bloginfo) {
  $default_tagline = 'Just another WordPress site';
  return ($bloginfo === $default_tagline) ? '' : $bloginfo;
}
add_filter('get_bloginfo_rss', 'remove_default_description');

/**
 * Add no index to staging sites
 */
add_action('init', function() {
    if (strpos($_SERVER['SERVER_NAME'], '.adtrak.agency') !== false) {
		/* Uncomment Yoast robots post-site launch  */
        // add_filter('wpseo_robots', '__return_false');
        add_action('wp_head', function() {
            echo '<meta name="robots" content="noindex">';
            echo '<meta name="googlebot" content="noindex">';
        });
    }

    if (strpos($_SERVER['SERVER_NAME'], 'breeez.agency') !== false) {
      /* Uncomment Yoast robots post-site launch  */
      // add_filter('wpseo_robots', '__return_false');
        add_action('wp_head', function() {
            echo '<meta name="robots" content="noindex">';
            echo '<meta name="googlebot" content="noindex">';
        });
    }
});

/**
 * Prevent theme edit
 */
// define( 'DISALLOW_FILE_EDIT', true );
