<?php
// -----------------------------------------------------------------------
// Auto populate button icon options
// -----------------------------------------------------------------------
// // Only works for secure sites or local/demo sites

// // Function to check if SSL certificate is installed
// function has_ssl( $domain ) {
//     $ssl_check = @fsockopen( 'ssl://' . $domain, 443, $errno, $errstr, 30 );
//     $res = !! $ssl_check;
//     if ( $ssl_check ) { fclose( $ssl_check ); }
//     return $res;
// }

// // Function to check if it's a demo site
// function is_demo($domain) {
//     $demo_notifiers = array(".vm",".local",".test",".adtrak.agency");
//     foreach($demo_notifiers as $a) {
//         if (stripos($domain,$a) !== false) return true;
//     }
// }


// // Function to auto populate icon choices in ACF fields with the icons available in the sprite
// if(is_demo($_SERVER['SERVER_NAME'])) {
//     delete_transient( 'acf_icon_choices' );
// }

// function acf_load_icon_field_choices( $field ) {
//     // Key for storing icon choices
//     $transient_key = 'acf_icon_choices';

//     // Path to the cached icons file
//     $cache_file = get_stylesheet_directory() . '/_assets/images/icons-cache.json';
    
//     // Path to the icons sprite file
//     $svgFilePath = get_stylesheet_directory() . '/_assets/images/icons-sprite.svg';

//     // Get the cached icon names from transient
//     $iconNames = get_transient($transient_key);

//     // If transient is not set, we need to regenerate the icon names and update the cache
//     if ($iconNames === false) {
//         // Read the SVG file content to extract icon names
//         $svgContent = file_get_contents($svgFilePath);

//         if ($svgContent === false) {
//             // If the SVG file is not found, return empty choices
//             $field['choices'] = array();
//             return $field;
//         }

//         // Extract icon names using regex
//         preg_match_all('/id="icon-([^"]+)"/', $svgContent, $matches);

//         $iconNames = array();
//         foreach ($matches[1] as $icon) {
//             $iconNames[$icon] = ucwords(str_replace("-", " ", $icon)); // Convert icon names to a more readable format
//         }

//         // Sort the icons alphabetically
//         asort($iconNames);

//         // Save the icon names in the transient for 24 hours
//         set_transient($transient_key, $iconNames, DAY_IN_SECONDS);

//         // Save the icon names to the icons-cache.json file (only if the transient is not set)
//         $myfile = fopen($cache_file, "w") or die("Unable to open file!");
//         fwrite($myfile, json_encode($iconNames)); // Encode the array as JSON
//         fclose($myfile);
//     }

//     // If the transient is set, we don't need to regenerate icons or update the cache file
//     // Instead, just read from the cache file if available
//     else {
//         // Read the cached content from the icons-cache.json file
//         $cached_file_content = file_get_contents($cache_file);

//         // Decode the cached content back to an array
//         $iconNames = json_decode($cached_file_content, true);

//         // If for any reason the cache is empty or invalid, return an empty array
//         if (empty($iconNames)) {
//             $iconNames = array();
//         }
//     }

//     // Assign the choices from the cached icon names
//     $field['choices'] = $iconNames;
//     return $field;
// }

// add_filter('acf/load_field/name=icon', 'acf_load_icon_field_choices');

// -----------------------------------------------------------------------
// Auto populate colour options in ACF fields - name your select field 'colour'
// -----------------------------------------------------------------------
// function acf_load_colour_field_choices( $field ) {

//     // Reset choices
//     $field['choices'] = array();
//     $field['choices'][ 'primary' ] = 'primary';
//     $field['choices'][ 'primary-light' ] = 'primary-light';
//     $field['choices'][ 'primary-dark' ] = 'primary-dark';
//     $field['choices'][ 'secondary' ] = 'secondary';
//     $field['choices'][ 'secondary-light' ] = 'secondary-light';
//     $field['choices'][ 'secondary-dark' ] = 'secondary-dark';

//     // Return the field
//     return $field;
    
// }
// add_filter('acf/load_field/name=colour', 'acf_load_colour_field_choices');


// -----------------------------------------------------------------------
// Auto populate an ACF field example
// -----------------------------------------------------------------------
// function acf_load_heading_level_field_choices( $field ) {

//     // Reset choices
//     $field['choices'] = array();
//     $field['choices'][ 'h1' ] = 'h1';
//     $field['choices'][ 'h2' ] = 'h2';
//     $field['choices'][ 'h3' ] = 'h3';
//     $field['choices'][ 'h4' ] = 'h4';
//     $field['choices'][ 'h5' ] = 'h5';
//     $field['choices'][ 'p' ] = 'p';

//     // Return the field
//     return $field;
    
// }
// add_filter('acf/load_field/name=heading_level', 'acf_load_heading_level_field_choices');