<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function astrid_register_blocks() {
	wp_register_script( 'astrid-editor', ASTRID_URL . 'assets/js/editor.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n' ), ASTRID_VERSION, true );
	wp_register_script( 'astrid-view', ASTRID_URL . 'assets/js/view.js', array(), ASTRID_VERSION, true );
	wp_register_style( 'astrid', ASTRID_URL . 'assets/css/astrid.css', array(), ASTRID_VERSION );
	wp_register_style( 'astrid-editor', ASTRID_URL . 'assets/css/editor.css', array( 'astrid' ), ASTRID_VERSION );
	wp_localize_script( 'astrid-editor', 'astridEditor', array(
		'settings' => astrid_get_settings(),
		'labels' => array(
			'registration' => __( 'Astrid Registration', 'astrid' ), 'multiple' => __( 'Astrid Multiple options', 'astrid' ),
			'activity' => __( 'Detected activity', 'astrid' ), 'fallback' => __( 'Page or post title', 'astrid' ),
			'hint' => __( 'Uses the first Heading in the nearest enclosing Group; otherwise uses the page or post title.', 'astrid' ),
			'display' => __( 'Form display', 'astrid' ), 'default' => __( 'Use dashboard default', 'astrid' ),
			'popup' => __( 'Popup', 'astrid' ), 'below' => __( 'Reveal below the button', 'astrid' ), 'static' => __( 'Always visible', 'astrid' ),
			'options' => __( 'Activity options', 'astrid' ), 'optionsHelp' => __( 'One option per line. Include duration, price or any other details in the option text.', 'astrid' ),
			'empty' => __( 'Add at least one option in the block settings before publishing.', 'astrid' ),
			'button' => __( 'Button text (optional)', 'astrid' ), 'preview' => __( 'Form preview — registrations are sent only on the published page.', 'astrid' ),
		),
	) );
	wp_localize_script( 'astrid-view', 'astridView', array(
		'endpoint' => esc_url_raw( rest_url( 'astrid/v1/register' ) ),
		'sending' => __( 'Sending…', 'astrid' ), 'error' => __( 'Something went wrong. Please try again.', 'astrid' ),
		'connectionError' => __( 'The connection was interrupted. Please try again.', 'astrid' ),
		'validationError' => __( 'Please complete the required fields and accept the consent checkbox.', 'astrid' ),
		'optionError' => __( 'Please choose an option before registering.', 'astrid' ),
	) );
	register_block_type( ASTRID_DIR . 'blocks/registration', array( 'render_callback' => 'astrid_render_block' ) );
	register_block_type( ASTRID_DIR . 'blocks/multiple-options', array( 'render_callback' => 'astrid_render_block' ) );
}

/** Find this Group's first heading, without stealing a nested activity's heading. */
function astrid_group_heading( $blocks ) {
	foreach ( $blocks as $block ) {
		if ( 'core/heading' === $block['blockName'] ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( $block['innerHTML'] ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $title ) { return sanitize_text_field( $title ); }
		}
		if ( 'core/post-title' === $block['blockName'] ) { return sanitize_text_field( get_the_title() ); }
		if ( 'core/group' !== $block['blockName'] && ! empty( $block['innerBlocks'] ) ) {
			$title = astrid_group_heading( $block['innerBlocks'] );
			if ( '' !== $title ) { return $title; }
		}
	}
	return '';
}

/** Carry activity identity through native wrappers during rendering, without saved copies. */
add_filter( 'render_block_data', function ( $parsed, $source, $parent ) {
	if ( ! $parent ) { return $parsed; }
	$ancestor = $parent->parsed_block;
	$title = isset( $ancestor['_astrid_activity'] ) ? $ancestor['_astrid_activity'] : '';
	if ( 'core/group' === $ancestor['blockName'] ) {
		$heading = astrid_group_heading( $ancestor['innerBlocks'] );
		if ( '' !== $heading ) { $title = $heading; }
	}
	if ( '' !== $title ) { $parsed['_astrid_activity'] = $title; }
	return $parsed;
}, 10, 3 );

// Keep existing NOOS page shortcodes usable when replacing the old plugin.
add_shortcode( 'yn_anmelden_button', function ( $atts ) {
	$atts = shortcode_atts( array( 'course' => '', 'label' => '' ), $atts, 'yn_anmelden_button' );
	return astrid_render_registration( array( 'buttonLabel' => $atts['label'] ), 'astrid/registration', $atts['course'], get_the_ID() );
} );
