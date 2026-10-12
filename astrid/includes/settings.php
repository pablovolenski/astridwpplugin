<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Site-language defaults stay dynamic until an administrator overrides a field. */
function astrid_defaults() {
	return array(
		'recipient_email' => get_option( 'admin_email' ),
		'agb_url' => '/agb',
		'datenschutz_url' => '/datenschutz',
		'display_mode' => 'popup',
		'button_label' => __( 'Register', 'astrid' ),
		'form_title' => __( 'Register for {activity}', 'astrid' ),
		'form_intro' => __( 'Leave your details to register for {activity}. We will get back to you soon.', 'astrid' ),
		'name_label' => __( 'Full name', 'astrid' ),
		'phone_label' => __( 'Phone number', 'astrid' ),
		'email_label' => __( 'Email address', 'astrid' ),
		'message_label' => __( 'Message (optional)', 'astrid' ),
		'option_label' => __( 'Choose an option', 'astrid' ),
		'option_placeholder' => __( 'Please select an option', 'astrid' ),
		'consent_text' => __( 'I agree to the processing of my data and accept the {terms} and {privacy}.', 'astrid' ),
		'terms_label' => __( 'terms and conditions', 'astrid' ),
		'privacy_label' => __( 'privacy policy', 'astrid' ),
		'submit_label' => __( 'Send registration', 'astrid' ),
		'success_message' => __( 'Thank you! Your registration for {activity} has been sent. We will contact you soon.', 'astrid' ),
		'button_style' => 'theme',
		'button_background' => '#e4177b',
		'button_color' => '#ffffff',
		'button_radius' => 4,
		'button_padding_y' => 12,
		'button_padding_x' => 22,
		'button_font_size' => 16,
	);
}

function astrid_get_settings() {
	// Use the site's locale, even when an administrator has a different user locale.
	$switched = get_locale() !== determine_locale() && switch_to_locale( get_locale() );
	$defaults = astrid_defaults();
	if ( $switched ) { restore_previous_locale(); }
	$saved = get_option( 'astrid_settings', array() );
	return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

function astrid_text_keys() {
	return array( 'button_label', 'form_title', 'form_intro', 'name_label', 'phone_label', 'email_label', 'message_label', 'option_label', 'option_placeholder', 'consent_text', 'terms_label', 'privacy_label', 'submit_label', 'success_message' );
}

function astrid_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$existing = astrid_get_settings();
	$out = array();
	if ( isset( $input['recipient_email'] ) ) {
		$email = is_string( $input['recipient_email'] ) ? sanitize_email( $input['recipient_email'] ) : '';
		$out['recipient_email'] = is_email( $email ) ? $email : $existing['recipient_email'];
		if ( ! is_email( $email ) ) { add_settings_error( 'astrid_settings', 'recipient', __( 'Enter a valid recipient email address. The previous address was kept.', 'astrid' ) ); }
	}
	foreach ( array( 'agb_url', 'datenschutz_url' ) as $key ) {
		if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) { $out[ $key ] = esc_url_raw( trim( $input[ $key ] ) ); }
	}
	foreach ( astrid_text_keys() as $key ) {
		// Blank means "follow the site-language default". Save only real overrides.
		if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && '' !== trim( $input[ $key ] ) ) {
			$out[ $key ] = sanitize_textarea_field( $input[ $key ] );
		}
	}
	$out['display_mode'] = isset( $input['display_mode'] ) && in_array( $input['display_mode'], array( 'popup', 'below', 'static' ), true ) ? $input['display_mode'] : $existing['display_mode'];
	$out['button_style'] = isset( $input['button_style'] ) && 'custom' === $input['button_style'] ? 'custom' : 'theme';
	foreach ( array( 'button_background', 'button_color' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) && is_string( $input[ $key ] ) && sanitize_hex_color( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : $existing[ $key ];
	}
	foreach ( array( 'button_radius' => 100, 'button_padding_y' => 80, 'button_padding_x' => 120, 'button_font_size' => 72 ) as $key => $max ) {
		$out[ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? min( $max, max( 'button_font_size' === $key ? 10 : 0, (int) $input[ $key ] ) ) : $existing[ $key ];
	}
	return $out;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Astrid', 'Astrid', 'manage_options', 'astrid', 'astrid_settings_page', 'dashicons-forms', 80 );
} );
add_action( 'admin_init', function () {
	register_setting( 'astrid', 'astrid_settings', array( 'type' => 'array', 'sanitize_callback' => 'astrid_sanitize_settings', 'default' => array() ) );
} );

function astrid_admin_input( $key, $label, $type = 'text', $help = '' ) {
	$settings = astrid_get_settings();
	$saved = get_option( 'astrid_settings', array() );
	$text = in_array( $key, astrid_text_keys(), true );
	$value = $text ? ( isset( $saved[ $key ] ) ? $saved[ $key ] : '' ) : $settings[ $key ];
	echo '<tr><th scope="row"><label for="astrid-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
	if ( 'textarea' === $type ) {
		echo '<textarea class="large-text" rows="3" id="astrid-' . esc_attr( $key ) . '" name="astrid_settings[' . esc_attr( $key ) . ']" placeholder="' . esc_attr( $settings[ $key ] ) . '">' . esc_textarea( $value ) . '</textarea>';
	} else {
		echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="astrid-' . esc_attr( $key ) . '" name="astrid_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . ( $text ? ' placeholder="' . esc_attr( $settings[ $key ] ) . '"' : '' ) . ( 'number' === $type ? ' min="0" max="120"' : '' ) . ' />';
	}
	if ( $help ) { echo '<p class="description">' . esc_html( $help ) . '</p>'; }
	echo '</td></tr>';
}

function astrid_admin_select( $key, $label, $choices ) {
	$settings = astrid_get_settings();
	echo '<tr><th scope="row"><label for="astrid-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><select id="astrid-' . esc_attr( $key ) . '" name="astrid_settings[' . esc_attr( $key ) . ']">';
	foreach ( $choices as $value => $text ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $text ) . '</option>'; }
	echo '</select></td></tr>';
}

function astrid_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	?>
	<div class="wrap">
		<h1>Astrid</h1>
		<p><?php esc_html_e( 'Place a Heading and an Astrid block inside a native Group. Astrid identifies the activity automatically from that heading.', 'astrid' ); ?></p>
		<?php settings_errors( 'astrid_settings' ); ?>
		<form action="options.php" method="post">
		<?php settings_fields( 'astrid' ); ?>
		<h2><?php esc_html_e( 'Delivery and display', 'astrid' ); ?></h2>
		<table class="form-table" role="presentation">
		<?php
		astrid_admin_input( 'recipient_email', __( 'Recipient email', 'astrid' ), 'email' );
		astrid_admin_input( 'agb_url', __( 'Terms page URL', 'astrid' ) );
		astrid_admin_input( 'datenschutz_url', __( 'Privacy page URL', 'astrid' ) );
		astrid_admin_select( 'display_mode', __( 'Default form display', 'astrid' ), array( 'popup' => __( 'Popup', 'astrid' ), 'below' => __( 'Reveal below the button', 'astrid' ), 'static' => __( 'Always visible', 'astrid' ) ) );
		?>
		</table>
		<h2><?php esc_html_e( 'Form text', 'astrid' ); ?></h2>
		<p><?php esc_html_e( 'Leave a text field blank to follow the WordPress site language. Use {activity} for the detected activity and {option} for the selected option. Consent text uses {terms} and {privacy} for links.', 'astrid' ); ?></p>
		<table class="form-table" role="presentation">
		<?php
		$labels = array(
			'button_label' => __( 'Registration button', 'astrid' ), 'form_title' => __( 'Form heading', 'astrid' ), 'form_intro' => __( 'Introduction', 'astrid' ),
			'name_label' => __( 'Name field', 'astrid' ), 'phone_label' => __( 'Phone field', 'astrid' ), 'email_label' => __( 'Email field', 'astrid' ),
			'message_label' => __( 'Message field', 'astrid' ), 'option_label' => __( 'Options label', 'astrid' ), 'option_placeholder' => __( 'Options placeholder', 'astrid' ),
			'consent_text' => __( 'Consent wording', 'astrid' ), 'terms_label' => __( 'Terms link text', 'astrid' ), 'privacy_label' => __( 'Privacy link text', 'astrid' ),
			'submit_label' => __( 'Submit button', 'astrid' ), 'success_message' => __( 'Thank-you message', 'astrid' ),
		);
		foreach ( $labels as $key => $label ) { astrid_admin_input( $key, $label, in_array( $key, array( 'form_intro', 'consent_text', 'success_message' ), true ) ? 'textarea' : 'text' ); }
		?>
		</table>
		<h2><?php esc_html_e( 'Button appearance', 'astrid' ); ?></h2>
		<p><?php esc_html_e( 'Theme styling uses the native WordPress Button classes. Custom styling applies to both registration and submit buttons.', 'astrid' ); ?></p>
		<table class="form-table" role="presentation">
		<?php
		astrid_admin_select( 'button_style', __( 'Style', 'astrid' ), array( 'theme' => __( 'Inherit theme', 'astrid' ), 'custom' => __( 'Custom', 'astrid' ) ) );
		astrid_admin_input( 'button_background', __( 'Background color', 'astrid' ), 'color' );
		astrid_admin_input( 'button_color', __( 'Text color', 'astrid' ), 'color' );
		astrid_admin_input( 'button_radius', __( 'Corner radius (px)', 'astrid' ), 'number' );
		astrid_admin_input( 'button_padding_y', __( 'Vertical padding (px)', 'astrid' ), 'number' );
		astrid_admin_input( 'button_padding_x', __( 'Horizontal padding (px)', 'astrid' ), 'number' );
		astrid_admin_input( 'button_font_size', __( 'Font size (px)', 'astrid' ), 'number' );
		?>
		</table>
		<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
