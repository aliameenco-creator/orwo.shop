<?php
/**
 * Ship less code.
 *
 * No emoji script, no oEmbed discovery, no generator tags, no classic-theme button stylesheet, and WooCommerce's own
 * styles, scripts and cart-fragment refresh only on the pages that genuinely need them.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes the head output nothing here uses.
 */
function orwo_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
}
add_action( 'init', 'orwo_trim_head' );
add_filter( 'emoji_svg_url', '__return_false' );

/**
 * Only the block styles a page actually uses, inlined while small.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
add_filter(
	'styles_inline_size_limit',
	static function () {
		return 40000;
	}
);

/**
 * Drops the stylesheets and scripts the shop does not need.
 */
function orwo_dequeue_extras() {
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_script( 'comment-reply' );

	if ( ! orwo_woo_page() ) {
		// Browsing pages: none of WooCommerce's own CSS or JS, and no cart-fragment refresh.
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'brands-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		foreach ( array( 'wc-cart-fragments', 'woocommerce', 'wc-add-to-cart', 'wc-cart', 'wc-checkout', 'js-cookie', 'wc-order-attribution' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'orwo_dequeue_extras', 99 );

/**
 * Whether this request is one of WooCommerce's own pages, where its assets belong.
 *
 * @return bool
 */
function orwo_woo_page() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	if ( is_cart() || is_checkout() || is_account_page() ) {
		return true;
	}

	// A variable product needs WooCommerce's variation script, which is the one place jQuery still loads.
	if ( is_product() ) {
		$product = wc_get_product( get_queried_object_id() );

		return $product instanceof WC_Product && $product->is_type( 'variable' );
	}

	return false;
}

/**
 * Pages are not a comment destination in this shop.
 *
 * @param bool $open    Whether comments are open.
 * @param int  $post_id Post ID.
 * @return bool
 */
function orwo_close_comments( $open, $post_id ) {
	return in_array( get_post_type( $post_id ), array( 'page', 'product', 'attachment' ), true ) ? false : $open;
}
add_filter( 'comments_open', 'orwo_close_comments', 10, 2 );
