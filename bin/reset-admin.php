<?php
/**
 * One-off CLI recovery: single admin user (login admin, password password).
 *
 * Usage from WordPress root:
 *   php wp-content/plugins/site-migrator/bin/reset-admin.php
 *
 * Or with WP-CLI:
 *   wp eval-file wp-content/plugins/site-migrator/bin/reset-admin.php
 *
 * @package Site_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
	if ( ! is_readable( $wp_load ) ) {
		fwrite( STDERR, "Could not find wp-load.php. Run via: wp eval-file ...\n" );
		exit( 1 );
	}
	require_once $wp_load;
}

require_once dirname( __DIR__ ) . '/includes/class-admin.php';

$result = SMIG_Admin::reset_users_to_single_admin_public();
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

echo "OK: administrator user ID {$result}. Login: admin / password\n";
