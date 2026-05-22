<?php
/**
 * Security helpers for Site Migrator.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license   GPL-3.0-or-later
 * @link      https://octarinestudio.uk/wordpress-site-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMIG_Security {

	const RATE_LIMIT_MAX = 120;

	/**
	 * Validate a MySQL table or column identifier.
	 */
	public static function is_valid_identifier( $name ) {
		return is_string( $name ) && (bool) preg_match( '/^[a-zA-Z0-9_]+$/', $name );
	}

	/**
	 * Safe filename fragment for staging files derived from table names.
	 */
	public static function safe_table_filename( $table ) {
		if ( ! self::is_valid_identifier( $table ) ) {
			return false;
		}
		return $table;
	}

	/**
	 * Validate remote site URL (SSRF mitigation).
	 *
	 * @return string|WP_Error Normalized URL or error.
	 */
	public static function validate_source_url( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$url     = untrailingslashit( $url );
		$wp_json = strpos( $url, '/wp-json' );
		if ( false !== $wp_json ) {
			$url = untrailingslashit( substr( $url, 0, $wp_json ) );
		}

		if ( function_exists( 'wp_http_validate_url' ) ) {
			$validated = wp_http_validate_url( $url );
			if ( ! $validated ) {
				return new WP_Error( 'invalid_url', __( 'Source URL is not allowed.', 'site-migrator' ) );
			}
			$url = untrailingslashit( $validated );
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return new WP_Error( 'invalid_url', __( 'Source URL is not allowed.', 'site-migrator' ) );
		}

		return $url;
	}

	/**
	 * Whether outbound HTTPS requests should verify SSL certificates.
	 */
	public static function sslverify() {
		/**
		 * Filter SSL certificate verification for outbound migration HTTP requests.
		 *
		 * @param bool $sslverify Default true.
		 */
		return (bool) apply_filters( 'smig_http_sslverify', true );
	}

	/**
	 * Basic per-IP rate limit for source REST API (auth attempts / requests).
	 *
	 * @return true|WP_Error
	 */
	public static function check_rate_limit() {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'smig_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'rest_rate_limited',
				__( 'Too many requests. Try again later.', 'site-migrator' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Generic error for JSON responses; details only when WP_DEBUG.
	 *
	 * @param string $message User-facing message.
	 * @param string $detail  Optional debug detail.
	 */
	public static function public_error_message( $message, $detail = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $detail ) {
			return $message . ' ' . $detail;
		}
		return $message;
	}
}
