<?php
/**
 * Source-side REST API.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later
 * @link      https://octarinestudio.uk/wordpress-site-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMIG_Source_API {

	const NAMESPACE = 'site-migrator/v1';

	/**
	 * Route registration.
	 */
	public function register_routes() {
		$auth = array( $this, 'check_auth' );

		register_rest_route(
			self::NAMESPACE,
			'/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_verify' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_manifest' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/table-schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_table_schema' ),
				'permission_callback' => $auth,
				'args'                => array(
					'table' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/table-rows',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_table_rows' ),
				'permission_callback' => $auth,
				'args'                => array(
					'table'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => SMIG_ROWS_PER_PAGE,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/files-list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_files_list' ),
				'permission_callback' => $auth,
				'args'                => array(
					'type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/file-content',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'endpoint_file_content' ),
				'permission_callback' => $auth,
				'args'                => array(
					'type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'path' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Auth.
	 */
	public function check_auth( $request ) {
		if ( ! SMIG_Admin::is_pull_endpoint_enabled() ) {
			return new WP_Error(
				'smig_endpoint_disabled',
				__( 'Migration pull endpoint is disabled on this site. Enable it under Tools → Site Migrator before pulling.', 'site-migrator' ),
				array( 'status' => 403 )
			);
		}

		$rate = SMIG_Security::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$token  = $request->get_header( 'X-Migrator-Auth' );
		$stored = get_option( 'smig_auth_code' );
		if ( ! is_string( $token ) || ! is_string( $stored ) || '' === $stored || ! hash_equals( $stored, $token ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid auth code.', 'site-migrator' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * /verify.
	 */
	public function endpoint_verify() {
		global $wpdb;
		return rest_ensure_response(
			array(
				'success'      => true,
				'site_name'    => get_bloginfo( 'name' ),
				'site_url'     => get_site_url(),
				'is_multisite' => is_multisite(),
				'blog_id'      => get_current_blog_id(),
				'wp_version'   => get_bloginfo( 'version' ),
				'table_prefix' => $wpdb->prefix,
				'base_prefix'  => $wpdb->base_prefix,
			)
		);
	}

	/**
	 * /manifest.
	 */
	public function endpoint_manifest() {
		global $wpdb;

		// Tables.
		$prefix = $wpdb->prefix;
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		// Exclude multisite-only tables when on blog 1.
		if ( is_multisite() && 1 === get_current_blog_id() ) {
			$exclude = array(
				$wpdb->base_prefix . 'blogs',
				$wpdb->base_prefix . 'blog_versions',
				$wpdb->base_prefix . 'site',
				$wpdb->base_prefix . 'sitemeta',
				$wpdb->base_prefix . 'registration_log',
				$wpdb->base_prefix . 'signups',
				$wpdb->base_prefix . 'blogmeta',
			);
			$tables  = array_values( array_diff( $tables, $exclude ) );
		}

		// Shared user tables.
		$users_t = $wpdb->base_prefix . 'users';
		$umeta_t = $wpdb->base_prefix . 'usermeta';
		if ( ! in_array( $users_t, $tables, true ) ) {
			$tables[] = $users_t;
		}
		if ( ! in_array( $umeta_t, $tables, true ) ) {
			$tables[] = $umeta_t;
		}

		$table_info = array();
		foreach ( $tables as $t ) {
			$rows         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
			$table_info[] = array(
				'name' => $t,
				'rows' => $rows,
			);
		}

		// Files.
		$file_types = array();
		foreach ( array( 'uploads', 'theme', 'plugins' ) as $type ) {
			$list       = $this->scan_files_for_type( $type );
			$total_size = array_sum( array_column( $list, 'size' ) );

			$extra = array();
			if ( 'theme' === $type ) {
				$extra['stylesheet'] = get_stylesheet();
				$extra['template']   = get_template();
				$extra['name']       = wp_get_theme()->get( 'Name' );
			}
			if ( 'plugins' === $type ) {
				$active           = $this->get_all_active_plugins();
				$extra['active']  = $active;
				$extra['plugins'] = SMIG_Plugin_Strategy::build_plugins_detail( $active );
			}

			$file_types[ $type ] = array_merge(
				array(
					'count' => count( $list ),
					'size'  => $total_size,
				),
				$extra
			);

			// Cache for files-list endpoint.
			set_transient( 'smig_flist_' . $type . '_' . get_current_blog_id(), $list, HOUR_IN_SECONDS );
		}

		return rest_ensure_response(
			array(
				'tables'      => $table_info,
				'file_types'  => $file_types,
				'prefix'      => $prefix,
				'base_prefix' => $wpdb->base_prefix,
				'site_url'    => get_site_url(),
			)
		);
	}

	/**
	 * /table-schema.
	 */
	public function endpoint_table_schema( $request ) {
		global $wpdb;

		$table = $request->get_param( 'table' );
		if ( ! SMIG_Security::is_valid_identifier( $table ) || ! $this->is_allowed_table( $table ) ) {
			return new WP_Error( 'invalid_table', __( 'Table not allowed.', 'site-migrator' ), array( 'status' => 403 ) );
		}

		$row = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Table does not exist.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'table'      => $table,
				'create_sql' => $row[1],
			)
		);
	}

	/**
	 * /table-rows.
	 */
	public function endpoint_table_rows( $request ) {
		global $wpdb;

		$table    = $request->get_param( 'table' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 2000, max( 1, (int) $request->get_param( 'per_page' ) ) );

		if ( ! SMIG_Security::is_valid_identifier( $table ) || ! $this->is_allowed_table( $table ) ) {
			return new WP_Error( 'invalid_table', __( 'Table not allowed.', 'site-migrator' ), array( 'status' => 403 ) );
		}

		$total_rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
		$offset      = ( $page - 1 ) * $per_page;

		$columns  = array();
		$col_info = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		foreach ( $col_info as $c ) {
			if ( SMIG_Security::is_valid_identifier( $c['Field'] ) ) {
				$columns[] = $c['Field'];
			}
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_N
		);

		return rest_ensure_response(
			array(
				'table'       => $table,
				'columns'     => $columns,
				'rows'        => $rows ? $rows : array(),
				'page'        => $page,
				'total_pages' => $total_pages,
				'total_rows'  => $total_rows,
			)
		);
	}

	/**
	 * /files-list.
	 */
	public function endpoint_files_list( $request ) {
		$type = $request->get_param( 'type' );
		if ( ! in_array( $type, array( 'uploads', 'theme', 'plugins' ), true ) ) {
			return new WP_Error( 'invalid_type', 'Invalid file type.', array( 'status' => 400 ) );
		}

		$cache_key = 'smig_flist_' . $type . '_' . get_current_blog_id();
		$list      = get_transient( $cache_key );
		if ( false === $list ) {
			$list = $this->scan_files_for_type( $type );
			set_transient( $cache_key, $list, HOUR_IN_SECONDS );
		}

		return rest_ensure_response(
			array(
				'type'        => $type,
				'files'       => $list,
				'total_files' => count( $list ),
			)
		);
	}

	/**
	 * /file-content.
	 */
	public function endpoint_file_content( $request ) {
		$type = $request->get_param( 'type' );
		$path = $request->get_param( 'path' );

		// Sanitise path — block traversal.
		$path = str_replace( '..', '', $path );
		$path = ltrim( $path, '/' );

		$base = $this->base_dir_for_type( $type );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$full = $base . '/' . $path;
		$real = realpath( $full );

		if ( ! $real || 0 !== strpos( $real, realpath( $base ) ) ) {
			return new WP_Error( 'invalid_path', 'Path outside allowed directory.', array( 'status' => 403 ) );
		}
		if ( ! is_file( $real ) ) {
			return new WP_Error( 'not_found', 'File not found.', array( 'status' => 404 ) );
		}

		$size = filesize( $real );
		if ( $size > SMIG_MAX_FILE_SIZE ) {
			return new WP_Error( 'too_large', 'File exceeds 25 MB limit.', array( 'status' => 413 ) );
		}

		return rest_ensure_response(
			array(
				'content' => base64_encode( file_get_contents( $real ) ),
				'size'    => $size,
			)
		);
	}

	/**
	 * Helpers.
	 */
	private function is_allowed_table( $table ) {
		global $wpdb;

		// Must start with the site prefix OR the base prefix (users/usermeta).
		$ok = 0 === strpos( $table, $wpdb->prefix )
			|| $table === $wpdb->base_prefix . 'users'
			|| $table === $wpdb->base_prefix . 'usermeta';

		if ( ! $ok ) {
			return false;
		}

		// Must actually exist.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table )
			)
		);

		return (bool) $exists;
	}

	private function base_dir_for_type( $type ) {
		switch ( $type ) {
			case 'uploads':
				return wp_upload_dir()['basedir'];
			case 'theme':
				return get_theme_root();
			case 'plugins':
				return WP_PLUGIN_DIR;
			default:
				return new WP_Error( 'invalid_type', 'Unknown type.', array( 'status' => 400 ) );
		}
	}

	private function get_all_active_plugins() {
		$active = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			$active  = array_merge( $active, array_keys( $network ) );
		}
		return array_unique( $active );
	}

	/**
	 * Recursively scan a directory and return relative paths + sizes.
	 */
	private function scan_files_for_type( $type ) {
		$files = array();

		switch ( $type ) {
			case 'uploads':
				$base = wp_upload_dir()['basedir'];
				if ( is_dir( $base ) ) {
					$files = $this->scan_dir( $base, '' );
				}
				break;

			case 'theme':
				$child      = get_stylesheet_directory();
				$parent     = get_template_directory();
				$child_name = basename( $child );
				$files      = $this->scan_dir( $child, $child_name );
				if ( $child !== $parent ) {
					$parent_name = basename( $parent );
					$files       = array_merge( $files, $this->scan_dir( $parent, $parent_name ) );
				}
				break;

			case 'plugins':
				$active  = $this->get_all_active_plugins();
				$scanned = array();
				foreach ( $active as $pf ) {
					$dir_name = dirname( $pf );
					if ( SMIG_Plugin_Strategy::SELF_PLUGIN_SLUG === $dir_name ) {
						continue; // Don't transfer ourselves.
					}
					if ( '.' === $dir_name ) {
						// Single-file plugin.
						$full = WP_PLUGIN_DIR . '/' . $pf;
						if ( is_file( $full ) ) {
							$files[] = array(
								'relative_path' => basename( $pf ),
								'size'          => filesize( $full ),
							);
						}
					} else {
						if ( in_array( $dir_name, $scanned, true ) ) {
							continue;
						}
						$scanned[] = $dir_name;
						$full      = WP_PLUGIN_DIR . '/' . $dir_name;
						if ( is_dir( $full ) ) {
							$files = array_merge( $files, $this->scan_dir( $full, $dir_name ) );
						}
					}
				}
				break;
		}

		return $files;
	}

	private function scan_dir( $dir, $prefix ) {
		$out = array();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$rel   = str_replace( $dir . '/', '', $file->getPathname() );
			$path  = '' !== $prefix ? $prefix . '/' . $rel : $rel;
			$out[] = array(
				'relative_path' => $path,
				'size'          => $file->getSize(),
			);
		}

		return $out;
	}
}
