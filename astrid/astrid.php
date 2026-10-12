<?php
/**
 * Plugin Name: Astrid
 * Description: Native Gutenberg activity registration forms, with automatic activity titles and optional choices.
 * Version: 2.0.0
 * Author: NOOS Yoga
 * Text Domain: astrid
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASTRID_VERSION', '2.0.0' );
define( 'ASTRID_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASTRID_URL', plugin_dir_url( __FILE__ ) );

require_once ASTRID_DIR . 'includes/settings.php';
require_once ASTRID_DIR . 'includes/blocks.php';
require_once ASTRID_DIR . 'includes/render.php';
require_once ASTRID_DIR . 'includes/rest.php';

add_action( 'init', function () {
	load_plugin_textdomain( 'astrid', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	astrid_register_blocks();
} );

register_activation_hook( __FILE__, function () {
	// Import the original plugin's delivery settings once, without deleting them.
	if ( false === get_option( 'astrid_settings', false ) ) {
		$legacy = get_option( 'noos_yoga_anmelden_settings', array() );
		if ( is_array( $legacy ) && $legacy ) {
			$import = array();
			foreach ( array( 'recipient_email', 'agb_url', 'datenschutz_url' ) as $key ) {
				if ( isset( $legacy[ $key ] ) && is_string( $legacy[ $key ] ) ) {
					$import[ $key ] = $legacy[ $key ];
				}
			}
			add_option( 'astrid_settings', astrid_sanitize_settings( $import ) );
		}
	}
} );
