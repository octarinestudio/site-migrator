<?php
/**
 * Must-use bootstrap: ensure the only user on a single-site install can access wp-admin.
 *
 * Copied to wp-content/mu-plugins/ on plugin activation and by bin/reset-admin.php so this
 * still runs when Site Migrator is deactivated after a migration.
 *
 * @package Site_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smig_admin_class = WP_PLUGIN_DIR . '/site-migrator/includes/class-admin.php';

if ( ! is_readable( $smig_admin_class ) ) {
	return;
}

require_once $smig_admin_class;

add_action( 'init', array( 'SMIG_Admin', 'maybe_ensure_single_user_admin_access' ), 1 );
