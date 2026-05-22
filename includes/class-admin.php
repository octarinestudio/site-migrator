<?php
/**
 * Admin page + AJAX handlers for the target-side pull wizard.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later
 * @link      https://octarinestudio.uk/wordpress-site-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMIG_Admin {

	/**
	 * ADMIN PAGE
	 */
	public function render_page() {
		// Ensure auth code exists.
		$auth_code = get_option( 'smig_auth_code' );
		if ( ! $auth_code ) {
			$auth_code = wp_generate_password( 32, false );
			update_option( 'smig_auth_code', $auth_code, false );
		}

		$site_url          = untrailingslashit( home_url() );
		$site_name         = get_bloginfo( 'name' );
		$blog_id           = get_current_blog_id();
		$endpoint_enabled  = self::is_pull_endpoint_enabled();
		$share_row_class   = $endpoint_enabled ? '' : ' smig-share-inactive';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Migrator', 'site-migrator' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Pull-based WordPress migration for database tables, media, themes, and plugins.', 'site-migrator' ); ?>
			</p>

			<div id="smig-notices"></div>

			<div class="metabox-holder">
				<div class="postbox">
					<div class="postbox-header">
						<h2 class="hndle"><?php esc_html_e( 'Share with target site', 'site-migrator' ); ?></h2>
					</div>
					<div class="inside">
						<p class="description">
							<?php esc_html_e( 'On the site that will receive the copy, open Tools → Site Migrator and enter both values under “Pull from another site”.', 'site-migrator' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Allow pulls', 'site-migrator' ); ?></th>
								<td>
									<label for="smig-endpoint-enabled">
										<input
											type="checkbox"
											id="smig-endpoint-enabled"
											value="1"
											<?php checked( $endpoint_enabled ); ?>
										/>
										<?php esc_html_e( 'Enable migration endpoint', 'site-migrator' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When off, other sites cannot pull from this site. You can still pull from other sites below.', 'site-migrator' ); ?>
									</p>
									<span class="spinner" id="smig-endpoint-spinner"></span>
								</td>
							</tr>
							<tr class="smig-share-credentials<?php echo esc_attr( $share_row_class ); ?>">
								<th scope="row">
									<label for="smig-site-url"><?php esc_html_e( 'Site URL', 'site-migrator' ); ?></label>
								</th>
								<td>
									<div class="smig-input-group">
										<input type="text" class="large-text code" readonly id="smig-site-url" value="<?php echo esc_attr( $site_url ); ?>" />
										<button type="button" class="button smig-copy" data-target="smig-site-url"><?php esc_html_e( 'Copy', 'site-migrator' ); ?></button>
									</div>
								</td>
							</tr>
							<tr class="smig-share-credentials<?php echo esc_attr( $share_row_class ); ?>">
								<th scope="row">
									<label for="smig-auth-code"><?php esc_html_e( 'Auth code', 'site-migrator' ); ?></label>
								</th>
								<td>
									<div class="smig-input-group">
										<input type="text" class="large-text code" readonly id="smig-auth-code" value="<?php echo esc_attr( $auth_code ); ?>" />
										<button type="button" class="button smig-copy" data-target="smig-auth-code"><?php esc_html_e( 'Copy', 'site-migrator' ); ?></button>
									</div>
									<p class="description">
										<?php esc_html_e( 'Regenerating invalidates in-progress pulls.', 'site-migrator' ); ?>
									</p>
								</td>
							</tr>
							<?php if ( is_multisite() ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Blog', 'site-migrator' ); ?></th>
								<td>
									<?php
									printf(
										/* translators: 1: blog ID, 2: site name */
										esc_html__( 'ID %1$s — %2$s', 'site-migrator' ),
										esc_html( (string) $blog_id ),
										esc_html( $site_name )
									);
									?>
								</td>
							</tr>
							<?php endif; ?>
						</table>
						<p>
							<button type="button" class="button button-secondary" id="smig-regen-btn">
								<?php esc_html_e( 'Regenerate auth code', 'site-migrator' ); ?>
							</button>
							<span class="spinner" id="smig-regen-spinner"></span>
						</p>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header">
						<h2 class="hndle"><?php esc_html_e( 'Pull from another site', 'site-migrator' ); ?></h2>
					</div>
					<div class="inside">
						<nav class="nav-tab-wrapper wp-clearfix" id="smig-step-nav" aria-label="<?php esc_attr_e( 'Migration steps', 'site-migrator' ); ?>">
							<a href="#" class="nav-tab nav-tab-active" data-step="1"><?php esc_html_e( '1. Connect', 'site-migrator' ); ?></a>
							<a href="#" class="nav-tab" data-step="2"><?php esc_html_e( '2. Download', 'site-migrator' ); ?></a>
							<a href="#" class="nav-tab" data-step="3"><?php esc_html_e( '3. Apply', 'site-migrator' ); ?></a>
						</nav>

						<div class="smig-step-panel active" id="smig-step-1">
							<h3><?php esc_html_e( 'Connect to source', 'site-migrator' ); ?></h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="smig-src-url"><?php esc_html_e( 'Source site URL', 'site-migrator' ); ?></label>
									</th>
									<td>
										<input type="url" class="regular-text" id="smig-src-url" placeholder="https://example.com" />
										<p class="description">
											<?php esc_html_e( 'Same value as “Site URL” on the source site’s Share panel.', 'site-migrator' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="smig-src-auth"><?php esc_html_e( 'Auth code', 'site-migrator' ); ?></label>
									</th>
									<td>
										<input type="text" class="large-text code" id="smig-src-auth" autocomplete="off" />
									</td>
								</tr>
							</table>
							<p class="submit">
								<button type="button" class="button button-primary" id="smig-verify-btn">
									<?php esc_html_e( 'Connect and verify', 'site-migrator' ); ?>
								</button>
								<span class="spinner" id="smig-verify-spinner"></span>
							</p>
							<div id="smig-verify-result"></div>
						</div>

						<div class="smig-step-panel" id="smig-step-2">
							<h3><?php esc_html_e( 'Download content', 'site-migrator' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Content is saved under wp-content/migrator-staging/ before anything on this site is changed.', 'site-migrator' ); ?>
							</p>
							<div id="smig-manifest"></div>
							<fieldset class="smig-options">
								<legend class="screen-reader-text"><?php esc_html_e( 'Plugin transfer options', 'site-migrator' ); ?></legend>
								<label for="smig-opt-wporg">
									<input type="checkbox" id="smig-opt-wporg" checked="checked" />
									<?php esc_html_e( 'Install WordPress.org plugins from wordpress.org instead of copying files', 'site-migrator' ); ?>
								</label>
								<br />
								<label for="smig-opt-skip-same">
									<input type="checkbox" id="smig-opt-skip-same" checked="checked" />
									<?php esc_html_e( 'Skip plugins already installed at the same version on this site', 'site-migrator' ); ?>
								</label>
							</fieldset>
							<p class="submit" id="smig-dl-actions-start">
								<button type="button" class="button button-primary" id="smig-download-btn">
									<?php esc_html_e( 'Start download', 'site-migrator' ); ?>
								</button>
								<span class="spinner" id="smig-dl-spinner"></span>
							</p>
							<p class="submit" id="smig-dl-actions-running" hidden>
								<button type="button" class="button" id="smig-cancel-btn">
									<?php esc_html_e( 'Cancel and restart', 'site-migrator' ); ?>
								</button>
								<span class="spinner" id="smig-cancel-spinner"></span>
							</p>
							<div id="smig-dl-progress" class="smig-progress-wrap" hidden>
								<progress id="smig-dl-bar" max="100" value="0"></progress>
								<p class="description" id="smig-dl-text"></p>
							</div>
							<div id="smig-staged-banner" class="notice notice-info inline" hidden>
								<p>
									<strong><?php esc_html_e( 'Downloaded content on this server', 'site-migrator' ); ?></strong>
									<span id="smig-staged-banner-detail"></span>
								</p>
								<p>
									<button type="button" class="button button-primary" id="smig-use-staged-btn">
										<?php esc_html_e( 'Apply from downloaded content', 'site-migrator' ); ?>
									</button>
									<span class="spinner" id="smig-use-staged-spinner"></span>
								</p>
							</div>
						</div>

						<div class="smig-step-panel" id="smig-step-3">
							<h3><?php esc_html_e( 'Confirm and apply', 'site-migrator' ); ?></h3>
							<div class="notice notice-warning inline">
								<p>
									<strong><?php esc_html_e( 'Warning:', 'site-migrator' ); ?></strong>
									<?php esc_html_e( 'This replaces all content on this site with the downloaded data. There is no undo. The current database and files will be overwritten.', 'site-migrator' ); ?>
								</p>
							</div>
							<div id="smig-staged-banner-step3" class="notice notice-info inline" hidden>
								<p>
									<strong><?php esc_html_e( 'Downloaded content ready', 'site-migrator' ); ?></strong>
									<span id="smig-staged-banner-step3-detail"></span>
								</p>
								<p>
									<button type="button" class="button button-primary" id="smig-use-staged-btn-step3">
										<?php esc_html_e( 'Use downloaded content', 'site-migrator' ); ?>
									</button>
									<span class="spinner" id="smig-use-staged-spinner-step3"></span>
								</p>
							</div>
							<div id="smig-apply-summary"></div>
							<fieldset class="smig-options">
								<legend class="screen-reader-text"><?php esc_html_e( 'After apply', 'site-migrator' ); ?></legend>
								<label for="smig-opt-reset-admin">
									<input type="checkbox" id="smig-opt-reset-admin" checked="checked" />
									<?php esc_html_e( 'Replace all users with a single administrator (login: admin, password: password)', 'site-migrator' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Recommended after migration so you can sign in to wp-admin. Change this password immediately.', 'site-migrator' ); ?>
								</p>
							</fieldset>
							<p class="submit" id="smig-apply-actions-start">
								<button type="button" class="button button-link-delete" id="smig-apply-btn">
									<?php esc_html_e( 'Replace all content', 'site-migrator' ); ?>
								</button>
								<span class="spinner" id="smig-apply-spinner"></span>
							</p>
							<p class="submit" id="smig-apply-actions-running" hidden>
								<button type="button" class="button" id="smig-cancel-apply-btn">
									<?php esc_html_e( 'Cancel and restart', 'site-migrator' ); ?>
								</button>
								<span class="spinner" id="smig-cancel-apply-spinner"></span>
							</p>
							<div id="smig-apply-progress" class="smig-progress-wrap" hidden>
								<progress id="smig-apply-bar" max="100" value="0"></progress>
								<p class="description" id="smig-apply-text"></p>
							</div>
							<div id="smig-complete" class="notice notice-success inline" hidden>
								<p>
									<strong><?php esc_html_e( 'Migration complete.', 'site-migrator' ); ?></strong>
									<span id="smig-complete-login-hint" hidden>
										<?php esc_html_e( 'Sign in with username admin and password password, then change the password under Users.', 'site-migrator' ); ?>
									</span>
									<?php
									printf(
										/* translators: %s: link to Settings > Permalinks */
										' ' . wp_kses_post( __( 'Save permalinks under %s if links break.', 'site-migrator' ) ),
										'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'site-migrator' ) . '</a>'
									);
									?>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether this site exposes the REST pull endpoint to remote targets.
	 */
	public static function is_pull_endpoint_enabled() {
		return (bool) get_option( SMIG_ENDPOINT_OPTION, false );
	}

	/**
	 * AJAX — save pull endpoint enabled state.
	 */
	public static function ajax_save_endpoint() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$enabled = ! empty( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		update_option( SMIG_ENDPOINT_OPTION, $enabled ? '1' : '0', false );

		wp_send_json_success(
			array(
				'enabled' => $enabled,
			)
		);
	}

	/**
	 * AJAX — regenerate auth code
	 */
	public static function ajax_regenerate_code() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}
		$code = wp_generate_password( 32, false );
		update_option( 'smig_auth_code', $code, false );
		wp_send_json_success( array( 'code' => $code ) );
	}

	/**
	 * AJAX — verify source
	 */
	public static function ajax_verify_source() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( empty( $_POST['source_url'] ) || empty( $_POST['source_auth'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Source URL and auth code are required.', 'site-migrator' ) ) );
		}

		$url = self::normalize_source_url( esc_url_raw( wp_unslash( $_POST['source_url'] ) ) );
		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}
		$auth = sanitize_text_field( wp_unslash( $_POST['source_auth'] ) );

		$resp = self::remote( $url, 'verify', $auth );
		if ( is_wp_error( $resp ) ) {
			wp_send_json_error( array( 'message' => $resp->get_error_message() ) );
		}

		wp_send_json_success( $resp );
	}

	/**
	 * AJAX — start download (fetch manifest, create session)
	 */
	public static function ajax_start_download() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( empty( $_POST['source_url'] ) || empty( $_POST['source_auth'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Source URL and auth code are required.', 'site-migrator' ) ) );
		}

		$url = self::normalize_source_url( esc_url_raw( wp_unslash( $_POST['source_url'] ) ) );
		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}
		$auth = sanitize_text_field( wp_unslash( $_POST['source_auth'] ) );

		// Fetch manifest.
		$manifest = self::remote( $url, 'manifest', $auth, 60 );
		if ( is_wp_error( $manifest ) ) {
			wp_send_json_error( array( 'message' => 'Manifest: ' . $manifest->get_error_message() ) );
		}

		$plugin_opts = array(
			'wporg_install'     => ! isset( $_POST['wporg_install'] ) || '0' !== $_POST['wporg_install'],
			'skip_same_version' => ! isset( $_POST['skip_same_version'] ) || '0' !== $_POST['skip_same_version'],
		);

		$plugins_detail  = $manifest['file_types']['plugins']['plugins'] ?? array();
		$has_plugin_meta = ! empty( $plugins_detail );
		$pull_slugs      = $has_plugin_meta
			? SMIG_Plugin_Strategy::plugins_requiring_file_pull( $plugins_detail, $plugin_opts )
			: null;

		// Fetch full file lists.
		$all_files = array();
		foreach ( array( 'uploads', 'theme', 'plugins' ) as $type ) {
			if ( 'plugins' === $type && $has_plugin_meta && empty( $pull_slugs ) ) {
				continue;
			}
			$fl = self::remote( $url, 'files-list', $auth, 60, array( 'type' => $type ) );
			if ( is_wp_error( $fl ) ) {
				continue;
			}
			if ( ! empty( $fl['files'] ) ) {
				$file_list = $fl['files'];
				if ( 'plugins' === $type && $has_plugin_meta && ! empty( $pull_slugs ) ) {
					$file_list = SMIG_Plugin_Strategy::filter_plugin_files_by_slug( $file_list, $pull_slugs );
				}
				foreach ( $file_list as $f ) {
					$all_files[] = array(
						'type' => $type,
						'path' => $f['relative_path'],
						'size' => $f['size'],
					);
				}
			}
		}

		$strategy = SMIG_Plugin_Strategy::filter_file_queue(
			$plugins_detail,
			$all_files,
			$plugin_opts
		);

		$plugin_files_before = 0;
		foreach ( $all_files as $f ) {
			if ( 'plugins' === $f['type'] ) {
				++$plugin_files_before;
			}
		}

		$all_files   = $strategy['files'];
		$wporg_queue = $strategy['wporg_queue'];
		$skipped     = $strategy['skipped'];

		$plugin_files_after = 0;
		foreach ( $all_files as $f ) {
			if ( 'plugins' === $f['type'] ) {
				++$plugin_files_after;
			}
		}
		$plugin_files_removed = max( 0, $plugin_files_before - $plugin_files_after );

		// Prepare staging dir.
		self::clean_staging();
		self::ensure_staging_secure();
		wp_mkdir_p( SMIG_STAGING_DIR . '/tables' );
		wp_mkdir_p( SMIG_STAGING_DIR . '/uploads' );
		wp_mkdir_p( SMIG_STAGING_DIR . '/themes' );
		wp_mkdir_p( SMIG_STAGING_DIR . '/plugins' );

		// Calculate total work items.
		$total = 0;
		foreach ( $manifest['tables'] as $t ) {
			++$total; // Table schema.
			$total += max( 1, (int) ceil( $t['rows'] / SMIG_ROWS_PER_PAGE ) );
		}
		$total += count( $all_files );

		// Build session state.
		$session_id = wp_generate_password( 16, false );
		$state      = array(
			'id'           => $session_id,
			'source_url'   => $url,
			'source_auth'  => $auth,
			'src_prefix'   => $manifest['prefix'],
			'src_base_pfx' => $manifest['base_prefix'],
			'src_site_url' => $manifest['site_url'],
			'manifest'     => $manifest,
			'all_files'    => $all_files,
			'phase'        => 'table_schema',
			'tbl_idx'      => 0,
			'tbl_page'     => 1,
			'file_idx'     => 0,
			'done'         => 0,
			'total'        => $total,
			'current'      => '',
		);

		// Save manifest summary to file for apply phase.
		file_put_contents(
			SMIG_STAGING_DIR . '/manifest.json',
			wp_json_encode(
				array(
					'tables'               => $manifest['tables'],
					'file_types'           => $manifest['file_types'],
					'prefix'               => $manifest['prefix'],
					'base_prefix'          => $manifest['base_prefix'],
					'site_url'             => $manifest['site_url'],
					'session_id'           => $session_id,
					'all_files'            => $all_files,
					'wporg_queue'          => $wporg_queue,
					'plugins_skipped'      => $skipped,
					'plugin_files_removed' => $plugin_files_removed,
				)
			)
		);

		set_transient( 'smig_session_' . $session_id, $state, 4 * HOUR_IN_SECONDS );

		self::save_resume(
			array(
				'session_id'        => $session_id,
				'source_url'        => $url,
				'source_auth'       => $auth,
				'download_complete' => false,
				'apply_started'     => false,
				'status'            => 'downloading',
				'progress'          => 0,
				'current'           => '',
				'manifest_stats'    => array(
					'files'                => count( $all_files ),
					'wporg_count'          => count( $wporg_queue ),
					'skipped_count'        => count( $skipped ),
					'plugin_files_removed' => $plugin_files_removed,
				),
			)
		);

		wp_send_json_success(
			array(
				'session_id'           => $session_id,
				'manifest'             => $manifest,
				'total'                => $total,
				'files'                => count( $all_files ),
				'wporg_count'          => count( $wporg_queue ),
				'skipped_count'        => count( $skipped ),
				'plugin_files_removed' => $plugin_files_removed,
			)
		);
	}

	/**
	 * AJAX — cancel migration and clear staging
	 */
	public static function ajax_cancel_migration() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$sid = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( $sid ) {
			delete_transient( 'smig_session_' . $sid );
			delete_transient( 'smig_apply_' . $sid );
		}

		self::clean_staging();
		self::clear_resume();
		wp_send_json_success();
	}

	/**
	 * AJAX — register an existing migrator-staging download for apply (skip re-download).
	 */
	public static function ajax_use_staged_download() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$staged = self::inspect_staged_download();
		if ( empty( $staged['ready'] ) ) {
			wp_send_json_error(
				array(
					'message' => $staged['message'] ?? __( 'No complete downloaded migration was found.', 'site-migrator' ),
				)
			);
		}

		$resume = get_option( SMIG_RESUME_OPTION, array() );
		if ( ! is_array( $resume ) ) {
			$resume = array();
		}

		$sid = '';
		if ( ! empty( $resume['session_id'] ) ) {
			$sid = (string) $resume['session_id'];
		} elseif ( ! empty( $staged['session_id'] ) ) {
			$sid = (string) $staged['session_id'];
		}
		if ( '' === $sid ) {
			$sid = wp_generate_password( 16, false );
		}

		delete_transient( 'smig_session_' . $sid );
		delete_transient( 'smig_apply_' . $sid );

		self::save_resume(
			array(
				'session_id'        => $sid,
				'source_url'        => $staged['source_url'] ?? '',
				'download_complete' => true,
				'apply_started'     => false,
				'status'            => 'downloaded',
				'progress'          => 100,
				'current'           => __( 'Ready to apply from downloaded content', 'site-migrator' ),
				'manifest_stats'    => $staged['manifest_stats'] ?? array(),
			)
		);

		wp_send_json_success(
			array(
				'session_id'     => $sid,
				'source_url'     => $staged['source_url'] ?? '',
				'manifest'       => $staged['manifest'] ?? array(),
				'manifest_stats' => $staged['manifest_stats'] ?? array(),
			)
		);
	}

	/**
	 * Staged download summary for admin.js on page load.
	 *
	 * @return array|null
	 */
	public static function get_staged_download_for_client() {
		$staged = self::inspect_staged_download();
		if ( empty( $staged['detected'] ) ) {
			return null;
		}

		return array(
			'detected'         => true,
			'ready'            => ! empty( $staged['ready'] ),
			'message'          => $staged['message'] ?? '',
			'source_url'       => $staged['source_url'] ?? '',
			'manifest'         => $staged['manifest'] ?? null,
			'manifest_stats'   => $staged['manifest_stats'] ?? array(),
			'can_apply_staged' => ! empty( $staged['ready'] ),
		);
	}

	/**
	 * Validate wp-content/migrator-staging/ for a complete prior download.
	 *
	 * @return array{detected: bool, ready: bool, message?: string, source_url?: string, session_id?: string, manifest?: array, manifest_stats?: array}
	 */
	private static function inspect_staged_download() {
		$manifest_path = SMIG_STAGING_DIR . '/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return array( 'detected' => false, 'ready' => false );
		}

		$stored = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $stored ) || empty( $stored['tables'] ) ) {
			return array(
				'detected' => true,
				'ready'    => false,
				'message'  => __( 'manifest.json is missing or invalid.', 'site-migrator' ),
			);
		}

		$tables_dir = SMIG_STAGING_DIR . '/tables';
		foreach ( $stored['tables'] as $tbl ) {
			$name = $tbl['name'] ?? '';
			if ( ! is_string( $name ) || '' === $name || ! self::assert_valid_table_name( $name ) ) {
				return array(
					'detected' => true,
					'ready'    => false,
					'message'  => __( 'Invalid table name in manifest.', 'site-migrator' ),
				);
			}

			$schema = $tables_dir . '/' . $name . '.schema.sql';
			if ( ! is_readable( $schema ) ) {
				return array(
					'detected' => true,
					'ready'    => false,
					'message'  => sprintf(
						/* translators: %s: table name */
						__( 'Missing schema for table %s. Re-download or finish the download first.', 'site-migrator' ),
						$name
					),
				);
			}

			$row_count = isset( $tbl['rows'] ) ? (int) $tbl['rows'] : 0;
			if ( $row_count > 0 ) {
				$rows_file = $tables_dir . '/' . $name . '.rows.1.json';
				if ( ! is_readable( $rows_file ) ) {
					return array(
						'detected' => true,
						'ready'    => false,
						'message'  => sprintf(
							/* translators: %s: table name */
							__( 'Missing row data for table %s. The download may still be in progress.', 'site-migrator' ),
							$name
						),
					);
				}
			}
		}

		$files = $stored['all_files'] ?? array();
		$stats = array(
			'files'                => count( $files ),
			'wporg_count'          => count( $stored['wporg_queue'] ?? array() ),
			'skipped_count'        => count( $stored['plugins_skipped'] ?? array() ),
			'plugin_files_removed' => (int) ( $stored['plugin_files_removed'] ?? 0 ),
		);

		return array(
			'detected'         => true,
			'ready'            => true,
			'source_url'       => $stored['site_url'] ?? '',
			'session_id'       => $stored['session_id'] ?? '',
			'manifest'         => array(
				'tables'     => $stored['tables'],
				'file_types' => $stored['file_types'] ?? array(),
			),
			'manifest_stats'   => $stats,
		);
	}

	/**
	 * AJAX — download one chunk
	 */
	public static function ajax_download_chunk() {
		check_ajax_referer( 'smig_nonce', 'nonce' );

		if ( empty( $_POST['session_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID is required.', 'site-migrator' ) ) );
		}

		$sid   = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );
		$state = get_transient( 'smig_session_' . $sid );
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => 'Session expired — start over.' ) );
		}

		$url      = $state['source_url'];
		$auth     = $state['source_auth'];
		$manifest = $state['manifest'];
		$tables   = $manifest['tables'];

		switch ( $state['phase'] ) {

			// Table schema.
			case 'table_schema':
				$tbl = $tables[ $state['tbl_idx'] ];
				$res = self::remote( $url, 'table-schema', $auth, 30, array( 'table' => $tbl['name'] ) );

				if ( is_wp_error( $res ) ) {
					wp_send_json_error( array( 'message' => 'Schema ' . $tbl['name'] . ': ' . $res->get_error_message() ) );
				}

				file_put_contents(
					SMIG_STAGING_DIR . '/tables/' . $tbl['name'] . '.schema.sql',
					$res['create_sql']
				);

				++$state['done'];
				$state['current'] = 'Schema: ' . $tbl['name'];

				if ( $tbl['rows'] > 0 ) {
					$state['phase']    = 'table_rows';
					$state['tbl_page'] = 1;
				} else {
					file_put_contents(
						SMIG_STAGING_DIR . '/tables/' . $tbl['name'] . '.rows.1.json',
						wp_json_encode(
							array(
								'columns' => array(),
								'rows'    => array(),
							)
						)
					);
					++$state['done'];
					$state = self::advance_table( $state );
				}
				break;

			// Table rows.
			case 'table_rows':
				$tbl = $tables[ $state['tbl_idx'] ];
				$res = self::remote(
					$url,
					'table-rows',
					$auth,
					60,
					array(
						'table'    => $tbl['name'],
						'page'     => $state['tbl_page'],
						'per_page' => SMIG_ROWS_PER_PAGE,
					)
				);

				if ( is_wp_error( $res ) ) {
					wp_send_json_error( array( 'message' => 'Rows ' . $tbl['name'] . ' p' . $state['tbl_page'] . ': ' . $res->get_error_message() ) );
				}

				file_put_contents(
					SMIG_STAGING_DIR . '/tables/' . $tbl['name'] . '.rows.' . $state['tbl_page'] . '.json',
					wp_json_encode(
						array(
							'columns'     => $res['columns'],
							'rows'        => $res['rows'],
							'total_pages' => $res['total_pages'],
						)
					)
				);

				++$state['done'];
				$state['current'] = $tbl['name'] . ' rows ' . $state['tbl_page'] . '/' . $res['total_pages'];

				if ( $state['tbl_page'] >= $res['total_pages'] ) {
					$state = self::advance_table( $state );
				} else {
					++$state['tbl_page'];
				}
				break;

			// Files.
			case 'files':
				if ( $state['file_idx'] >= count( $state['all_files'] ) ) {
					$state['phase'] = 'done';
					break;
				}

				$file = $state['all_files'][ $state['file_idx'] ];

				if ( $file['size'] > SMIG_MAX_FILE_SIZE ) {
					++$state['file_idx'];
					++$state['done'];
					$state['current'] = 'Skipped (>25 MB): ' . basename( $file['path'] );
					break;
				}

				$res = self::remote(
					$url,
					'file-content',
					$auth,
					120,
					array(
						'type' => $file['type'],
						'path' => $file['path'],
					)
				);

				if ( is_wp_error( $res ) ) {
					++$state['file_idx'];
					++$state['done'];
					$state['current'] = 'Error: ' . basename( $file['path'] );
					break;
				}

				$subdir = 'theme' === $file['type'] ? 'themes' : $file['type'];
				$dest   = SMIG_STAGING_DIR . '/' . $subdir . '/' . $file['path'];
				wp_mkdir_p( dirname( $dest ) );
				file_put_contents( $dest, base64_decode( $res['content'] ) );

				++$state['file_idx'];
				++$state['done'];
				$state['current'] = $file['type'] . ': ' . basename( $file['path'] );
				if ( $state['file_idx'] >= count( $state['all_files'] ) ) {
					$state['phase'] = 'done';
				}
				break;

			case 'done':
				break;
		}

		set_transient( 'smig_session_' . $sid, $state, 4 * HOUR_IN_SECONDS );
		self::sync_resume_from_download( $state );

		$pct = $state['total'] > 0 ? round( ( $state['done'] / $state['total'] ) * 100 ) : 0;
		wp_send_json_success(
			array(
				'phase'    => $state['phase'],
				'progress' => $pct,
				'done'     => $state['done'],
				'total'    => $state['total'],
				'current'  => $state['current'],
			)
		);
	}

	/**
	 * AJAX — apply one chunk
	 */
	public static function ajax_apply_chunk() {
		check_ajax_referer( 'smig_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( empty( $_POST['session_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID is required.', 'site-migrator' ) ) );
		}

		$sid = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );

		$akey  = 'smig_apply_' . $sid;
		$state = get_transient( $akey );
		if ( ! $state ) {
			$reset_admin = ! empty( $_POST['reset_admin_user'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['reset_admin_user'] ) );
			$state       = self::init_apply_state( $sid, $reset_admin );
			if ( is_wp_error( $state ) ) {
				wp_send_json_error( array( 'message' => $state->get_error_message() ) );
			}
		} elseif ( isset( $_POST['reset_admin_user'] ) ) {
			$state['reset_admin_user'] = ( '1' === sanitize_text_field( wp_unslash( $_POST['reset_admin_user'] ) ) );
		}

		global $wpdb;

		// Resume sessions from before apply v2 (DB swap ran before file copy).
		if ( empty( $state['apply_version'] ) || (int) $state['apply_version'] < 2 ) {
			if ( ! empty( $state['db_swapped'] ) && 'copy_files' === $state['phase'] ) {
				$manifest    = json_decode( file_get_contents( SMIG_STAGING_DIR . '/manifest.json' ), true );
				$wporg_queue = $manifest['wporg_queue'] ?? array();
				if ( ! empty( $wporg_queue ) ) {
					$state['phase']       = 'install_wporg';
					$state['wporg_idx']   = 0;
					$state['wporg_queue'] = $wporg_queue;
				} else {
					$state['phase'] = 'cleanup';
				}
			}
			$state['apply_version'] = 2;
		}

		switch ( $state['phase'] ) {

			case 'create_staging':
				$batch       = 0;
				$table_count = count( $state['tables'] );
				while ( $state['tbl_idx'] < $table_count && $batch < 5 ) {
					$tbl      = $state['tables'][ $state['tbl_idx'] ];
					$src_name = $tbl['name'];
					if ( ! self::assert_valid_table_name( $src_name ) ) {
						wp_send_json_error( array( 'message' => __( 'Invalid table in migration manifest.', 'site-migrator' ) ) );
					}
					$suffix      = self::table_suffix( $src_name, $state['src_prefix'], $state['src_base_pfx'] );
					$stg_name    = SMIG_STAGING_PREFIX . $suffix;
					$schema_file = SMIG_STAGING_DIR . '/tables/' . $src_name . '.schema.sql';
					if ( ! is_readable( $schema_file ) ) {
						wp_send_json_error( array( 'message' => __( 'Missing staged schema file.', 'site-migrator' ) ) );
					}
					$schema_sql = file_get_contents( $schema_file );

					$schema_sql = preg_replace(
						'/CREATE TABLE[^`]*`' . preg_quote( $src_name, '/' ) . '`/',
						'CREATE TABLE IF NOT EXISTS `' . $stg_name . '`',
						$schema_sql,
						1
					);

					$wpdb->query( "DROP TABLE IF EXISTS `{$stg_name}`" );
					$result = $wpdb->query( $schema_sql );
					if ( false === $result ) {
						wp_send_json_error(
							array(
								'message' => SMIG_Security::public_error_message(
									__( 'Creating a staging table failed.', 'site-migrator' ),
									$wpdb->last_error
								),
							)
						);
					}

					++$state['tbl_idx'];
					++$state['done'];
					$state['current'] = 'Created staging: ' . $stg_name;
					++$batch;
				}

				if ( $state['tbl_idx'] >= count( $state['tables'] ) ) {
					$state['phase']    = 'import_rows';
					$state['tbl_idx']  = 0;
					$state['row_page'] = 1;
				}
				break;

			case 'import_rows':
				if ( $state['tbl_idx'] >= count( $state['tables'] ) ) {
					$state['phase']    = 'copy_files';
					$state['file_idx'] = 0;
					break;
				}

				$tbl      = $state['tables'][ $state['tbl_idx'] ];
				$src_name = $tbl['name'];
				if ( ! self::assert_valid_table_name( $src_name ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid table in migration manifest.', 'site-migrator' ) ) );
				}
				$suffix   = self::table_suffix( $src_name, $state['src_prefix'], $state['src_base_pfx'] );
				$stg_name = SMIG_STAGING_PREFIX . $suffix;

				$file = SMIG_STAGING_DIR . '/tables/' . $src_name . '.rows.' . (int) $state['row_page'] . '.json';

				if ( ! file_exists( $file ) ) {
					++$state['tbl_idx'];
					$state['row_page'] = 1;
					break;
				}

				$data = json_decode( file_get_contents( $file ), true );

				if ( ! empty( $data['rows'] ) && ! empty( $data['columns'] ) ) {
					$cols = array_values( array_filter( $data['columns'], array( 'SMIG_Security', 'is_valid_identifier' ) ) );
					if ( empty( $cols ) ) {
						++$state['tbl_idx'];
						$state['row_page'] = 1;
						break;
					}
					$col_list   = '`' . implode( '`,`', $cols ) . '`';
					$chunk_size = 50;

					foreach ( array_chunk( $data['rows'], $chunk_size ) as $batch ) {
						$values = array();
						foreach ( $batch as $row ) {
							$escaped = array();
							foreach ( $row as $v ) {
								$escaped[] = null === $v ? 'NULL' : "'" . $wpdb->_real_escape( $v ) . "'";
							}
							$values[] = '(' . implode( ',', $escaped ) . ')';
						}
						$sql = "INSERT INTO `{$stg_name}` ({$col_list}) VALUES " . implode( ',', $values );
						$wpdb->query( $sql );
					}
				}

				++$state['done'];
				$state['current'] = $src_name . ' rows p' . $state['row_page'];

				$total_pages = isset( $data['total_pages'] ) ? (int) $data['total_pages'] : 1;
				$next_file   = SMIG_STAGING_DIR . '/tables/' . $src_name . '.rows.' . ( $state['row_page'] + 1 ) . '.json';

				if ( $state['row_page'] >= $total_pages || ! file_exists( $next_file ) ) {
					++$state['tbl_idx'];
					$state['row_page'] = 1;
				} else {
					++$state['row_page'];
				}
				break;

			case 'swap':
				$target_prefix = $wpdb->prefix;
				$renames       = array();

				foreach ( $state['tables'] as $tbl ) {
					if ( ! self::assert_valid_table_name( $tbl['name'] ) ) {
						continue;
					}
					$suffix   = self::table_suffix( $tbl['name'], $state['src_prefix'], $state['src_base_pfx'] );
					$stg_name = SMIG_STAGING_PREFIX . $suffix;
					$tgt_name = $target_prefix . $suffix;
					$old_name = SMIG_STAGING_PREFIX . 'old_' . $suffix;

					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stg_name ) ) );
					if ( ! $exists ) {
						continue;
					}

					$tgt_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tgt_name ) ) );
					if ( $tgt_exists ) {
						$renames[] = "`{$tgt_name}` TO `{$old_name}`";
					}
					$renames[] = "`{$stg_name}` TO `{$tgt_name}`";
				}

				if ( ! empty( $renames ) ) {
					$sql    = 'RENAME TABLE ' . implode( ', ', $renames );
					$result = $wpdb->query( $sql );
					if ( false === $result ) {
						wp_send_json_error(
							array(
								'message' => SMIG_Security::public_error_message(
									__( 'Replacing database tables failed.', 'site-migrator' ),
									$wpdb->last_error
								),
							)
						);
					}
				}

				$state['done']    += 2;
				$state['current']  = 'Replacing database…';
				$state['db_swapped'] = true;

				self::apply_post_swap_updates( $state );

				if ( ! empty( $state['reset_admin_user'] ) && empty( $state['users_reset_done'] ) ) {
					$result = self::reset_users_to_single_admin();
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( array( 'message' => $result->get_error_message() ) );
					}
					$state['users_reset_done'] = true;
					$state['admin_login']      = 'admin';
				}

				$state['current'] = 'Database replaced';
				break;

			case 'post_swap':
				// Legacy resume: post-swap only (swap already completed).
				self::apply_post_swap_updates( $state );
				break;

			case 'copy_files':
				$manifest = json_decode( file_get_contents( SMIG_STAGING_DIR . '/manifest.json' ), true );
				$files    = $manifest['all_files'] ?? array();

				$batch      = 0;
				$file_count = count( $files );
				while ( $state['file_idx'] < $file_count && $batch < SMIG_FILES_PER_BATCH ) {
					$f    = $files[ $state['file_idx'] ];
					$type = $f['type'];
					$path = ltrim( str_replace( '..', '', $f['path'] ), '/' );
					if ( '' === $path ) {
						++$state['file_idx'];
						++$state['done'];
						++$batch;
						continue;
					}
					$subdir = 'theme' === $type ? 'themes' : $type;
					$src    = SMIG_STAGING_DIR . '/' . $subdir . '/' . $path;

					if ( file_exists( $src ) ) {
						switch ( $type ) {
							case 'uploads':
								$dest = wp_upload_dir()['basedir'] . '/' . $path;
								break;
							case 'theme':
								$dest = get_theme_root() . '/' . $path;
								break;
							case 'plugins':
								$dest = WP_PLUGIN_DIR . '/' . $path;
								break;
							default:
								$dest = null;
						}
						if ( $dest ) {
							wp_mkdir_p( dirname( $dest ) );
							copy( $src, $dest );
						}
					}

					++$state['file_idx'];
					++$state['done'];
					++$batch;
				}

				$state['current'] = 'Copying files… ' . $state['file_idx'] . '/' . count( $files );

				if ( $state['file_idx'] >= count( $files ) ) {
					$state['phase'] = 'swap';
				}
				break;

			case 'install_wporg':
				$queue       = $state['wporg_queue'] ?? array();
				$batch       = 0;
				$wporg_count = count( $queue );
				while ( $state['wporg_idx'] < $wporg_count && $batch < 2 ) {
					$plugin = $queue[ $state['wporg_idx'] ];
					$result = SMIG_Plugin_Strategy::install_from_wporg( $plugin['slug'], $plugin['version'] );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error(
							array(
								'message' => $plugin['slug'] . ': ' . $result->get_error_message(),
							)
						);
					}
					++$state['wporg_idx'];
					++$state['done'];
					$state['current'] = 'WordPress.org: ' . ( $plugin['name'] ?? $plugin['slug'] );
					++$batch;
				}

				if ( $state['wporg_idx'] >= count( $queue ) ) {
					$state['phase'] = 'cleanup';
				}
				break;

			case 'cleanup':
				$all_tables = $wpdb->get_col( 'SHOW TABLES' );
				foreach ( $all_tables as $t ) {
					if ( 0 === strpos( $t, SMIG_STAGING_PREFIX ) ) {
						$wpdb->query( "DROP TABLE IF EXISTS `{$t}`" );
					}
				}

				self::clean_staging();

				++$state['done'];
				$state['current'] = 'Cleanup complete';
				$state['phase']   = 'done';
				break;

			case 'done':
				break;
		}

		set_transient( $akey, $state, 4 * HOUR_IN_SECONDS );

		if ( 'done' === $state['phase'] ) {
			self::clear_resume();
		} else {
			self::sync_resume_from_apply( $state );
		}

		$pct = $state['total'] > 0 ? round( ( $state['done'] / $state['total'] ) * 100 ) : 0;
		wp_send_json_success(
			array(
				'phase'        => $state['phase'],
				'progress'     => min( $pct, 100 ),
				'done'         => $state['done'],
				'total'        => $state['total'],
				'current'      => $state['current'],
				'admin_reset'  => ! empty( $state['users_reset_done'] ),
				'admin_login'  => $state['admin_login'] ?? 'admin',
			)
		);
	}

	/** Public wrapper for activation hook. */
	public static function ensure_staging_secure_public() {
		self::ensure_staging_secure();
	}

	/**
	 * Public wrapper for recovery scripts (bin/reset-admin.php).
	 *
	 * @return int|WP_Error
	 */
	public static function reset_users_to_single_admin_public() {
		return self::reset_users_to_single_admin();
	}

	/**
	 * Site root only — strips /wp-json/... if the REST endpoint was pasted by mistake.
	 */
	/**
	 * @return string|WP_Error
	 */
	private static function normalize_source_url( $url ) {
		return SMIG_Security::validate_source_url( $url );
	}

	private static function assert_valid_table_name( $table ) {
		return SMIG_Security::is_valid_identifier( $table );
	}

	private static function remote( $base_url, $endpoint, $auth, $timeout = 15, $params = array() ) {
		$base_url = self::normalize_source_url( $base_url );
		if ( is_wp_error( $base_url ) ) {
			return $base_url;
		}
		$api_url = trailingslashit( $base_url ) . 'wp-json/site-migrator/v1/' . $endpoint;
		if ( ! empty( $params ) ) {
			$api_url = add_query_arg( $params, $api_url );
		}

		$resp = wp_remote_get(
			$api_url,
			array(
				'headers'   => array( 'X-Migrator-Auth' => $auth ),
				'timeout'   => $timeout,
				'sslverify' => SMIG_Security::sslverify(),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code >= 400 ) {
			$msg = isset( $body['message'] ) ? $body['message'] : 'HTTP ' . $code;
			if ( 404 === (int) $code && isset( $body['code'] ) && 'rest_no_route' === $body['code'] ) {
				$msg .= ' Check that Site Migrator is active on the source site and that the source URL is the site root (e.g. https://example.com), not the API endpoint path.';
			}
			return new WP_Error( 'remote_error', $msg );
		}

		return $body;
	}

	private static function advance_table( $state ) {
		++$state['tbl_idx'];
		$state['tbl_page'] = 1;
		if ( $state['tbl_idx'] >= count( $state['manifest']['tables'] ) ) {
			$state['phase']    = 'files';
			$state['file_idx'] = 0;
		} else {
			$state['phase'] = 'table_schema';
		}
		return $state;
	}

	/**
	 * @param string $sid          Session id.
	 * @param bool   $reset_admin  Replace all users with admin/password after apply.
	 * @return array|WP_Error
	 */
	private static function init_apply_state( $sid, $reset_admin = false ) {
		$manifest_file = SMIG_STAGING_DIR . '/manifest.json';
		if ( ! file_exists( $manifest_file ) ) {
			return new WP_Error( 'no_staging', 'No staging data found. Please download first.' );
		}

		$manifest    = json_decode( file_get_contents( $manifest_file ), true );
		$tables      = $manifest['tables'] ?? array();
		$files       = $manifest['all_files'] ?? array();
		$wporg_queue = $manifest['wporg_queue'] ?? array();

		$total = count( $tables );
		foreach ( $tables as $t ) {
			$total += max( 1, (int) ceil( $t['rows'] / SMIG_ROWS_PER_PAGE ) );
		}
		$total += 2 + 1 + count( $files ) + count( $wporg_queue ) + 1;
		if ( $reset_admin ) {
			++$total;
		}

		$target_urls = self::resolve_target_site_urls( true );

		$state = array(
			'phase'            => 'create_staging',
			'apply_version'    => 2,
			'reset_admin_user' => $reset_admin,
			'tables'         => $tables,
			'src_prefix'     => $manifest['prefix'],
			'src_base_pfx'   => $manifest['base_prefix'],
			'src_site_url'   => $manifest['site_url'],
			'target_siteurl' => $target_urls['siteurl'],
			'target_home'    => $target_urls['home'],
			'wporg_queue'    => $wporg_queue,
			'tbl_idx'      => 0,
			'row_page'     => 1,
			'file_idx'     => 0,
			'wporg_idx'    => 0,
			'done'         => 0,
			'total'        => $total,
			'current'      => '',
		);

		set_transient( 'smig_apply_' . $sid, $state, 4 * HOUR_IN_SECONDS );
		self::save_resume(
			array(
				'apply_started' => true,
				'status'        => 'applying',
			)
		);
		return $state;
	}

	/**
	 * Resume payload for admin.js after page reload.
	 *
	 * @return array|null
	 */
	public static function get_resume_for_client() {
		$resume = get_option( SMIG_RESUME_OPTION );
		if ( ! is_array( $resume ) || empty( $resume['session_id'] ) ) {
			return null;
		}

		$sid           = $resume['session_id'];
		$dl_state      = get_transient( 'smig_session_' . $sid );
		$apply_state   = get_transient( 'smig_apply_' . $sid );
		$manifest_path = SMIG_STAGING_DIR . '/manifest.json';
		$has_staging   = file_exists( $manifest_path );

		if ( ! $has_staging && ! $dl_state && ! $apply_state ) {
			self::clear_resume();
			return null;
		}

		$staged_info       = self::inspect_staged_download();
		$staged_ready      = ! empty( $staged_info['ready'] );
		$expired           = $has_staging && ! $dl_state && ! $apply_state && empty( $resume['download_complete'] ) && ! $staged_ready;

		$manifest = null;
		if ( $has_staging ) {
			$stored = json_decode( file_get_contents( $manifest_path ), true );
			if ( is_array( $stored ) ) {
				$manifest = array(
					'tables'     => $stored['tables'] ?? array(),
					'file_types' => $stored['file_types'] ?? array(),
				);
			}
		}

		$download_complete = ! empty( $resume['download_complete'] );
		if ( $dl_state && 'done' === ( $dl_state['phase'] ?? '' ) ) {
			$download_complete = true;
		}
		if ( $staged_ready && ! $apply_state ) {
			$download_complete = true;
		}

		$progress = (int) ( $resume['progress'] ?? 0 );
		$current  = $resume['current'] ?? '';
		$status   = $resume['status'] ?? 'downloading';

		if ( $dl_state ) {
			$progress = $dl_state['total'] > 0
				? (int) round( ( $dl_state['done'] / $dl_state['total'] ) * 100 )
				: 0;
			$current  = $dl_state['current'] ?? $current;
			$status   = 'done' === ( $dl_state['phase'] ?? '' ) ? 'downloaded' : 'downloading';
		} elseif ( $apply_state ) {
			$progress = $apply_state['total'] > 0
				? (int) round( ( $apply_state['done'] / $apply_state['total'] ) * 100 )
				: 0;
			$current  = $apply_state['current'] ?? $current;
			$status   = 'applying';
		}

		return array(
			'session_id'        => $sid,
			'source_url'        => $resume['source_url'] ?? '',
			'download_complete' => $download_complete,
			'apply_started'     => ! empty( $resume['apply_started'] ) || (bool) $apply_state,
			'status'            => $status,
			'progress'          => $progress,
			'current'           => $current,
			'manifest'          => $manifest,
			'manifest_stats'    => $resume['manifest_stats'] ?? array(),
			'resume_download'   => (bool) $dl_state,
			'resume_apply'      => (bool) $apply_state,
			'expired'           => $expired,
			'staged_ready'      => $staged_ready,
			'can_apply_staged'  => $staged_ready && ! $apply_state,
		);
	}

	private static function save_resume( array $data ) {
		$existing = get_option( SMIG_RESUME_OPTION, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		update_option( SMIG_RESUME_OPTION, array_merge( $existing, $data, array( 'updated' => time() ) ), false );
	}

	private static function clear_resume() {
		delete_option( SMIG_RESUME_OPTION );
	}

	private static function sync_resume_from_download( $state ) {
		$complete = ( 'done' === ( $state['phase'] ?? '' ) );
		$pct      = $state['total'] > 0 ? (int) round( ( $state['done'] / $state['total'] ) * 100 ) : 0;

		self::save_resume(
			array(
				'status'            => $complete ? 'downloaded' : 'downloading',
				'download_complete' => $complete,
				'progress'          => $complete ? 100 : $pct,
				'current'           => $state['current'] ?? '',
			)
		);
	}

	private static function sync_resume_from_apply( $state ) {
		$pct = $state['total'] > 0 ? (int) round( ( $state['done'] / $state['total'] ) * 100 ) : 0;
		self::save_resume(
			array(
				'status'        => 'applying',
				'apply_started' => true,
				'progress'      => min( $pct, 100 ),
				'current'       => $state['current'] ?? '',
			)
		);
	}

	private static function table_suffix( $table, $src_prefix, $src_base_prefix ) {
		if ( $table === $src_base_prefix . 'users' ) {
			return 'users';
		}
		if ( $table === $src_base_prefix . 'usermeta' ) {
			return 'usermeta';
		}
		if ( 0 === strpos( $table, $src_prefix ) ) {
			return substr( $table, strlen( $src_prefix ) );
		}
		return $table;
	}

	/**
	 * URL fixes and prefix repair immediately after the table swap (single atomic DB cutover).
	 *
	 * @param array $state Apply state (by reference).
	 */
	private static function apply_post_swap_updates( &$state ) {
		global $wpdb;

		$target_prefix = $wpdb->prefix;
		$target_urls   = self::get_apply_target_urls( $state );
		$old_url       = untrailingslashit( $state['src_site_url'] );
		$new_siteurl   = $target_urls['siteurl'];
		$new_home      = $target_urls['home'];

		$wpdb->update(
			$target_prefix . 'options',
			array( 'option_value' => $new_siteurl ),
			array( 'option_name' => 'siteurl' )
		);
		$wpdb->update(
			$target_prefix . 'options',
			array( 'option_value' => $new_home ),
			array( 'option_name' => 'home' )
		);

		foreach ( self::url_replace_variants( $old_url ) as $variant ) {
			if ( $variant === $new_siteurl || $variant === $new_home ) {
				continue;
			}
			self::search_replace_table( $target_prefix . 'options', 'option_value', $variant, $new_siteurl );
			self::search_replace_table( $target_prefix . 'posts', 'post_content', $variant, $new_siteurl );
			self::search_replace_table( $target_prefix . 'posts', 'guid', $variant, $new_siteurl );
			self::search_replace_table( $target_prefix . 'postmeta', 'meta_value', $variant, $new_siteurl );
		}

		$src_pfx = $state['src_prefix'];
		if ( $src_pfx !== $target_prefix ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$target_prefix}options` SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
					$src_pfx,
					$target_prefix,
					$wpdb->esc_like( $src_pfx ) . '%'
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$target_prefix}usermeta` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
					$src_pfx,
					$target_prefix,
					$wpdb->esc_like( $src_pfx ) . '%'
				)
			);
		}

		self::repair_administrator_capabilities( $target_prefix );

		$active      = get_option( 'active_plugins', array() );
		$self_plugin = 'site-migrator/site-migrator.php';
		if ( ! is_array( $active ) ) {
			$active = array();
		}
		if ( ! in_array( $self_plugin, $active, true ) ) {
			$active[] = $self_plugin;
			update_option( 'active_plugins', $active );
		}

		++$state['done'];

		$manifest    = json_decode( file_get_contents( SMIG_STAGING_DIR . '/manifest.json' ), true );
		$wporg_queue = $manifest['wporg_queue'] ?? array();
		if ( ! empty( $wporg_queue ) ) {
			$state['phase']       = 'install_wporg';
			$state['wporg_idx']   = 0;
			$state['wporg_queue'] = $wporg_queue;
		} else {
			$state['phase'] = 'cleanup';
		}
	}

	/**
	 * Ensure {prefix}user_roles exists so the administrator role grants real caps (e.g. manage_options).
	 */
	private static function ensure_wordpress_roles() {
		global $wpdb;

		$correct_name = $wpdb->prefix . 'user_roles';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name <> %s",
				'%user_roles',
				$correct_name
			)
		);

		if ( is_array( $orphans ) ) {
			foreach ( $orphans as $row ) {
				$existing = get_option( 'user_roles', null );
				if ( null === $existing || array() === $existing ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$wpdb->options,
						array( 'option_name' => $correct_name ),
						array( 'option_id' => (int) $row->option_id ),
						array( '%s' ),
						array( '%d' )
					);
					wp_cache_delete( 'user_roles', 'options' );
					wp_cache_delete( $row->option_name, 'options' );
					break;
				}
			}
		}

		$roles = get_option( 'user_roles' );
		if ( ! is_array( $roles ) || empty( $roles['administrator']['capabilities'] ) ) {
			delete_option( 'user_roles' );
			if ( ! function_exists( 'populate_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/schema.php';
			}
			populate_roles();
		}

		if ( function_exists( 'wp_roles' ) ) {
			wp_roles()->for_site();
		}
	}

	/**
	 * Grant every capability from the administrator role to a user.
	 *
	 * @param int $user_id User ID.
	 */
	private static function grant_all_administrator_caps( $user_id ) {
		self::ensure_wordpress_roles();

		$role = get_role( 'administrator' );
		if ( ! $role || empty( $role->capabilities ) ) {
			return;
		}

		$user = new WP_User( $user_id );
		foreach ( $role->capabilities as $cap => $grant ) {
			if ( $grant ) {
				$user->add_cap( $cap );
			}
		}
	}

	/**
	 * Ensure imported users have a valid administrator capabilities row for this table prefix.
	 *
	 * @param string $table_prefix Target $wpdb->prefix.
	 */
	private static function repair_administrator_capabilities( $table_prefix ) {
		global $wpdb;

		self::ensure_wordpress_roles();

		$cap_key = $table_prefix . 'capabilities';
		$admins  = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		);

		if ( empty( $admins ) ) {
			$admins = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
					$cap_key,
					'%administrator%'
				)
			);
		}

		foreach ( $admins as $user_id ) {
			$user_id = (int) $user_id;
			$user    = new WP_User( $user_id );
			$user->set_role( 'administrator' );

			if ( ! user_can( $user_id, 'manage_options' ) ) {
				self::grant_all_administrator_caps( $user_id );
			}
		}
	}

	/**
	 * Remove all users and create one administrator (login admin, password password).
	 *
	 * @return int|WP_Error User ID or error.
	 */
	private static function reset_users_to_single_admin() {
		global $wpdb;

		if ( is_multisite() ) {
			return new WP_Error(
				'multisite_reset',
				__( 'Replacing all users is only supported on single-site WordPress installs.', 'site-migrator' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$host  = $host ? $host : 'localhost';
		$email = 'admin@' . preg_replace( '/[^a-zA-Z0-9.-]/', '', $host );

		$user_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users}" );
		if ( is_array( $user_ids ) ) {
			foreach ( $user_ids as $uid ) {
				if ( function_exists( 'wp_delete_user' ) ) {
					wp_delete_user( (int) $uid, true );
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->usermeta}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->users}" );

		// wp_create_user() hashes the plain-text password (WordPress standard).
		$user_id = wp_create_user( 'admin', 'password', $email );

		if ( is_wp_error( $user_id ) ) {
			$existing = get_user_by( 'login', 'admin' );
			if ( ! $existing ) {
				return $user_id;
			}
			$user_id = (int) $existing->ID;
		}

		// Force a fresh hash in case insert was skipped or filtered.
		wp_set_password( 'password', $user_id );

		self::ensure_wordpress_roles();

		$user = new WP_User( $user_id );
		$user->set_role( 'administrator' );

		if ( ! user_can( $user_id, 'manage_options' ) ) {
			self::grant_all_administrator_caps( $user_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_author = %d WHERE post_author > 0", $user_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->comments} SET user_id = %d WHERE user_id > 0", $user_id ) );

		clean_user_cache( $user_id );
		wp_cache_flush();

		return $user_id;
	}

	/**
	 * @param bool $use_db_options When true, read siteurl/home from DB (only safe before swap).
	 * @return array{siteurl: string, home: string}
	 */
	private static function resolve_target_site_urls( $use_db_options = false ) {
		if ( defined( 'WP_SITEURL' ) ) {
			$siteurl = untrailingslashit( WP_SITEURL );
		} elseif ( $use_db_options ) {
			$siteurl = untrailingslashit( (string) get_option( 'siteurl' ) );
		} else {
			$siteurl = self::url_from_current_request();
		}

		if ( defined( 'WP_HOME' ) ) {
			$home = untrailingslashit( WP_HOME );
		} elseif ( $use_db_options ) {
			$home = untrailingslashit( (string) get_option( 'home' ) );
		} else {
			$home = $siteurl;
		}

		if ( '' === $siteurl ) {
			$siteurl = self::url_from_current_request();
		}
		if ( '' === $home ) {
			$home = $siteurl;
		}

		return array(
			'siteurl' => $siteurl,
			'home'    => $home,
		);
	}

	/**
	 * URLs stored at apply start, or from wp-config / request after swap.
	 *
	 * @param array $state Apply transient state.
	 * @return array{siteurl: string, home: string}
	 */
	private static function get_apply_target_urls( $state ) {
		if ( ! empty( $state['target_siteurl'] ) && ! empty( $state['target_home'] ) ) {
			return array(
				'siteurl' => untrailingslashit( $state['target_siteurl'] ),
				'home'    => untrailingslashit( $state['target_home'] ),
			);
		}

		return self::resolve_target_site_urls( false );
	}

	/**
	 * Build site URL from the current admin request (fallback when wp-config has no constants).
	 *
	 * @return string
	 */
	private static function url_from_current_request() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $host ) {
			return '';
		}

		$scheme = is_ssl() ? 'https' : 'http';
		return untrailingslashit( $scheme . '://' . $host );
	}

	/**
	 * Old URL variants to search-replace (http/https, with and without trailing slash).
	 *
	 * @param string $url Source site URL.
	 * @return string[]
	 */
	private static function url_replace_variants( $url ) {
		$url      = untrailingslashit( $url );
		$variants = array( $url, $url . '/' );

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return array_unique( $variants );
		}

		$host = $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . $parts['port'];
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		foreach ( array( 'http', 'https' ) as $scheme ) {
			$variants[] = $scheme . '://' . $host . $path;
			$variants[] = $scheme . '://' . $host . $path . '/';
		}

		return array_values( array_unique( $variants ) );
	}

	private static function search_replace_table( $table, $column, $search, $replace ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return;
		}

		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
		if ( ! in_array( $column, $cols, true ) ) {
			return;
		}

		$pk_col   = null;
		$key_info = $wpdb->get_results( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'", ARRAY_A );
		if ( $key_info ) {
			$pk_col = $key_info[0]['Column_name'];
		}

		if ( ! $pk_col ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, %s, %s) WHERE `{$column}` LIKE %s",
					$search,
					$replace,
					'%' . $wpdb->esc_like( $search ) . '%'
				)
			);
			return;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `{$pk_col}`, `{$column}` FROM `{$table}` WHERE `{$column}` LIKE %s",
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		foreach ( $rows as $row ) {
			$old = $row->$column;
			$new = self::sr_deep( $old, $search, $replace );
			if ( $new !== $old ) {
				$wpdb->update( $table, array( $column => $new ), array( $pk_col => $row->$pk_col ) );
			}
		}
	}

	private static function sr_deep( $data, $search, $replace ) {
		if ( is_serialized( $data ) ) {
			$unserialized = @unserialize( $data );
			if ( false !== $unserialized || 'b:0;' === $data ) {
				$unserialized = self::sr_recursive( $unserialized, $search, $replace );
				return serialize( $unserialized );
			}
		}
		if ( is_string( $data ) ) {
			return str_replace( $search, $replace, $data );
		}
		return $data;
	}

	private static function sr_recursive( $data, $search, $replace ) {
		if ( is_string( $data ) ) {
			return str_replace( $search, $replace, $data );
		}
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$k         = self::sr_recursive( $k, $search, $replace );
				$out[ $k ] = self::sr_recursive( $v, $search, $replace );
			}
			return $out;
		}
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = self::sr_recursive( $v, $search, $replace );
			}
			return $data;
		}
		return $data;
	}

	private static function ensure_staging_secure() {
		wp_mkdir_p( SMIG_STAGING_DIR );

		$index = SMIG_STAGING_DIR . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = SMIG_STAGING_DIR . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}
	}

	private static function clean_staging() {
		if ( is_dir( SMIG_STAGING_DIR ) ) {
			self::rmdir_recursive( SMIG_STAGING_DIR );
		}
	}

	private static function rmdir_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $dir );
	}
}
