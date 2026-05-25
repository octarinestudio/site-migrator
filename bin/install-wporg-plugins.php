<?php
/**
 * Install plugins listed in migrator-staging/manifest.json wporg_queue (recovery after apply).
 *
 * Usage from WordPress root:
 *   php wp-content/plugins/site-migrator/bin/install-wporg-plugins.php
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
		fwrite( STDERR, "Could not find wp-load.php. Run from your WordPress root.\n" );
		exit( 1 );
	}
	require_once $wp_load;
}

require_once dirname( __DIR__ ) . '/includes/class-admin.php';

$result = SMIG_Admin::install_wporg_from_manifest_public();
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

echo 'Installed: ' . ( empty( $result['installed'] ) ? '(none)' : implode( ', ', $result['installed'] ) ) . "\n";
if ( ! empty( $result['failed'] ) ) {
	echo "Failed:\n";
	foreach ( $result['failed'] as $slug => $msg ) {
		echo "  {$slug}: {$msg}\n";
	}
	exit( 1 );
}

$manifest = json_decode( file_get_contents( SMIG_STAGING_DIR . '/manifest.json' ), true );
$queue    = $manifest['wporg_queue'] ?? array();
$active   = SMIG_Plugin_Strategy::activate_wporg_queue_plugins( $queue );
echo 'Activated: ' . ( empty( $active ) ? '(none)' : implode( ', ', $active ) ) . "\n";
echo "OK: all WordPress.org plugins from the manifest are present.\n";
