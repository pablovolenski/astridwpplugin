<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'astrid_settings' );
// Rate-limit and duplicate-prevention transients expire automatically.
