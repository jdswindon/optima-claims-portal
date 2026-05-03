<?php
/**
 * Context and messages for the Lookup ACF block (claim search + upload).
 */

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		$path = get_stylesheet_directory() . '/_assets/js/standalone/claim-lookup-rows.js';
		if ( ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'optima-claim-lookup-rows',
			get_stylesheet_directory_uri() . '/_assets/js/standalone/claim-lookup-rows.js',
			array(),
			(string) filemtime( $path ),
			true
		);
		wp_localize_script(
			'optima-claim-lookup-rows',
			'optimaClaimLookupRows',
			array(
				'rowLabel'   => __( 'Image', 'adtrak' ),
				'removeAria' => __( 'Remove this image slot', 'adtrak' ),
				'maxRows'    => 15,
			)
		);
	},
	20
);

add_filter( 'timber/acf-gutenberg-blocks-data/lookup', function ( array $context ): array {
	$session = isset( $_GET['claim_session'] ) ? sanitize_text_field( wp_unslash( $_GET['claim_session'] ) ) : '';

	$context['claim_session']        = $session;
	$context['claim_upload_ready']   = false;
	$context['claim_upload_nonce']   = '';
	$context['lookup_nonce']         = wp_create_nonce( 'optima_claim_lookup' );
	$context['lookup_error_message'] = '';
	$context['upload_error_message'] = '';
	$upload_ok                         = isset( $_GET['upload_ok'] ) ? max( 0, (int) $_GET['upload_ok'] ) : 0;
	$context['upload_success_count']   = $upload_ok;
	$context['upload_success_message'] = $upload_ok > 0
		? sprintf(
			/* translators: %d: number of images uploaded */
			_n( 'Thank you. We received %d image.', 'Thank you. We received %d images.', $upload_ok, 'adtrak' ),
			$upload_ok
		)
		: '';
	$context['claim_lookup_enabled']   = class_exists( 'Optima_Claims_Frontend_Lookup' );

	$lookup_codes = array(
		'no_match'        => __( 'No claim matched those details. Check your claimable ID and registration number, then try again.', 'adtrak' ),
		'session_expired' => __( 'Your session has expired. Please search for your claim again.', 'adtrak' ),
	);
	$upload_codes   = array(
		'no_file'               => __( 'Please choose at least one image to upload.', 'adtrak' ),
		'upload_failed'         => __( 'We could not save your images. Check that each file is a valid image (JPEG, PNG, GIF, or WebP) and try again.', 'adtrak' ),
		'description_required'  => __( 'Each image needs a short description. Please fill in the description for every file you upload.', 'adtrak' ),
	);

	if ( isset( $_GET['lookup_err'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_GET['lookup_err'] ) );
		if ( isset( $lookup_codes[ $code ] ) ) {
			$context['lookup_error_message'] = $lookup_codes[ $code ];
		}
	}
	if ( isset( $_GET['upload_err'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_GET['upload_err'] ) );
		if ( isset( $upload_codes[ $code ] ) ) {
			$context['upload_error_message'] = $upload_codes[ $code ];
		}
	}

	if ( $session && class_exists( 'Optima_Claims_Frontend_Lookup' ) && Optima_Claims_Frontend_Lookup::is_valid_session( $session ) ) {
		$context['claim_upload_ready'] = true;
		$context['claim_upload_nonce'] = wp_create_nonce( 'optima_claim_upload_' . $session );
	}

	return $context;
} );
