<?php
/**
 * Plugin Name:       Site Migrator
 * Plugin URI:        https://octarinestudio.uk/wordpress-site-migrator
 * Description:       Pull-based WordPress migration for database tables, media, themes, and plugins.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Octarine Studio
 * Author URI:        https://octarinestudio.uk
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       site-migrator
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMIG_VERSION', '1.0.0' );
define( 'SMIG_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMIG_URL', plugin_dir_url( __FILE__ ) );
define( 'SMIG_STAGING_DIR', WP_CONTENT_DIR . '/migrator-staging' );
define( 'SMIG_APPLY_STATE_DIR', SMIG_STAGING_DIR . '/.apply-state' );
define( 'SMIG_RESUME_FILE', SMIG_STAGING_DIR . '/.resume.json' );
define( 'SMIG_ROWS_PER_PAGE', 500 );
define( 'SMIG_FILES_PER_BATCH', 50 );
define( 'SMIG_MAX_FILE_SIZE', 25 * 1024 * 1024 );
define( 'SMIG_STAGING_PREFIX', '_smig_' );
define( 'SMIG_RESUME_OPTION', 'smig_resume' );
define( 'SMIG_ENDPOINT_OPTION', 'smig_endpoint_enabled' );
if ( ! defined( 'SMIG_RECOVERY_OPTION' ) ) {
	define( 'SMIG_RECOVERY_OPTION', 'smig_recovery_token' );
}

require_once SMIG_PATH . 'includes/class-security.php';
require_once SMIG_PATH . 'includes/class-plugin-strategy.php';
require_once SMIG_PATH . 'includes/class-source-api.php';
require_once SMIG_PATH . 'includes/class-admin.php';

add_action(
	'rest_api_init',
	function () {
		( new SMIG_Source_API() )->register_routes();
	}
);

add_action( 'init', array( 'SMIG_Admin', 'maybe_handle_recovery_request' ), 0 );
add_action( 'init', array( 'SMIG_Admin', 'maybe_ensure_single_user_admin_access' ), 1 );

add_filter(
	'login_message',
	function ( $message ) {
		if ( empty( $_GET['smig_recovered'] ) ) {
			return $message;
		}
		$notice = '<p class="message"><strong>' . esc_html__( 'Administrator account reset.', 'site-migrator' ) . '</strong> ';
		$notice .= esc_html__( 'Sign in with username admin and password password, then change your password under Users.', 'site-migrator' );
		$notice .= '</p>';
		return $notice . $message;
	}
);

add_action(
	'admin_menu',
	function () {
		add_management_page(
			__( 'Site Migrator', 'site-migrator' ),
			__( 'Site Migrator', 'site-migrator' ),
			'manage_options',
			'site-migrator',
			array( new SMIG_Admin(), 'render_page' )
		);
	}
);

add_action( 'wp_ajax_smig_save_endpoint', array( 'SMIG_Admin', 'ajax_save_endpoint' ) );
add_action( 'wp_ajax_smig_regenerate_code', array( 'SMIG_Admin', 'ajax_regenerate_code' ) );
add_action( 'wp_ajax_smig_verify_source', array( 'SMIG_Admin', 'ajax_verify_source' ) );
add_action( 'wp_ajax_smig_start_download', array( 'SMIG_Admin', 'ajax_start_download' ) );
add_action( 'wp_ajax_smig_download_chunk', array( 'SMIG_Admin', 'ajax_download_chunk' ) );
add_action( 'wp_ajax_smig_apply_chunk', array( 'SMIG_Admin', 'ajax_apply_chunk' ) );
add_action( 'wp_ajax_smig_cancel_migration', array( 'SMIG_Admin', 'ajax_cancel_migration' ) );
add_action( 'wp_ajax_smig_use_staged_download', array( 'SMIG_Admin', 'ajax_use_staged_download' ) );

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( strpos( $hook, 'site-migrator' ) === false ) {
			return;
		}
		wp_enqueue_style( 'smig-admin', SMIG_URL . 'assets/admin.css', array(), SMIG_VERSION );
		wp_enqueue_script( 'smig-admin', SMIG_URL . 'assets/admin.js', array( 'jquery' ), SMIG_VERSION, true );
		wp_localize_script(
			'smig-admin',
			'smig',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'smig_nonce' ),
				'resume'        => SMIG_Admin::get_resume_for_client(),
				'staged'        => SMIG_Admin::get_staged_download_for_client(),
				'default_urls'  => SMIG_Admin::get_default_target_urls_for_client(),
				'leave_warning' => __( 'A migration is in progress. Leaving this page will not stop it, but you should use Cancel and restart if you need to abort.', 'site-migrator' ),
			)
		);
	}
);

register_activation_hook(
	__FILE__,
	function () {
		if ( ! get_option( 'smig_auth_code' ) ) {
			update_option( 'smig_auth_code', wp_generate_password( 32, false ), false );
		}
		if ( null === get_option( SMIG_ENDPOINT_OPTION, null ) ) {
			update_option( SMIG_ENDPOINT_OPTION, '0', false );
		}
		if ( class_exists( 'SMIG_Admin' ) ) {
			SMIG_Admin::ensure_staging_secure_public();
			SMIG_Admin::ensure_sole_user_admin_mu_plugin_public();
		}
	}
);
