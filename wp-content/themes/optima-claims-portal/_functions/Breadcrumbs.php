<?php 
/**
 * Override Yoast SEO Breadcrumb Trail
 * -----------------------------------------------------------------------------------
 */

// add_filter( 'wpseo_breadcrumb_links', 'override_yoast_breadcrumb_trail' );

// function override_yoast_breadcrumb_trail( $links ) {
//     global $post;

//     if ( is_singular( '[ POST TYPE HERE ]' ) ) {
//         $breadcrumb[] = array(
//             'url' => get_permalink( [ PAGE ID HERE ] ),
//             'text' => '[ PAGE TITLE HERE ]',
//         );

//         array_splice( $links, 1, -2, $breadcrumb );
//     }

//     return $links;
// }