<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'astrid/v1', '/register', array(
		'methods' => 'POST', 'callback' => 'astrid_handle_registration', 'permission_callback' => '__return_true',
	) );
} );

/** Public, cache-safe configuration token. It authenticates configuration, not the visitor. */
function astrid_verify_context( $token ) {
	if ( ! is_string( $token ) || strlen( $token ) > 262144 ) { return false; }
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return false; }
	$decoded = base64_decode( $parts[0], true );
	$context = false !== $decoded ? json_decode( $decoded, true ) : null;
	return is_array( $context ) && isset( $context['activity'], $context['options'], $context['block'], $context['post_id'] ) && is_array( $context['options'] ) ? $context : false;
}

function astrid_request_text( $request, $key, $textarea = false ) {
	$value = $request->get_param( $key );
	if ( ! is_string( $value ) ) { return ''; }
	return $textarea ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
}

function astrid_handle_registration( $request ) {
	$context = astrid_verify_context( $request->get_param( 'context' ) );
	if ( ! $context ) { return new WP_Error( 'astrid_context', __( 'This form is no longer valid. Reload the page and try again.', 'astrid' ), array( 'status' => 400 ) ); }
	if ( '' !== astrid_request_text( $request, 'website' ) ) { return rest_ensure_response( array( 'success' => true ) ); }
	$post = get_post( (int) $context['post_id'] );
	if ( $post && ( ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) ) || ( post_password_required( $post ) && ! current_user_can( 'edit_post', $post->ID ) ) ) ) {
		return new WP_Error( 'astrid_unavailable', __( 'This activity is not available for registration.', 'astrid' ), array( 'status' => 403 ) );
	}
	if ( $context['post_id'] && ! $post ) { return new WP_Error( 'astrid_unavailable', __( 'This activity is not available for registration.', 'astrid' ), array( 'status' => 404 ) ); }
	$name = astrid_request_text( $request, 'name' );
	$phone = astrid_request_text( $request, 'phone' );
	$email_raw = $request->get_param( 'email' );
	$email = is_string( $email_raw ) ? trim( $email_raw ) : '';
	$message = astrid_request_text( $request, 'message', true );
	if ( '' === $name || '' === $phone || ! is_email( $email ) || '1' !== $request->get_param( 'consent' ) || strlen( $name ) > 800 || strlen( $phone ) > 320 || strlen( $email ) > 254 || strlen( $message ) > 20000 ) {
		return new WP_Error( 'astrid_fields', __( 'Please complete the required fields and accept the consent checkbox.', 'astrid' ), array( 'status' => 400 ) );
	}
	$email = sanitize_email( $email );
	$option = '';
	if ( ! empty( $context['multiple'] ) ) {
		$index = $request->get_param( 'option' );
		if ( ! is_string( $index ) || ! ctype_digit( $index ) || ! array_key_exists( (int) $index, $context['options'] ) ) {
			return new WP_Error( 'astrid_option', __( 'Please choose an option before registering.', 'astrid' ), array( 'status' => 400 ) );
		}
		$option = $context['options'][ (int) $index ];
	}
	// Prevent immediate identical retries from sending the same registration twice.
	$fingerprint = hash_hmac( 'sha256', wp_json_encode( array( $context, $name, $phone, $email, $message, $option ) ), wp_salt( 'nonce' ) );
	if ( get_transient( 'astrid_sent_' . $fingerprint ) ) { return rest_ensure_response( array( 'success' => true ) ); }
	// A short-lived salted key provides basic throttling without storing raw IP addresses.
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
	$key = 'astrid_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	$count = (int) get_transient( $key );
	if ( $count >= 20 ) { return new WP_Error( 'astrid_rate', __( 'Too many attempts. Please wait a minute and try again.', 'astrid' ), array( 'status' => 429 ) ); }
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	$settings = astrid_get_settings();
	$subject = sprintf( __( 'Registration: %s', 'astrid' ), $context['activity'] );
	if ( '' !== $option ) { $subject .= ' — ' . $option; }
	$consent_copy = str_replace( array( '{activity}', '{option}', '{terms}', '{privacy}' ), array( $context['activity'], $option, $settings['terms_label'] . ' (' . $settings['agb_url'] . ')', $settings['privacy_label'] . ' (' . $settings['datenschutz_url'] . ')' ), $settings['consent_text'] );
	$body = sprintf( "Activity: %s\nOption: %s\nSource page: %s\nURL: %s\nBlock: %s (%s)\n\nName: %s\nPhone: %s\nEmail: %s\n\nMessage:\n%s\n\nConsent confirmed: %s\nReceived (UTC): %s\n",
		$context['activity'], $option ?: '—', $context['source_title'], $context['url'], $context['block'], $context['instance'],
		$name, $phone, $email, $message ?: '—', $consent_copy, gmdate( 'Y-m-d H:i:s' )
	);
	// Email-only workflow: no registration records or participant confirmation emails.
	$sent = wp_mail( $settings['recipient_email'], $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email ) );
	if ( ! $sent ) { return new WP_Error( 'astrid_mail', __( 'The email could not be sent. Please try again later.', 'astrid' ), array( 'status' => 500 ) ); }
	set_transient( 'astrid_sent_' . $fingerprint, 1, 5 * MINUTE_IN_SECONDS );
	return rest_ensure_response( array( 'success' => true ) );
}
