<?php
/*
Plugin Name: HTML Forms
Plugin URI: https://htmlformsplugin.com/
Description: A simpler, faster, and smarter WordPress forms plugin.
Version: 1.6.3
Author: HTML Forms
Author URI: https://htmlformsplugin.com/
License: GPL v3
Text Domain: html-forms

HTML Forms
Copyright (C) 2017-2024, Link Software LLC <support@linksoftwarellc.com>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace HTML_Forms;

function _bootstrap() {
    $settings = hf_get_settings();

    $forms = new Forms( __FILE__, $settings );
    $forms->hook();

    if( is_admin() ) {
        if( ! \defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            $admin = new Admin\Admin( __FILE__ );
            $admin->hook();
        }

        $gdpr = new Admin\GDPR();
        $gdpr->hook();

        if( function_exists('hcaptcha') ) {
            $hcaptcha = new Admin\Hcaptcha();
            $hcaptcha->hook();
        }
    }

    // Initialize Google reCAPTCHA v3 (needs to run on both frontend and backend)
    if( \class_exists( 'HTML_Forms\Admin\Recaptcha' ) ) {
        $recaptcha = new Admin\Recaptcha();
        $recaptcha->hook();
    }
}

function _hf_actions() {
    $email_action = new Actions\Email();
    $email_action->hook();

    if( \class_exists( 'MC4WP_MailChimp' ) ) {
        $mailchimp_action = new Actions\MailChimp();
        $mailchimp_action->hook();
    }
}

define('HTML_FORMS_VERSION', '1.6.3');

if( ! function_exists( 'hf_get_form' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', 'HTML_Forms\\_bootstrap', 10 );
add_action( 'init', 'HTML_Forms\\_hf_actions', 10 );

register_activation_hook( __FILE__, '_hf_on_plugin_activation');

// add cap to site admin after being added to blog
add_action('add_user_to_blog', '_hf_on_add_user_to_blog', 10, 3);

// install db table for newly added sites
add_action('wp_insert_site', '_hf_on_wp_insert_site');
