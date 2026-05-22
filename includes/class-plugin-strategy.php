<?php
/**
 * Plugin transfer strategy: skip local matches, queue WordPress.org installs.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later
 * @link      https://octarinestudio.uk/wordpress-site-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMIG_Plugin_Strategy {

	/**
	 * @param array $plugins_detail From source manifest.
	 * @param array $all_files      Full file queue.
	 * @param array $opts           wporg_install, skip_same_version (bool).
	 * @return array{files: array, wporg_queue: array, skipped: array, pulled_plugin_dirs: array}
	 */
	public static function filter_file_queue( $plugins_detail, $all_files, $opts ) {
		$wporg_install       = ! empty( $opts['wporg_install'] );
		$skip_same_version   = ! empty( $opts['skip_same_version'] );
		$skip_dirs           = array();
		$wporg_queue         = array();
		$skipped             = array();
		$local               = self::local_plugins_by_slug();

		foreach ( $plugins_detail as $plugin ) {
			$slug    = $plugin['slug'];
			$version = $plugin['version'] ?? '';

			if ( $skip_same_version && isset( $local[ $slug ] ) ) {
				$local_ver = $local[ $slug ]['version'];
				if ( $local_ver && $version && version_compare( $local_ver, $version, '>=' ) ) {
					$skip_dirs[] = $slug;
					$skipped[]   = array(
						'slug'    => $slug,
						'name'    => $plugin['name'] ?? $slug,
						'reason'  => 'same_version',
						'version' => $version,
					);
					continue;
				}
			}

			if ( $wporg_install && ! empty( $plugin['wporg'] ) ) {
				$skip_dirs[] = $slug;
				$wporg_queue[] = array(
					'slug'    => $slug,
					'version' => $version,
					'name'    => $plugin['name'] ?? $slug,
					'file'    => $plugin['file'] ?? '',
				);
				continue;
			}
		}

		$skip_dirs = array_unique( $skip_dirs );
		$filtered  = array();

		foreach ( $all_files as $f ) {
			if ( 'plugins' !== $f['type'] ) {
				$filtered[] = $f;
				continue;
			}
			$dir = self::plugin_dir_from_path( $f['path'] );
			if ( in_array( $dir, $skip_dirs, true ) ) {
				continue;
			}
			$filtered[] = $f;
		}

		return array(
			'files'       => $filtered,
			'wporg_queue' => $wporg_queue,
			'skipped'     => $skipped,
			'skip_dirs'   => $skip_dirs,
		);
	}

	/**
	 * Plugin slugs that still need per-file pull from the source (not wp.org / not skipped).
	 *
	 * @return string[]
	 */
	public static function plugins_requiring_file_pull( $plugins_detail, $opts ) {
		$strategy = self::filter_file_queue( $plugins_detail, array(), $opts );
		$skip     = $strategy['skip_dirs'];
		$pull     = array();

		foreach ( $plugins_detail as $plugin ) {
			$slug = $plugin['slug'];
			if ( ! in_array( $slug, $skip, true ) ) {
				$pull[] = $slug;
			}
		}

		return $pull;
	}

	/**
	 * @param array  $files      Plugin file entries from files-list.
	 * @param string[] $pull_slugs Directory slugs to include.
	 * @return array
	 */
	public static function filter_plugin_files_by_slug( $files, $pull_slugs ) {
		if ( empty( $pull_slugs ) ) {
			return array();
		}
		$out = array();
		foreach ( $files as $f ) {
			$dir = self::plugin_dir_from_path( $f['relative_path'] ?? $f['path'] ?? '' );
			if ( in_array( $dir, $pull_slugs, true ) ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	/**
	 * Install one plugin from WordPress.org.
	 *
	 * @return true|WP_Error
	 */
	public static function install_from_wporg( $slug, $version ) {
		self::load_upgrader_deps();

		$slug    = sanitize_key( $slug );
		$package = self::wporg_zip_url( $slug, $version );

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package );

		if ( ! $result || is_wp_error( $result ) ) {
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				return new WP_Error(
					'wporg_install_failed',
					sprintf( 'Could not install %s from WordPress.org.', $slug )
				);
			}
			$result = $upgrader->install( $api->download_link );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'wporg_install_failed', sprintf( 'Install failed for %s.', $slug ) );
		}

		return true;
	}

	/**
	 * Build plugin metadata list on the source site.
	 *
	 * @param array $active_plugins Plugin basenames.
	 * @return array
	 */
	public static function build_plugins_detail( $active_plugins ) {
		self::load_plugin_deps();

		$details = array();
		$seen    = array();

		foreach ( $active_plugins as $plugin_file ) {
			if ( false !== strpos( $plugin_file, 'site-migrator' ) ) {
				continue;
			}

			$slug = self::slug_from_plugin_file( $plugin_file );
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;

			$full = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! is_readable( $full ) ) {
				continue;
			}

			$data    = get_plugin_data( $full, false, false );
			$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';

			$details[] = array(
				'file'    => $plugin_file,
				'slug'    => $slug,
				'version' => $version,
				'name'    => isset( $data['Name'] ) ? (string) $data['Name'] : $slug,
				'wporg'   => self::is_on_wordpress_org( $slug ),
			);
		}

		return $details;
	}

	public static function is_on_wordpress_org( $slug ) {
		$cache_key = 'smig_wporg_' . sanitize_key( $slug );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		self::load_plugin_deps();

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => sanitize_key( $slug ),
				'fields' => array( 'sections' => false ),
			)
		);

		$ok = ! is_wp_error( $api ) && ! empty( $api->slug );
		set_transient( $cache_key, $ok ? 1 : 0, DAY_IN_SECONDS );

		return $ok;
	}

	private static function wporg_zip_url( $slug, $version ) {
		if ( $version ) {
			return sprintf(
				'https://downloads.wordpress.org/plugin/%s.%s.zip',
				$slug,
				preg_replace( '/[^0-9a-zA-Z._-]/', '', $version )
			);
		}
		return sprintf( 'https://downloads.wordpress.org/plugin/%s.zip', $slug );
	}

	private static function slug_from_plugin_file( $plugin_file ) {
		$dir = dirname( $plugin_file );
		if ( '.' === $dir ) {
			return basename( $plugin_file, '.php' );
		}
		return $dir;
	}

	private static function plugin_dir_from_path( $path ) {
		$path = ltrim( $path, '/' );
		if ( false !== strpos( $path, '/' ) ) {
			return explode( '/', $path )[0];
		}
		return basename( $path, '.php' );
	}

	/**
	 * @return array<string, array{version: string, file: string}>
	 */
	private static function local_plugins_by_slug() {
		self::load_plugin_deps();

		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$map = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug          = self::slug_from_plugin_file( $file );
			$map[ $slug ] = array(
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'file'    => $file,
			);
		}

		return $map;
	}

	private static function load_plugin_deps() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
	}

	private static function load_upgrader_deps() {
		self::load_plugin_deps();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}
}
