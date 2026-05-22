<?php
/**
 * PHPStan bootstrap: WordPress constants and plugin defines.
 *
 * @package Site_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'SMIG_VERSION' ) ) {
	define( 'SMIG_VERSION', '1.0.0' );
}
if ( ! defined( 'SMIG_PATH' ) ) {
	define( 'SMIG_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SMIG_URL' ) ) {
	define( 'SMIG_URL', 'https://example.com/wp-content/plugins/site-migrator/' );
}
if ( ! defined( 'SMIG_STAGING_DIR' ) ) {
	define( 'SMIG_STAGING_DIR', WP_CONTENT_DIR . '/migrator-staging' );
}
if ( ! defined( 'SMIG_ROWS_PER_PAGE' ) ) {
	define( 'SMIG_ROWS_PER_PAGE', 500 );
}
if ( ! defined( 'SMIG_FILES_PER_BATCH' ) ) {
	define( 'SMIG_FILES_PER_BATCH', 50 );
}
if ( ! defined( 'SMIG_MAX_FILE_SIZE' ) ) {
	define( 'SMIG_MAX_FILE_SIZE', 25 * 1024 * 1024 );
}
if ( ! defined( 'SMIG_STAGING_PREFIX' ) ) {
	define( 'SMIG_STAGING_PREFIX', '_smig_' );
}
if ( ! defined( 'SMIG_RESUME_OPTION' ) ) {
	define( 'SMIG_RESUME_OPTION', 'smig_resume' );
}
if ( ! defined( 'SMIG_ENDPOINT_OPTION' ) ) {
	define( 'SMIG_ENDPOINT_OPTION', 'smig_endpoint_enabled' );
}
