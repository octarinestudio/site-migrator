<?php
/**
 * Tests for SMIG_Plugin_Strategy.
 *
 * @package Site_Migrator
 */

/**
 * Plugin strategy tests.
 */
class PluginStrategyTest extends SMIG_TestCase {

	public function setUp(): void {
		parent::setUp();
		$GLOBALS['smig_test_plugins'] = array();
	}

	public function test_filter_file_queue_queues_wporg_install_and_removes_plugin_files() {
		$plugins_detail = array(
			array(
				'slug'    => 'akismet',
				'version' => '5.3',
				'name'    => 'Akismet',
				'wporg'   => true,
				'file'    => 'akismet/akismet.php',
			),
		);

		$all_files = array(
			array(
				'type' => 'plugins',
				'path' => 'akismet/akismet.php',
			),
			array(
				'type' => 'plugins',
				'path' => 'custom-plugin/custom-plugin.php',
			),
			array(
				'type' => 'uploads',
				'path' => '2024/01/photo.jpg',
			),
		);

		$result = SMIG_Plugin_Strategy::filter_file_queue(
			$plugins_detail,
			$all_files,
			array(
				'wporg_install'     => true,
				'skip_same_version' => false,
			)
		);

		$this->assertCount( 1, $result['wporg_queue'] );
		$this->assertSame( 'akismet', $result['wporg_queue'][0]['slug'] );
		$this->assertSame( 'akismet', $result['skip_dirs'][0] );

		$paths = array_map(
			static function ( $file ) {
				return $file['path'];
			},
			$result['files']
		);
		$this->assertContains( 'custom-plugin/custom-plugin.php', $paths );
		$this->assertContains( '2024/01/photo.jpg', $paths );
		$this->assertNotContains( 'akismet/akismet.php', $paths );
	}

	public function test_filter_file_queue_skips_same_version_when_local_matches() {
		$GLOBALS['smig_test_plugins'] = array(
			'akismet/akismet.php' => array( 'Version' => '5.3' ),
		);

		$plugins_detail = array(
			array(
				'slug'    => 'akismet',
				'version' => '5.3',
				'name'    => 'Akismet',
				'wporg'   => false,
			),
		);

		$all_files = array(
			array(
				'type' => 'plugins',
				'path' => 'akismet/readme.txt',
			),
		);

		$result = SMIG_Plugin_Strategy::filter_file_queue(
			$plugins_detail,
			$all_files,
			array(
				'wporg_install'     => false,
				'skip_same_version' => true,
			)
		);

		$this->assertEmpty( $result['files'] );
		$this->assertCount( 1, $result['skipped'] );
		$this->assertSame( 'same_version', $result['skipped'][0]['reason'] );
	}

	public function test_plugins_requiring_file_pull_excludes_skipped_slugs() {
		$plugins_detail = array(
			array(
				'slug'    => 'akismet',
				'version' => '5.3',
				'wporg'   => true,
			),
			array(
				'slug'    => 'custom-plugin',
				'version' => '1.0',
				'wporg'   => false,
			),
		);

		$pull = SMIG_Plugin_Strategy::plugins_requiring_file_pull(
			$plugins_detail,
			array(
				'wporg_install'     => true,
				'skip_same_version' => false,
			)
		);

		$this->assertSame( array( 'custom-plugin' ), $pull );
	}

	public function test_filter_plugin_files_by_slug_returns_only_matching_dirs() {
		$files = array(
			array( 'relative_path' => 'akismet/akismet.php' ),
			array( 'relative_path' => 'custom-plugin/custom-plugin.php' ),
		);

		$filtered = SMIG_Plugin_Strategy::filter_plugin_files_by_slug( $files, array( 'custom-plugin' ) );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'custom-plugin/custom-plugin.php', $filtered[0]['relative_path'] );
	}

	public function test_filter_plugin_files_by_slug_returns_empty_when_no_slugs() {
		$files = array(
			array( 'relative_path' => 'akismet/akismet.php' ),
		);

		$this->assertSame( array(), SMIG_Plugin_Strategy::filter_plugin_files_by_slug( $files, array() ) );
	}

	public function test_filter_plugin_files_by_slug_handles_single_file_plugin_path() {
		$files = array(
			array( 'path' => 'hello.php' ),
		);

		$filtered = SMIG_Plugin_Strategy::filter_plugin_files_by_slug( $files, array( 'hello' ) );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'hello.php', $filtered[0]['path'] );
	}
}
