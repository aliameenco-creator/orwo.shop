<?php
/**
 * Product helpers shared by the templates, the REST endpoint and the chatbot index.
 *
 * The card markup lives in the theme (template-parts/product-card.php) so a redesign never touches this plugin; the
 * fallback below only runs when no theme supplies one.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether WooCommerce is active. Everything money-related is guarded by this.
 *
 * @return bool
 */
function orwo_woocommerce_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

/**
 * The WooCommerce product object, or null.
 *
 * @param int $product_id Product ID.
 * @return WC_Product|null
 */
function orwo_wc_product( $product_id ) {
	if ( ! orwo_woocommerce_active() ) {
		return null;
	}
	$product = wc_get_product( $product_id );

	return $product instanceof WC_Product ? $product : null;
}

/**
 * Price HTML for a product, empty when WooCommerce is not active.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function orwo_price_html( $product_id ) {
	$product = orwo_wc_product( $product_id );

	return $product ? (string) $product->get_price_html() : '';
}

/**
 * Whether a product is out of stock.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function orwo_is_sold_out( $product_id ) {
	$product = orwo_wc_product( $product_id );

	return $product ? ! $product->is_in_stock() : false;
}

/**
 * Whether a product is on sale.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function orwo_is_on_sale( $product_id ) {
	$product = orwo_wc_product( $product_id );

	return $product ? (bool) $product->is_on_sale() : false;
}

/**
 * The small labels shown on a product card and above the product title.
 *
 * @param int $product_id Product ID.
 * @return array<int,array{text:string,kind:string}>
 */
function orwo_product_badges( $product_id ) {
	$badges = array();

	if ( orwo_is_sold_out( $product_id ) ) {
		$badges[] = array(
			'text' => __( 'Sold out', 'orwo-core' ),
			'kind' => 'soldout',
		);
	} elseif ( orwo_is_on_sale( $product_id ) ) {
		$badges[] = array(
			'text' => __( 'Sale', 'orwo-core' ),
			'kind' => 'sale',
		);
	}

	$edition = orwo_field( $product_id, 'edition' );
	if ( is_string( $edition ) && false !== stripos( $edition, 'limited' ) ) {
		$badges[] = array(
			'text' => __( 'Limited', 'orwo-core' ),
			'kind' => 'limited',
		);
	}

	return $badges;
}

/**
 * The creator name of a product, used on the card under the title.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function orwo_creator_name( $product_id ) {
	$term = orwo_first_term( $product_id, 'orwo_creator' );

	return $term ? $term->name : '';
}

/**
 * The second gallery image, shown on hover. Loaded only on hover by the theme's script.
 *
 * @param int $product_id Product ID.
 * @return string Image URL or empty string.
 */
function orwo_hover_image_url( $product_id ) {
	$product = orwo_wc_product( $product_id );
	if ( ! $product ) {
		return '';
	}
	$gallery = $product->get_gallery_image_ids();
	if ( empty( $gallery ) ) {
		return '';
	}

	return (string) wp_get_attachment_image_url( $gallery[0], 'orwo-card' );
}

/**
 * Renders one product card, preferring the theme's template part.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function orwo_product_card( $product_id ) {
	$located = locate_template( 'template-parts/product-card.php' );

	ob_start();
	if ( $located ) {
		// Templates read the global post, exactly as they do inside the main loop.
		global $post;
		$previous = $post;
		$post     = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		setup_postdata( $post );
		load_template( $located, false, array( 'product_id' => $product_id ) );
		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
		wp_reset_postdata();
	} else {
		printf(
			'<article class="orwo-card"><a href="%1$s"><span class="orwo-card__title">%2$s</span></a> <span class="orwo-card__price">%3$s</span></article>',
			esc_url( (string) get_permalink( $product_id ) ),
			esc_html( get_the_title( $product_id ) ),
			wp_kses_post( orwo_price_html( $product_id ) )
		);
	}

	return (string) ob_get_clean();
}

/**
 * Renders a grid of product cards from a query.
 *
 * @param WP_Query $query Product query.
 * @return string
 */
function orwo_product_grid( $query ) {
	if ( ! $query->have_posts() ) {
		return '';
	}

	$html = '';
	foreach ( $query->posts as $post ) {
		$html .= orwo_product_card( is_object( $post ) ? $post->ID : (int) $post );
	}

	return $html;
}

/**
 * A simple product query used by the homepage rows and the chatbot.
 *
 * @param array<string,mixed> $args Keys: view (new, sale, limited), format, limit, filters.
 * @return WP_Query
 */
function orwo_products( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'view'    => '',
			'format'  => '',
			'limit'   => 8,
			'filters' => array(),
		)
	);

	$query_args = orwo_query_args(
		$args['filters'],
		array(
			'format'   => $args['format'],
			'view'     => $args['view'],
			'per_page' => (int) $args['limit'],
		)
	);

	if ( 'limited' === $args['view'] ) {
		$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One flag.
			array(
				'key'   => Orwo_Formats::meta_key( 'limited_edition' ),
				'value' => '1',
			),
		);
	}

	$query = new WP_Query();
	$query->query( $query_args );

	return $query;
}

/**
 * Registers the image size used by the 4:5 product tile.
 */
function orwo_register_image_sizes() {
	// 4:5 tiles: contained on a light tile, so square vinyl and tall posters line up in one grid.
	add_image_size( 'orwo-card', 600, 750, false );
	add_image_size( 'orwo-tile', 900, 1125, false );
}
add_action( 'after_setup_theme', 'orwo_register_image_sizes' );

/**
 * Keeps the filter cache and chatbot index in step with stock changes made by an order.
 *
 * @param int|WC_Product $product Product or ID.
 */
function orwo_product_changed( $product = 0 ) {
	orwo_flush_filter_cache();
	orwo_queue_chat_index_rebuild();
}
add_action( 'woocommerce_product_set_stock', 'orwo_product_changed' );
add_action( 'woocommerce_variation_set_stock', 'orwo_product_changed' );
add_action( 'woocommerce_product_set_stock_status', 'orwo_product_changed' );
add_action( 'woocommerce_update_product', 'orwo_product_changed' );
add_action( 'trashed_post', 'orwo_product_changed' );
