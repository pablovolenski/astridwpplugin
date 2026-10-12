<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function astrid_clean_options( $options ) {
	$out = array();
	if ( ! is_array( $options ) ) { return $out; }
	foreach ( array_slice( $options, 0, 100 ) as $option ) {
		if ( ! is_string( $option ) ) { continue; }
		$option = wp_html_excerpt( sanitize_text_field( $option ), 300, '' );
		if ( '' !== $option && ! in_array( $option, $out, true ) ) { $out[] = $option; }
	}
	return $out;
}

function astrid_sign_context( $context ) {
	$payload = base64_encode( wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
}

function astrid_button_style( $settings ) {
	if ( 'custom' !== $settings['button_style'] ) { return ''; }
	return sprintf( '--astrid-button-bg:%s;--astrid-button-color:%s;--astrid-button-radius:%dpx;--astrid-button-py:%dpx;--astrid-button-px:%dpx;--astrid-button-size:%dpx;',
		sanitize_hex_color( $settings['button_background'] ), sanitize_hex_color( $settings['button_color'] ),
		$settings['button_radius'], $settings['button_padding_y'], $settings['button_padding_x'], $settings['button_font_size']
	);
}

function astrid_consent_markup( $settings, $title ) {
	$parts = preg_split( '/(\{terms\}|\{privacy\}|\{activity\}|\{option\})/', $settings['consent_text'], -1, PREG_SPLIT_DELIM_CAPTURE );
	$markup = '';
	foreach ( $parts as $part ) {
		if ( '{terms}' === $part || '{privacy}' === $part ) {
			$terms = '{terms}' === $part;
			$markup .= '<a href="' . esc_url( $settings[ $terms ? 'agb_url' : 'datenschutz_url' ] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings[ $terms ? 'terms_label' : 'privacy_label' ] ) . '</a>';
		} elseif ( '{activity}' === $part ) {
			$markup .= esc_html( $title );
		} elseif ( '{option}' === $part ) {
			$markup .= '<span data-astrid-context="option"></span>';
		} else { $markup .= esc_html( $part ); }
	}
	return $markup;
}

function astrid_render_block( $attributes, $content, $block ) {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
	$title = isset( $block->parsed_block['_astrid_activity'] ) ? $block->parsed_block['_astrid_activity'] : get_the_title( $post_id );
	return astrid_render_registration( $attributes, $block->name, $title, $post_id, true );
}

function astrid_render_registration( $attributes, $block_name, $title, $post_id, $native_block = false ) {
	$settings = astrid_get_settings();
	$title = sanitize_text_field( $title ?: get_the_title( $post_id ) );
	if ( '' === $title ) { $title = __( 'Activity', 'astrid' ); }
	$multiple = 'astrid/multiple-options' === $block_name;
	$options = $multiple ? astrid_clean_options( isset( $attributes['options'] ) ? $attributes['options'] : array() ) : array();
	// Fail visibly for the editor and fail closed publicly if a multiple-options block is unconfigured.
	if ( $multiple && ! $options ) {
		return current_user_can( 'edit_posts' ) ? '<p class="astrid-setup-notice">' . esc_html__( 'Astrid: add at least one activity option in the block editor.', 'astrid' ) . '</p>' : '';
	}
	$mode = isset( $attributes['displayMode'] ) && in_array( $attributes['displayMode'], array( 'popup', 'below', 'static' ), true ) ? $attributes['displayMode'] : $settings['display_mode'];
	$id = wp_unique_id( 'astrid-' );
	$form_id = $id . '-form';
	$label = ! empty( $attributes['buttonLabel'] ) ? sanitize_text_field( $attributes['buttonLabel'] ) : $settings['button_label'];
	$context = array(
		'activity' => $title, 'post_id' => (int) $post_id, 'source_title' => sanitize_text_field( get_the_title( $post_id ) ),
		'url' => $post_id ? get_permalink( $post_id ) : home_url( '/' ),
		'block' => $block_name, 'instance' => ! empty( $attributes['anchor'] ) ? sanitize_title( $attributes['anchor'] ) : $id,
		'options' => $options, 'multiple' => $multiple,
	);
	$extra = array( 'class' => 'astrid-registration', 'data-astrid' => $mode, 'data-activity' => $title, 'data-button-style' => $settings['button_style'], 'style' => astrid_button_style( $settings ) );
	$wrapper = $native_block ? get_block_wrapper_attributes( $extra ) : '';
	if ( ! $native_block ) { foreach ( $extra as $key => $value ) { $wrapper .= ' ' . $key . '="' . esc_attr( $value ) . '"'; } }
	wp_enqueue_style( 'astrid' );
	wp_enqueue_style( 'wp-block-button' );
	wp_enqueue_script( 'astrid-view' );
	ob_start();
	?>
	<div <?php echo $wrapper; // Attributes are escaped by get_block_wrapper_attributes or above. ?>>
		<?php if ( $multiple ) : ?>
		<div class="astrid-option-field">
			<label for="<?php echo esc_attr( $id . '-option' ); ?>"><?php echo esc_html( $settings['option_label'] ); ?></label>
			<select id="<?php echo esc_attr( $id . '-option' ); ?>" name="option" form="<?php echo esc_attr( $form_id ); ?>" required aria-describedby="<?php echo esc_attr( $id . '-option-status' ); ?>">
				<option value=""><?php echo esc_html( $settings['option_placeholder'] ); ?></option>
				<?php foreach ( $options as $index => $option ) : ?><option value="<?php echo esc_attr( (string) $index ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?>
			</select>
			<p class="astrid-option-status" id="<?php echo esc_attr( $id . '-option-status' ); ?>" role="status"></p>
		</div>
		<?php endif; ?>
		<?php if ( 'static' !== $mode ) : ?>
		<div class="wp-block-buttons"><div class="wp-block-button">
			<button type="button" class="wp-block-button__link wp-element-button astrid-button astrid-trigger" aria-controls="<?php echo esc_attr( $id . '-panel' ); ?>" <?php echo 'popup' === $mode ? 'aria-haspopup="dialog"' : 'aria-expanded="false"'; ?>><?php echo esc_html( $label ); ?></button>
		</div></div>
		<?php endif; ?>
		<?php if ( 'popup' === $mode ) : ?>
		<dialog class="astrid-panel astrid-dialog" id="<?php echo esc_attr( $id . '-panel' ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>">
			<button type="button" class="astrid-close" aria-label="<?php esc_attr_e( 'Close', 'astrid' ); ?>">&times;</button>
		<?php else : ?>
		<section class="astrid-panel" id="<?php echo esc_attr( $id . '-panel' ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>" <?php if ( 'below' === $mode ) { echo 'hidden'; } ?>>
		<?php endif; ?>
			<h2 id="<?php echo esc_attr( $id . '-title' ); ?>" data-astrid-template="<?php echo esc_attr( $settings['form_title'] ); ?>"><?php echo esc_html( str_replace( array( '{activity}', '{option}' ), array( $title, '' ), $settings['form_title'] ) ); ?></h2>
			<p class="astrid-intro" data-astrid-template="<?php echo esc_attr( $settings['form_intro'] ); ?>"><?php echo esc_html( str_replace( array( '{activity}', '{option}' ), array( $title, '' ), $settings['form_intro'] ) ); ?></p>
			<form id="<?php echo esc_attr( $form_id ); ?>" class="astrid-form" method="post" action="<?php echo esc_url( rest_url( 'astrid/v1/register' ) ); ?>">
				<input type="hidden" name="context" value="<?php echo esc_attr( astrid_sign_context( $context ) ); ?>" />
				<div class="astrid-honeypot" aria-hidden="true"><label><?php esc_html_e( 'Leave this field empty', 'astrid' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
				<div class="astrid-form-fields">
				<?php foreach ( array( 'name' => 'text', 'phone' => 'tel', 'email' => 'email' ) as $field => $type ) : ?>
					<div class="astrid-field"><label for="<?php echo esc_attr( $id . '-' . $field ); ?>"><?php echo esc_html( $settings[ $field . '_label' ] ); ?> <span aria-hidden="true">*</span></label>
					<input id="<?php echo esc_attr( $id . '-' . $field ); ?>" type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>" autocomplete="<?php echo esc_attr( 'phone' === $field ? 'tel' : $field ); ?>" maxlength="<?php echo 'name' === $field ? '200' : ( 'phone' === $field ? '80' : '254' ); ?>" required /></div>
				<?php endforeach; ?>
					<div class="astrid-field"><label for="<?php echo esc_attr( $id . '-message' ); ?>"><?php echo esc_html( $settings['message_label'] ); ?></label><textarea id="<?php echo esc_attr( $id . '-message' ); ?>" name="message" rows="4" maxlength="5000"></textarea></div>
					<div class="astrid-consent"><input id="<?php echo esc_attr( $id . '-consent' ); ?>" type="checkbox" name="consent" value="1" required /><label for="<?php echo esc_attr( $id . '-consent' ); ?>">
					<?php
					echo astrid_consent_markup( $settings, $title ); // Each segment is escaped in the helper.
					?></label></div>
					<div class="wp-block-buttons"><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button astrid-button astrid-submit"><?php echo esc_html( $settings['submit_label'] ); ?></button></div></div>
				</div>
				<div class="astrid-success" hidden role="status" tabindex="-1">
					<p data-astrid-template="<?php echo esc_attr( $settings['success_message'] ); ?>"><?php echo esc_html( str_replace( array( '{activity}', '{option}' ), array( $title, '' ), $settings['success_message'] ) ); ?></p>
					<button type="button" class="astrid-restart"><?php esc_html_e( 'Register another person', 'astrid' ); ?></button>
				</div>
				<p class="astrid-status" role="status" aria-live="polite" tabindex="-1"></p>
			</form>
		<?php echo 'popup' === $mode ? '</dialog>' : '</section>'; ?>
		<noscript><p><?php esc_html_e( 'Please enable JavaScript to use this registration form.', 'astrid' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}
