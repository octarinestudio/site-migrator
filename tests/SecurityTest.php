<?php
/**
 * Tests for SMIG_Security.
 *
 * @package Site_Migrator
 */

/**
 * Security helper tests.
 */
class SecurityTest extends SMIG_TestCase {

	/**
	 * @dataProvider valid_identifiers
	 * @param string $name Identifier.
	 */
	public function test_is_valid_identifier_accepts_safe_names( $name ) {
		$this->assertTrue( SMIG_Security::is_valid_identifier( $name ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function valid_identifiers() {
		return array(
			'letters' => array( 'wp_posts' ),
			'digits'  => array( 'table123' ),
			'mixed'   => array( 'wp_2_comments' ),
		);
	}

	/**
	 * @dataProvider invalid_identifiers
	 * @param mixed $name Identifier.
	 */
	public function test_is_valid_identifier_rejects_unsafe_names( $name ) {
		$this->assertFalse( SMIG_Security::is_valid_identifier( $name ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function invalid_identifiers() {
		return array(
			'hyphen'     => array( 'wp-posts' ),
			'space'      => array( 'wp posts' ),
			'semicolon'  => array( 'wp_posts; DROP' ),
			'empty'      => array( '' ),
			'non_string' => array( null ),
		);
	}

	public function test_safe_table_filename_returns_table_for_valid_identifier() {
		$this->assertSame( 'wp_options', SMIG_Security::safe_table_filename( 'wp_options' ) );
	}

	public function test_safe_table_filename_returns_false_for_invalid_identifier() {
		$this->assertFalse( SMIG_Security::safe_table_filename( 'bad-name' ) );
	}

	public function test_validate_source_url_adds_https_scheme() {
		unset( $GLOBALS['smig_wp_http_validate_url'] );

		$result = SMIG_Security::validate_source_url( 'example.com' );
		$this->assertSame( 'https://example.com', $result );
	}

	public function test_validate_source_url_strips_wp_json_suffix() {
		unset( $GLOBALS['smig_wp_http_validate_url'] );

		$result = SMIG_Security::validate_source_url( 'https://example.com/wp-json/site-migrator/v1' );
		$this->assertSame( 'https://example.com', $result );
	}

	public function test_validate_source_url_returns_error_when_host_missing() {
		$GLOBALS['smig_wp_http_validate_url'] = static function () {
			return 'https://';
		};

		$result = SMIG_Security::validate_source_url( 'https://' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_url', $result->get_error_code() );
	}

	public function test_validate_source_url_returns_error_when_wp_http_validate_url_fails() {
		$GLOBALS['smig_wp_http_validate_url'] = static function () {
			return false;
		};

		$result = SMIG_Security::validate_source_url( 'https://evil.internal' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_public_error_message_hides_detail_without_debug() {
		if ( defined( 'WP_DEBUG' ) ) {
			$this->markTestSkipped( 'WP_DEBUG is defined in this environment.' );
		}

		$message = SMIG_Security::public_error_message( 'Something failed.', 'SQL detail' );
		$this->assertSame( 'Something failed.', $message );
	}

	public function test_public_error_message_appends_detail_when_debug_enabled() {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		$message = SMIG_Security::public_error_message( 'Something failed.', 'SQL detail' );
		$this->assertSame( 'Something failed. SQL detail', $message );
	}
}
