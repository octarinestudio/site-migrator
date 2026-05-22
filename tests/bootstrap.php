<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Site_Migrator
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/phpstan-bootstrap.php';
require_once __DIR__ . '/TestCase.php';

$GLOBALS['smig_test_plugins'] = array();
$GLOBALS['smig_options']      = array();

// Minimal WordPress stubs for unit tests.
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for unit tests.
	 */
	class WP_Error {
		/**
		 * @var array<string, array<int, string>>
		 */
		public $errors = array();

		/**
		 * @var array<string, mixed>
		 */
		public $error_data = array();

		/**
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Optional error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ (string) $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ (string) $code ] = $data;
				}
			}
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			$code = $this->get_error_code();
			if ( isset( $this->errors[ $code ][0] ) ) {
				return $this->errors[ $code ][0];
			}
			return '';
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes ? (string) $codes[0] : '';
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function untrailingslashit( $url ) {
		return rtrim( $url, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Parse component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	/**
	 * @param string $url URL.
	 * @return string|false
	 */
	function wp_http_validate_url( $url ) {
		if ( array_key_exists( 'smig_wp_http_validate_url', $GLOBALS ) ) {
			return $GLOBALS['smig_wp_http_validate_url']( $url );
		}
		return $url;
	}
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	/**
	 * @param string $file Plugin file.
	 * @return array<string, mixed>
	 */
	function get_plugin_data( $file ) {
		return array();
	}
}

if ( ! function_exists( 'plugins_api' ) ) {
	/**
	 * @return WP_Error
	 */
	function plugins_api() {
		return new WP_Error( 'no_api', 'Not available in tests.' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		if ( array_key_exists( $option, $GLOBALS['smig_options'] ) ) {
			return $GLOBALS['smig_options'][ $option ];
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		$GLOBALS['smig_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		unset( $GLOBALS['smig_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * @return array<string, array<string, mixed>>
	 */
	function get_plugins() {
		return $GLOBALS['smig_test_plugins'];
	}
}

require_once dirname( __DIR__ ) . '/includes/class-security.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin-strategy.php';
require_once dirname( __DIR__ ) . '/includes/class-admin.php';
