<?php
/**
 * Tests for pull endpoint enabled gate.
 *
 * @package Site_Migrator
 */

/**
 * Endpoint setting tests.
 */
class EndpointTest extends SMIG_TestCase {

	public function setUp(): void {
		parent::setUp();
		unset( $GLOBALS['smig_options'][ SMIG_ENDPOINT_OPTION ] );
	}

	public function test_is_pull_endpoint_enabled_defaults_to_false() {
		$this->assertFalse( SMIG_Admin::is_pull_endpoint_enabled() );
	}

	public function test_is_pull_endpoint_enabled_when_option_set() {
		update_option( SMIG_ENDPOINT_OPTION, '1' );
		$this->assertTrue( SMIG_Admin::is_pull_endpoint_enabled() );
	}
}
