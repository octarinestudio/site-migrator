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
	$wp_load = null;
	$dir     = dirname( __DIR__ );
	for ( $i = 0; $i < 8; $i++ ) {
		$candidate = $dir . '/wp-load.php';
		if ( is_readable( $candidate ) ) {
			$wp_load = $candidate;
			break;
		}
		$parent = dirname( $dir );
		if ( $parent === $dir ) {
			break;
		}
		$dir = $parent;
	}
	if ( ! $wp_load ) {
		fwrite( STDERR, "Could not find wp-load.php. cd to your WordPress root, then run:\n" );
		fwrite( STDERR, "  php wp-content/plugins/site-migrator/bin/reset-admin.php\n" );
		fwrite( STDERR, "Or from this plugin folder:\n" );
		fwrite( STDERR, "  php bin/reset-admin.php\n" );
		exit( 1 );
	}
	require_once $wp_load;
}

require_once dirname( __DIR__ ) . '/includes/class-admin.php';

SMIG_Admin::ensure_sole_user_admin_mu_plugin_public();

$result = SMIG_Admin::reset_users_to_single_admin_public();
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

SMIG_Admin::maybe_ensure_single_user_admin_access();

echo "OK: administrator user ID {$result}. Login: admin / password\n";
echo "All login sessions were cleared. Use a private window or clear cookies for this site, then log in again.\n";
