<?php
// Run only inside the disposable local WordPress Playground site.
require_once '/wordpress/wp-load.php';
wp_set_password( 'astrid-test-password', 1 );
$checks = array();
function astrid_check( $condition, $description ) {
	global $checks;
	if ( ! $condition ) { throw new Exception( 'FAILED: ' . $description ); }
	$checks[] = $description;
}
function astrid_token( $html ) {
	preg_match( '/name="context" value="([^"]+)"/', $html, $match );
	return html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
}
function astrid_submit_test( $token, $overrides = array() ) {
	$request = new WP_REST_Request( 'POST', '/astrid/v1/register' );
	$request->set_body_params( array_merge( array( 'context' => $token, 'name' => 'Test Person', 'phone' => '+43 123 456', 'email' => 'person@example.org', 'message' => 'Integration test', 'consent' => '1' ), $overrides ) );
	return astrid_handle_registration( $request );
}

astrid_check( WP_Block_Type_Registry::get_instance()->is_registered( 'astrid/registration' ), 'Registration block registered' );
astrid_check( WP_Block_Type_Registry::get_instance()->is_registered( 'astrid/multiple-options' ), 'Multiple-options block registered' );
$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2 class="wp-block-heading">Course Alpha &amp; friends</h2><!-- /wp:heading -->' .
 '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:astrid/registration /--></div><!-- /wp:column --></div><!-- /wp:columns -->' .
 '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2 class="wp-block-heading">Course Beta</h2><!-- /wp:heading --><!-- wp:astrid/multiple-options {"options":["4 weeks — €60","8 weeks — €100"],"displayMode":"below"} /--></div><!-- /wp:group -->' .
 '</div><!-- /wp:group --><!-- wp:astrid/registration {"displayMode":"static"} /-->';
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Astrid activities', 'post_content' => $content ) );
$GLOBALS['post'] = get_post( $id );
$html = do_blocks( $content );
preg_match_all( '/name="context" value="([^"]+)"/', $html, $matches );
$tokens = array_map( function ( $token ) { return html_entity_decode( $token, ENT_QUOTES, 'UTF-8' ); }, $matches[1] );
astrid_check( 3 === count( $tokens ), 'Three independent forms rendered' );
$alpha = astrid_verify_context( $tokens[0] );
$beta = astrid_verify_context( $tokens[1] );
$fallback = astrid_verify_context( $tokens[2] );
astrid_check( 'Course Alpha & friends' === $alpha['activity'], 'Heading detected through Columns, with entities decoded' );
astrid_check( 'Course Beta' === $beta['activity'], 'Nearest nested Group heading wins' );
astrid_check( 'Astrid activities' === $fallback['activity'], 'Ungrouped block uses page title' );
astrid_check( false === astrid_verify_context( $tokens[0] . 'x' ), 'Altered configuration signature rejected' );
astrid_check( 3 === count( array_unique( array_column( array( $alpha, $beta, $fallback ), 'instance' ) ) ), 'Form instances have unique identifiers' );
astrid_check( false !== strpos( $html, 'wp-block-button__link wp-element-button' ), 'Native WordPress Button classes rendered' );
astrid_check( false !== strpos( $html, '<dialog ' ) && false !== strpos( $html, 'data-astrid="below"' ) && false !== strpos( $html, 'data-astrid="static"' ), 'All display modes rendered' );
$empty = render_block( array( 'blockName' => 'astrid/multiple-options', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
astrid_check( '' === $empty, 'Unconfigured multiple-options block cannot accept public registrations' );

$mail = array();
$mail_success = true;
add_filter( 'pre_wp_mail', function ( $return, $args ) use ( &$mail, &$mail_success ) { $mail[] = $args; return $mail_success; }, 10, 2 );
foreach ( array( array( 'name' => '' ), array( 'phone' => '' ), array( 'email' => 'invalid' ), array( 'consent' => '' ), array( 'name' => array( 'unexpected' ) ) ) as $bad ) {
	astrid_check( is_wp_error( astrid_submit_test( $tokens[0], $bad ) ), 'Invalid input rejected: ' . key( $bad ) );
}
astrid_check( is_wp_error( astrid_submit_test( $tokens[1] ) ), 'Missing option rejected server-side' );
astrid_check( is_wp_error( astrid_submit_test( $tokens[1], array( 'option' => '999' ) ) ), 'Option outside configured choices rejected' );
astrid_check( is_wp_error( astrid_submit_test( $tokens[1], array( 'option' => '-1' ) ) ), 'Negative option index rejected' );
astrid_submit_test( $tokens[0], array( 'website' => 'bot.example' ) );
astrid_check( 0 === count( $mail ), 'Honeypot submissions do not send mail' );
$result = astrid_submit_test( $tokens[1], array( 'option' => '1' ) );
astrid_check( ! is_wp_error( $result ) && $result->get_data()['success'], 'Valid selected-option registration succeeds' );
astrid_check( false !== strpos( $mail[0]['subject'], 'Course Beta' ) && false !== strpos( $mail[0]['subject'], '8 weeks — €100' ), 'Email subject identifies activity and option' );
astrid_check( false !== strpos( $mail[0]['message'], get_permalink( $id ) ) && false !== strpos( $mail[0]['message'], 'astrid/multiple-options' ), 'Email body includes source URL and block identity' );
astrid_check( in_array( 'Reply-To: person@example.org', $mail[0]['headers'], true ), 'Reply-To targets participant' );
astrid_submit_test( $tokens[1], array( 'option' => '1' ) );
astrid_check( 1 === count( $mail ), 'Immediate identical retry does not send duplicate mail' );
$mail_success = false;
$result = astrid_submit_test( $tokens[0] );
astrid_check( is_wp_error( $result ) && 'astrid_mail' === $result->get_error_code(), 'Mail failure produces an error instead of a thank-you' );
$mail_success = true;
astrid_check( ! is_wp_error( astrid_submit_test( $tokens[0] ) ), 'Failed mail can be retried successfully' );

$clean = astrid_sanitize_settings( array( 'button_style' => 'custom', 'button_background' => '#000000', 'button_color' => 'bad', 'button_radius' => 9999, 'button_font_size' => -1, 'form_intro' => '<script>alert(1)</script>Welcome {activity}' ) );
astrid_check( 100 === $clean['button_radius'] && 10 === $clean['button_font_size'], 'Custom style values bounded' );
astrid_check( false === strpos( $clean['form_intro'], '<script>' ), 'Edited form text sanitized' );
$clean = astrid_sanitize_settings( array( 'button_label' => '' ) );
astrid_check( ! isset( $clean['button_label'] ), 'Blank copy fields keep dynamic language defaults' );
$legacy = do_shortcode( '[yn_anmelden_button course="Legacy activity" label="Join"]' );
astrid_check( 'Legacy activity' === astrid_verify_context( astrid_token( $legacy ) )['activity'], 'Original shortcode remains compatible' );

// Install a site-language code for the translation test; Astrid ships its own catalog.
if ( ! is_dir( WP_LANG_DIR ) ) { mkdir( WP_LANG_DIR ); }
file_put_contents( WP_LANG_DIR . '/de_DE.mo', '' );
switch_to_locale( 'de_DE' );
load_textdomain( 'astrid', ASTRID_DIR . 'languages/astrid-de_DE.mo', 'de_DE' );
astrid_check( 'Anmelden' === astrid_defaults()['button_label'], 'German gettext catalog loads' );
restore_previous_locale();

file_put_contents( '/wordpress/astrid-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/wp-content/plugins/astrid/assets/css/astrid.css"><style>body{font:16px system-ui;margin:2rem auto;max-width:900px;padding:1rem}.wp-element-button{background:#1c4840;color:white;border:0;padding:12px 24px;border-radius:8px;font:inherit}.wp-block-group{padding:1rem;border:1px solid #ddd;margin-bottom:1rem}</style></head><body>' . $html . '<script>window.astridView=' . wp_json_encode( array( 'endpoint' => rest_url( 'astrid/v1/register' ), 'sending' => 'Sending…', 'error' => 'Error', 'connectionError' => 'Connection interrupted', 'validationError' => 'Complete the required fields', 'optionError' => 'Please choose an option' ) ) . ';</script><script src="/wp-content/plugins/astrid/assets/js/view.js"></script></body></html>' );
// Test-only mail transport: capture successful requests instead of delivering email.
if ( ! is_dir( '/wordpress/wp-content/mu-plugins' ) ) { mkdir( '/wordpress/wp-content/mu-plugins' ); }
file_put_contents( '/wordpress/wp-content/mu-plugins/astrid-test-mail.php', '<?php add_filter("pre_wp_mail", function($return, $args) { $mail = get_option("astrid_test_mail", array()); $mail[] = $args; update_option("astrid_test_mail", $mail); return true; }, 10, 2);' );
global $wp_version;
echo wp_json_encode( array( 'checks' => $checks, 'postId' => $id, 'htmlLength' => strlen( $html ), 'wordpress' => $wp_version, 'php' => PHP_VERSION ) );
