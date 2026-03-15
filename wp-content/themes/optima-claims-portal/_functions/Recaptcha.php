<?php

// Add the Google reCAPTCHA v2 script to the head of the website
// function add_recaptcha_v2_script() {
//     echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
// }
// add_action('wp_head', 'add_recaptcha_v2_script');

// // Add the reCAPTCHA widget to the HTML Form
// function add_recaptcha_v2_to_html_form($markup) {
//     $recaptcha_html = '<div class="g-recaptcha" data-sitekey="SITEKEY"></div>';
//     $markup .= $recaptcha_html; // Append the reCAPTCHA widget before the closing form tag
//     return $markup;
// }
// add_filter('hf_form_markup', 'add_recaptcha_v2_to_html_form');

// // Validate the reCAPTCHA response during form submission
// function validate_recaptcha_v2($error_code, $form, $data) {
//     // Check if the reCAPTCHA response is present
//     $response = $data['g-recaptcha-response'] ?? '';

//     if (empty($response)) {
//         $error_code = 'recaptcha_failed';
//     } else {
//         // Validate the response with Google reCAPTCHA API
//         $secret_key = 'SECRET_KEY'; // Replace with your reCAPTCHA secret key
//         $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
//             'body' => [
//                 'secret' => $secret_key,
//                 'response' => $response,
//                 'remoteip' => $_SERVER['REMOTE_ADDR']
//             ]
//         ]);

//         // Handle API response
//         if (is_wp_error($verify_response)) {
//             $error_code = 'recaptcha_failed';
//         } else {
//             $result = json_decode(wp_remote_retrieve_body($verify_response), true);

//             if (empty($result['success']) || !$result['success']) {
//                 $error_code = 'recaptcha_failed';
//             }
//         }
//     }

//     return $error_code;
// }
// add_filter('hf_validate_form', 'validate_recaptcha_v2', 10, 3);

// // Display error message if reCAPTCHA validation fails
// function display_recaptcha_v2_error_message($message) {
//     return 'CAPTCHA verification failed. Please try again.';
// }
// add_filter('hf_form_message_recaptcha_failed', 'display_recaptcha_v2_error_message');