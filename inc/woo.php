<?php
/**
 * WooCommerce, kept visible.
 *
 * Cart, checkout, account and order pages stay WooCommerce's own, restyled by this theme. What this file does is
 * remove the parts of WooCommerce's own catalogue chrome we replace (its loop, breadcrumb, sidebar and gallery
 * scripts), and add the tiny cart-count endpoint that lets browsing pages stay cached.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strips the WooCommerce catalogue chrome this theme replaces.
 */
function orwo_unhook_woo() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
}
add_action( 'init', 'orwo_unhook_woo' );

/**
 * Twenty-four products per page, matching the filter panel's own paging.
 *
 * @return int
 */
function orwo_loop_columns() {
	return 4;
}
add_filter( 'loop_shop_columns', 'orwo_loop_columns' );

/**
 * The shop page uses the same page size as every other catalogue view.
 *
 * @return int
 */
function orwo_products_per_page() {
	return defined( 'ORWO_PER_PAGE' ) ? ORWO_PER_PAGE : 24;
}
add_filter( 'loop_shop_per_page', 'orwo_products_per_page', 20 );

/**
 * High performance order storage is expected on this shop; this notice is the reminder if it is ever switched off.
 */
function orwo_hpos_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
		return;
	}
	if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'appearance_page_orwo-setup' ), true ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'WooCommerce high performance order storage is switched off.', 'orwo' ); ?></strong>
			<?php esc_html_e( 'Switch it on under WooCommerce → Settings → Advanced → Features to keep orders fast as the shop grows.', 'orwo' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'orwo_hpos_notice' );

/**
 * The cart-count endpoint. A browsing page is served from the cache with no count rendered into it; this returns the
 * count as a tiny JSON response, and the script only asks for it when a cart cookie exists.
 *
 * @return string
 */
function orwo_cart_endpoint() {
	return esc_url_raw( rest_url( 'orwo/v1/cart' ) );
}

/**
 * Registers the cart-count endpoint.
 */
function orwo_register_cart_route() {
	register_rest_route(
		'orwo/v1',
		'/cart',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'orwo_rest_cart',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'orwo_register_cart_route' );

/**
 * The number of items in the visitor's cart and its total.
 *
 * @return WP_REST_Response
 */
function orwo_rest_cart() {
	$response = array(
		'count' => 0,
		'total' => '',
	);

	if ( ! function_exists( 'WC' ) ) {
		return new WP_REST_Response( $response );
	}

	if ( ! WC()->cart ) {
		wc_load_cart();
	}
	if ( WC()->cart ) {
		$response['count'] = (int) WC()->cart->get_cart_contents_count();
		$response['total'] = wp_strip_all_tags( (string) WC()->cart->get_cart_subtotal() );
	}

	$result = new WP_REST_Response( $response );
	$result->header( 'Cache-Control', 'no-store, private' );

	return $result;
}

/**
 * Our own product gallery markup: the main image plus thumbnails, no flexslider, no zoom script, no jQuery.
 *
 * @param int $product_id Product ID.
 */
function orwo_product_gallery( $product_id ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	$ids     = array();

	$thumbnail_id = (int) get_post_thumbnail_id( $product_id );
	if ( $thumbnail_id ) {
		$ids[] = $thumbnail_id;
	}
	if ( $product instanceof WC_Product ) {
		$ids = array_merge( $ids, array_map( 'intval', $product->get_gallery_image_ids() ) );
	}
	$ids = array_values( array_unique( array_filter( $ids ) ) );

	if ( ! $ids ) {
		echo '<div class="orwo-gallery orwo-gallery--empty" aria-hidden="true"></div>';

		return;
	}

	$alt = get_the_title( $product_id );
	?>
	<div class="orwo-gallery" data-orwo-gallery>
		<figure class="orwo-gallery__main">
			<?php
			echo wp_get_attachment_image(
				$ids[0],
				'orwo-tile',
				false,
				array(
					'class'         => 'orwo-gallery__image',
					'alt'           => $alt,
					'fetchpriority' => 'high',
					'decoding'      => 'sync',
					'sizes'         => '(max-width: 900px) 92vw, 600px',
					'id'            => 'orwo-gallery-image',
				)
			);
			?>
		</figure>
		<?php if ( count( $ids ) > 1 ) : ?>
			<ul class="orwo-gallery__thumbs">
				<?php foreach ( $ids as $index => $id ) : ?>
					<li>
						<button type="button" class="orwo-gallery__thumb<?php echo 0 === $index ? ' is-current' : ''; ?>"
							data-full="<?php echo esc_url( (string) wp_get_attachment_image_url( $id, 'orwo-tile' ) ); ?>"
							data-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $id, 'orwo-tile' ) ); ?>"
							aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>">
							<?php
							echo wp_get_attachment_image(
								$id,
								'thumbnail',
								false,
								array(
									'alt'     => sprintf( /* translators: %d: image number. */ __( 'View image %d', 'orwo' ), $index + 1 ),
									'loading' => 'lazy',
								)
							);
							?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Keeps WooCommerce's gallery scripts out: this theme renders its own gallery.
 */
function orwo_drop_gallery_scripts() {
	wp_dequeue_script( 'wc-single-product' );
	wp_dequeue_script( 'flexslider' );
	wp_dequeue_script( 'photoswipe' );
	wp_dequeue_script( 'photoswipe-ui-default' );
	wp_dequeue_script( 'zoom' );
	wp_dequeue_style( 'photoswipe' );
	wp_dequeue_style( 'photoswipe-default-skin' );
}
add_action( 'wp_enqueue_scripts', 'orwo_drop_gallery_scripts', 99 );

/**
 * Styles WooCommerce's own notices in the theme's voice rather than restyling them twice.
 *
 * @param string $class Notice wrapper classes.
 * @return string
 */
function orwo_notice_class( $class ) {
	return trim( $class . ' orwo-notice' );
}
add_filter( 'woocommerce_message_wrapper_class', 'orwo_notice_class' );
add_filter( 'woocommerce_error_wrapper_class', 'orwo_notice_class' );
add_filter( 'woocommerce_info_wrapper_class', 'orwo_notice_class' );

/**
 * WooCommerce puts a new store behind its "Coming soon" page, which hides the shop from everyone who is not signed
 * in. That is the right default while the shop is being built and the wrong one on launch day, so it is said out
 * loud here rather than changed quietly.
 */
function orwo_coming_soon_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) || 'yes' !== get_option( 'woocommerce_coming_soon', 'no' ) ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'The shop is in WooCommerce&#8217;s &#8220;Coming soon&#8221; mode.', 'orwo' ); ?></strong>
			<?php esc_html_e( 'Visitors who are not signed in see a holding page instead of the shop. Switch it off under WooCommerce → Settings → General → Site visibility when you are ready to open.', 'orwo' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'orwo_coming_soon_notice' );
