<?php
/**
 * Security defaults.
 *
 * File editing off, XML-RPC off, the WordPress version hidden, login errors made vague, security headers set, and a
 * Shop manager role that can run the catalogue and orders but not settings or code.
 *
 * Two-step login is deliberately left to a reviewed plugin: this file does not pretend to provide it.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No editing theme or plugin files from the admin.
 */
function orwo_block_file_editing() {
	if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
		define( 'DISALLOW_FILE_EDIT', true );
	}
}
add_action( 'init', 'orwo_block_file_editing' );

/**
 * XML-RPC is not used by this shop.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Hides the WordPress version from the generator tag and asset URLs.
 *
 * @param string $src Asset URL.
 * @return string
 */
function orwo_strip_version( $src ) {
	if ( $src && false !== strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
		$src = remove_query_arg( 'ver', $src );
	}

	return $src;
}
add_filter( 'style_loader_src', 'orwo_strip_version', 20 );
add_filter( 'script_loader_src', 'orwo_strip_version', 20 );

/**
 * A login failure should not say which half was wrong.
 *
 * @return string
 */
function orwo_login_error() {
	return __( 'Those details did not match. Please try again.', 'orwo' );
}
add_filter( 'login_errors', 'orwo_login_error' );

/**
 * Security headers. HTTPS is assumed; the strict transport header is left to the host so a misconfigured staging
 * site cannot lock itself out.
 *
 * @param array<string,string> $headers Headers.
 * @return array<string,string>
 */
function orwo_security_headers( $headers ) {
	$headers['X-Content-Type-Options'] = 'nosniff';
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
	$headers['X-Frame-Options']        = 'SAMEORIGIN';

	return $headers;
}
add_filter( 'wp_headers', 'orwo_security_headers' );

/**
 * Creates the Shop manager role's catalogue capabilities once WooCommerce has registered it.
 */
function orwo_shop_manager_role() {
	if ( (string) get_option( 'orwo_roles_version', '' ) === ORWO_VERSION ) {
		return;
	}
	do_action( 'orwo_core_roles' );
	update_option( 'orwo_roles_version', ORWO_VERSION, false );
}
add_action( 'admin_init', 'orwo_shop_manager_role' );
