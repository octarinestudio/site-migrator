<?php
/**
 * Must-use bootstrap: sole-user admin access + lockout recovery (no wp-admin login required).
 *
 * @package Site_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMIG_RECOVERY_OPTION' ) ) {
	define( 'SMIG_RECOVERY_OPTION', 'smig_recovery_token' );
}

$smig_admin_class = WP_PLUGIN_DIR . '/site-migrator/includes/class-admin.php';

if ( ! is_readable( $smig_admin_class ) ) {
	return;
}

require_once $smig_admin_class;

add_action( 'init', array( 'SMIG_Admin', 'maybe_handle_recovery_request' ), 0 );
add_action( 'init', array( 'SMIG_Admin', 'maybe_ensure_single_user_admin_access' ), 1 );
