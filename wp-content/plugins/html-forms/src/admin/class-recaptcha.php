<?php

namespace HTML_Forms\Admin;

class Recaptcha {
    private $settings;
    private static $scripts_enqueued = false;
    
    public function __construct() {
        if ( function_exists( 'get_option' ) ) {
            $this->settings = hf_get_settings();
        } else {
            $this->settings = array(
                'google_recaptcha' => array(
                    'site_key' => '',
                    'secret_key' => '',
                )
            );
        }
    }
    
    public function hook() {
        if ( is_admin() ) {
            add_action( 'hf_admin_output_form_messages', array( $this, 'output_recaptcha_message_fields' ) );
        }
        
        if ( $this->is_configured() ) {
            add_filter( 'hf_ignored_field_names', array( $this, 'ignored_fields' ) );
            add_filter( 'hf_validate_form', array( $this, 'validate_recaptcha' ), 10, 3 );
            add_filter( 'hf_form_markup', array( $this, 'add_recaptcha_to_form' ), 10, 2 );
            add_filter( 'hf_form_html', array( $this, 'enqueue_recaptcha_on_form_render' ), 10, 2 );
            
            add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
        }
    }
    
    /**
     * Check if reCAPTCHA is properly configured
     * 
     * @return bool
     */
    private function is_configured() {
        $site_key = ! empty( $this->settings['google_recaptcha']['site_key'] );
        $secret_key = ! empty( $this->settings['google_recaptcha']['secret_key'] );
        $configured = $site_key && $secret_key;
        
        return $configured;
    }
    
    /**
     * Add reCAPTCHA response fields to ignored fields list
     * 
     * @param array $ignored_fields
     * @return array
     */
    public function ignored_fields( $ignored_fields ) {
        $ignored_fields[] = 'g-recaptcha-response';
        $ignored_fields[] = 'g-recaptcha-failed';

        return $ignored_fields;
    }
    
    /**
     * Enqueue reCAPTCHA scripts when a form is being rendered
     * 
     * @param string $html
     * @param \HTML_Forms\Form $form
     * @return string
     */
    public function enqueue_recaptcha_on_form_render( $html, $form ) {
        // Only enqueue scripts once per page load
        if ( self::$scripts_enqueued ) {
            return $html;
        }
        
        // Skip if we're in admin area
        if ( is_admin() ) {
            return $html;
        }
        
        $this->enqueue_recaptcha_script();
        self::$scripts_enqueued = true;
        
        return $html;
    }
    
    /**
     * Enqueue Google reCAPTCHA v3 script
     */
    public function enqueue_recaptcha_script() {
        $site_key = $this->settings['google_recaptcha']['site_key'];
        
        // Enqueue Google reCAPTCHA v3 API
        wp_enqueue_script(
            'google-recaptcha-v3',
            "https://www.google.com/recaptcha/api.js?render={$site_key}",
            array(),
            null,
            true
        );
        
        // Enqueue reCAPTCHA integration script
        wp_enqueue_script(
            'html-forms-recaptcha',
            plugins_url( 'assets/js/recaptcha.js', $this->get_plugin_file() ),
            array( 'google-recaptcha-v3', 'html-forms' ),
            HTML_FORMS_VERSION,
            true
        );
        
        wp_localize_script( 'html-forms-recaptcha', 'hf_recaptcha', array(
            'site_key' => $site_key,
        ) );
    }
    
    /**
     * Get the plugin file path
     * 
     * @return string
     */
    private function get_plugin_file() {
        return dirname( dirname( __DIR__ ) ) . '/html-forms.php';
    }
    
    /**
     * Add reCAPTCHA comment to form markup
     * 
     * @param string $markup
     * @param \HTML_Forms\Form $form
     * @return string
     */
    public function add_recaptcha_to_form( $markup, $form ) {
        $recaptcha_notice = "\n<!-- Google reCAPTCHA v3 active on this form -->\n";
        
        return $recaptcha_notice . $markup;
    }
    
    /**
     * Validate reCAPTCHA response
     * 
     * @param string $error_code
     * @param \HTML_Forms\Form $form
     * @param array $data
     * @return string
     */
    public function validate_recaptcha( $error_code, $form, $data ) {
        // If there's already an error, don't proceed
        if ( ! empty( $error_code ) ) {
            return $error_code;
        }
        
        // Get reCAPTCHA response from form data
        $recaptcha_response = isset( $data['g-recaptcha-response'] ) ? $data['g-recaptcha-response'] : '';
        $recaptcha_failed = isset( $data['g-recaptcha-failed'] ) ? $data['g-recaptcha-failed'] : '';
        
        // Check if reCAPTCHA execution failed on the client side
        if ( ! empty( $recaptcha_failed ) ) {
            $this->log_debug( 'reCAPTCHA execution failed on client side', $form );
            return 'recaptcha_failed';
        }
        
        if ( empty( $recaptcha_response ) ) {
            $this->log_debug( 'reCAPTCHA token missing from form submission', $form );
            return 'recaptcha_failed';
        }
        
        // Basic token format validation (should be a long string)
        if ( strlen( $recaptcha_response ) < 20 ) {
            $this->log_debug( 'reCAPTCHA token appears to be invalid (too short): ' . $recaptcha_response, $form );
            return 'recaptcha_failed';
        }
        
        // Verify reCAPTCHA with Google
        $verification_result = $this->verify_recaptcha( $recaptcha_response );
        
        if ( ! $verification_result['success'] ) {
            $error_codes = isset( $verification_result['error-codes'] ) ? implode( ', ', $verification_result['error-codes'] ) : 'unknown';
            $this->log_debug( 'reCAPTCHA verification failed with error codes: ' . $error_codes, $form );
            
            // Check for specific error codes that indicate token reuse or timeout
            if ( isset( $verification_result['error-codes'] ) && is_array( $verification_result['error-codes'] ) ) {
                $error_codes_array = $verification_result['error-codes'];
                if ( in_array( 'timeout-or-duplicate', $error_codes_array ) || in_array( 'invalid-input-response', $error_codes_array ) ) {
                    $this->log_debug( 'reCAPTCHA token was reused or expired', $form );
                }
            }
            
            return 'recaptcha_failed';
        }
        
        // Check score
        $min_score = apply_filters( 'hf_recaptcha_min_score', 0.5 );
        
        if ( isset( $verification_result['score'] ) && $verification_result['score'] < $min_score ) {
            $this->log_debug( sprintf( 'reCAPTCHA score %.2f below minimum %.2f', $verification_result['score'], $min_score ), $form );
            return 'recaptcha_low_score';
        }
        
        return $error_code;
    }
    
    /**
     * Verify reCAPTCHA response with Google's API
     * 
     * @param string $response
     * @return array
     */
    private function verify_recaptcha( $response ) {
        $secret_key = $this->settings['google_recaptcha']['secret_key'];
        $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret'   => $secret_key,
            'response' => $response,
            'remoteip' => $remote_ip,
        );
        
        $response = wp_remote_post( $url, array(
            'body' => $data,
            'timeout' => 10,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => 'network_error' );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );
        
        if ( ! $result ) {
            return array( 'success' => false, 'error' => 'invalid_response' );
        }
        
        return $result;
    }
    
    /**
     * Log debug information if WP_DEBUG is enabled
     * 
     * @param string $message
     * @param \HTML_Forms\Form $form
     */
    private function log_debug( $message, $form = null ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[HTML Forms reCAPTCHA] ' . $message;
            if ( $form ) {
                $log_message .= sprintf( ' (Form: "%s", ID: %d)', $form->title, $form->ID );
            }
            error_log( $log_message );
        }
    }
    
    /**
     * Output reCAPTCHA message fields in the Messages tab
     * 
     * @param \HTML_Forms\Form $form
     */
    public function output_recaptcha_message_fields( $form ) {
        // Only show these fields if reCAPTCHA is configured
        if ( ! $this->is_configured() ) {
            return;
        }
        ?>
        <tr valign="top">
            <th scope="row" colspan="2" class="hf-settings-header"><?php echo __( 'Google reCAPTCHA v3', 'html-forms' ); ?></th>
        </tr>
        
        <tr valign="top">
            <th scope="row"><label for="hf_form_recaptcha_failed"><?php _e( 'reCAPTCHA Failed', 'html-forms' ); ?></label></th>
            <td>
                <input type="text" class="widefat" id="hf_form_recaptcha_failed" name="form[messages][recaptcha_failed]" value="<?php echo esc_attr( $form->messages['recaptcha_failed'] ); ?>" required />
                <p class="description"><?php _e( 'The text that shows when reCAPTCHA verification fails.', 'html-forms' ); ?></p>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row"><label for="hf_form_recaptcha_low_score"><?php _e( 'reCAPTCHA Low Score', 'html-forms' ); ?></label></th>
            <td>
                <input type="text" class="widefat" id="hf_form_recaptcha_low_score" name="form[messages][recaptcha_low_score]" value="<?php echo esc_attr( $form->messages['recaptcha_low_score'] ); ?>" required />
                <p class="description"><?php _e( 'The text that shows when a submission appears to be spam based on reCAPTCHA score.', 'html-forms' ); ?></p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Show admin notice when reCAPTCHA is active
     */
    public function show_admin_notice() {
        // Only show on HTML Forms pages, not every admin page
        if ( empty( $_GET['page'] ) || $_GET['page'] !== 'html-forms' || empty( $_GET['form_id'] ) ) {
            return;
        }
        
        echo '<div class="notice notice-success" data-notice="hf-recaptcha">';
        echo '<p><span class="dashicons dashicons-shield" style="color:#46b450;"></span> <strong>' . __( 'Google reCAPTCHA v3 is enabled on this form.', 'html-forms' ) . '</strong> ';
        echo __( 'Submissions will be automatically protected from spam and abuse.', 'html-forms' );
        echo ' <a href="' . admin_url( 'admin.php?page=html-forms-settings' ) . '">' . __( 'View settings', 'html-forms' ) . '</a>.</p>';
        echo '</div>';
    }
}
