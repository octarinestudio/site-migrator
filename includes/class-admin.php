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
						</div>

						<div class="smig-step-panel" id="smig-step-3">
							<h3><?php esc_html_e( 'Confirm and apply', 'site-migrator' ); ?></h3>
							<div class="notice notice-warning inline">
								<p>
									<strong><?php esc_html_e( 'Warning:', 'site-migrator' ); ?></strong>
									<?php esc_html_e( 'This replaces all content on this site with the downloaded data. There is no undo. The current database and files will be overwritten.', 'site-migrator' ); ?>
								</p>
							</div>
							<div id="smig-apply-summary"></div>
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
									<?php
									printf(
										/* translators: %s: link to Settings > Permalinks */
										wp_kses_post( __( 'You may need to sign in again and save permalinks under %s.', 'site-migrator' ) ),
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
			$state = self::init_apply_state( $sid );
			if ( is_wp_error( $state ) ) {
				wp_send_json_error( array( 'message' => $state->get_error_message() ) );
			}
		}

		global $wpdb;

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
					$state['phase'] = 'swap';
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

				$state['done']   += 2;
				$state['current'] = 'Tables swapped';
				$state['phase']   = 'post_swap';
				break;

			case 'post_swap':
				$target_prefix  = $wpdb->prefix;
				$old_url        = untrailingslashit( $state['src_site_url'] );
				$siteurl_option = get_option( 'siteurl' );
				$new_url        = untrailingslashit( $siteurl_option ? $siteurl_option : site_url() );

				if ( defined( 'WP_SITEURL' ) ) {
					$new_url = untrailingslashit( WP_SITEURL );
				} elseif ( defined( 'WP_HOME' ) ) {
					$new_url = untrailingslashit( WP_HOME );
				} else {
					$new_url = untrailingslashit(
						( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' )
					);
				}

				$wpdb->update(
					$target_prefix . 'options',
					array( 'option_value' => $new_url ),
					array( 'option_name' => 'siteurl' )
				);
				$wpdb->update(
					$target_prefix . 'options',
					array( 'option_value' => $new_url ),
					array( 'option_name' => 'home' )
				);

				if ( $old_url !== $new_url ) {
					self::search_replace_table( $target_prefix . 'options', 'option_value', $old_url, $new_url );
					self::search_replace_table( $target_prefix . 'posts', 'post_content', $old_url, $new_url );
					self::search_replace_table( $target_prefix . 'posts', 'guid', $old_url, $new_url );
					self::search_replace_table( $target_prefix . 'postmeta', 'meta_value', $old_url, $new_url );
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
				$state['current']  = 'URLs and prefixes updated';
				$state['phase']    = 'copy_files';
				$state['file_idx'] = 0;
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
					$wporg_queue = $manifest['wporg_queue'] ?? array();
					if ( ! empty( $wporg_queue ) ) {
						$state['phase']       = 'install_wporg';
						$state['wporg_idx']   = 0;
						$state['wporg_queue'] = $wporg_queue;
					} else {
						$state['phase'] = 'cleanup';
					}
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
				'phase'    => $state['phase'],
				'progress' => min( $pct, 100 ),
				'done'     => $state['done'],
				'total'    => $state['total'],
				'current'  => $state['current'],
			)
		);
	}

	/** Public wrapper for activation hook. */
	public static function ensure_staging_secure_public() {
		self::ensure_staging_secure();
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

	private static function init_apply_state( $sid ) {
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

		$state = array(
			'phase'        => 'create_staging',
			'tables'       => $tables,
			'src_prefix'   => $manifest['prefix'],
			'src_base_pfx' => $manifest['base_prefix'],
			'src_site_url' => $manifest['site_url'],
			'wporg_queue'  => $wporg_queue,
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

		$expired = $has_staging && ! $dl_state && ! $apply_state && empty( $resume['download_complete'] );

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
