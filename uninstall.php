<?php
/**
 * Uninstall Site Migrator — remove options and staging data.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'smig_auth_code' );
delete_option( 'smig_endpoint_enabled' );
delete_option( 'smig_resume' );

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_smig_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_smig_' ) . '%'
	)
);

$staging = WP_CONTENT_DIR . '/migrator-staging';
if ( is_dir( $staging ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $staging, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}
	rmdir( $staging );
}
