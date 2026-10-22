<?php
/**
 * SEO, built in without a plugin.
 *
 * Title pattern, meta description from the "Why we like it" line, Open Graph tags, product and breadcrumb
 * structured data, canonical links, the noindex rule for filtered views, and filter URLs kept out of the sitemap.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The document title pattern: "Title, Creator, Format | ORWO".
 *
 * @param array<string,string> $parts Title parts.
 * @return array<string,string>
 */
function orwo_document_title( $parts ) {
	if ( function_exists( 'is_product' ) && is_product() && orwo_core_active() ) {
		$id      = get_queried_object_id();
		$creator = orwo_creator_name( $id );
		$format  = Orwo_Formats::label( orwo_product_format( $id ) );

		$parts['title'] = implode( ', ', array_filter( array( get_the_title( $id ), $creator, $format ) ) );

		return $parts;
	}

	if ( orwo_is_catalogue_view() ) {
		$view           = orwo_current_view();
		$parts['title'] = $view['title'];
	}

	return $parts;
}
add_filter( 'document_title_parts', 'orwo_document_title' );

/**
 * The separator and tail match the pattern in the plan.
 *
 * @return string
 */
function orwo_title_separator() {
	return '|';
}
add_filter( 'document_title_separator', 'orwo_title_separator' );

/**
 * The description of the current page.
 *
 * @return string
 */
function orwo_meta_description() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$id  = get_queried_object_id();
		$why = orwo_core_active() ? (string) orwo_field( $id, 'why_we_like_it' ) : '';
		if ( '' === $why ) {
			$why = (string) get_post_field( 'post_excerpt', $id );
		}
		if ( '' === $why ) {
			$why = wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) );
		}

		return wp_trim_words( $why, 32, '' );
	}

	if ( orwo_is_catalogue_view() ) {
		$view = orwo_current_view();
		if ( $view['description'] ) {
			return $view['description'];
		}
		if ( 'format' === $view['kind'] ) {
			/* translators: %s: format name, for example Vinyl. */
			return sprintf( __( 'Browse our %s, with filters for genre, creator, condition and price.', 'orwo' ), strtolower( $view['title'] ) );
		}
		/* translators: %s: archive title. */
		return sprintf( __( 'Everything in %s at ORWO.', 'orwo' ), $view['title'] );
	}

	if ( is_singular() ) {
		$excerpt = (string) get_post_field( 'post_excerpt', get_queried_object_id() );

		return $excerpt ? wp_trim_words( $excerpt, 32, '' ) : wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', get_queried_object_id() ) ), 32, '' );
	}

	return (string) get_bloginfo( 'description' );
}

/**
 * Prints the description, robots rule, canonical link and Open Graph tags.
 */
function orwo_print_head_meta() {
	$description = orwo_meta_description();
	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}

	$view = orwo_current_view();
	if ( orwo_is_catalogue_view() && ! $view['indexable'] ) {
		// A filtered or search view is shareable but never indexed, and points at the page it filters.
		echo '<meta name="robots" content="noindex, follow">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $view['base'] ) . '">' . "\n";
	} elseif ( orwo_is_catalogue_view() ) {
		echo '<link rel="canonical" href="' . esc_url( orwo_paged_canonical( $view['base'] ) ) . '">' . "\n";
	}

	$title = wp_get_document_title();
	echo '<meta property="og:site_name" content="' . esc_attr( (string) get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:type" content="' . ( ( function_exists( 'is_product' ) && is_product() ) ? 'product' : 'website' ) . '">' . "\n";
	if ( $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}

	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = (string) get_the_post_thumbnail_url( get_queried_object_id(), 'orwo-tile' );
	} elseif ( is_front_page() ) {
		$hero  = orwo_home_option( 'hero_image' );
		$image = $hero ? (string) wp_get_attachment_image_url( (int) $hero, 'full' ) : '';
	}
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}
}
add_action( 'wp_head', 'orwo_print_head_meta', 4 );

/**
 * The canonical URL of a paged archive keeps its page number.
 *
 * @param string $base Base URL.
 * @return string
 */
function orwo_paged_canonical( $base ) {
	$paged = max( 1, (int) get_query_var( 'paged' ) );

	return $paged > 1 ? user_trailingslashit( trailingslashit( $base ) . 'page/' . $paged ) : $base;
}

/**
 * Product and breadcrumb structured data.
 */
function orwo_print_schema() {
	$graph = array();

	if ( function_exists( 'is_product' ) && is_product() ) {
		$id      = get_queried_object_id();
		$product = orwo_core_active() ? orwo_wc_product( $id ) : null;

		$node = array(
			'@type'       => 'Product',
			'@id'         => get_permalink( $id ) . '#product',
			'name'        => get_the_title( $id ),
			'description' => orwo_meta_description(),
			'url'         => get_permalink( $id ),
		);

		$image = get_the_post_thumbnail_url( $id, 'full' );
		if ( $image ) {
			$node['image'] = $image;
		}
		if ( orwo_core_active() ) {
			$creator = orwo_creator_name( $id );
			if ( $creator ) {
				$node['brand'] = array(
					'@type' => 'Brand',
					'name'  => $creator,
				);
			}
			$year = orwo_field( $id, 'year' );
			if ( $year ) {
				$node['releaseDate'] = (string) $year;
			}
		}
		if ( $product ) {
			$sku = $product->get_sku();
			if ( $sku ) {
				$node['sku'] = $sku;
			}
			$node['offers'] = array(
				'@type'         => 'Offer',
				'url'           => get_permalink( $id ),
				'price'         => (string) wc_get_price_to_display( $product ),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'itemCondition' => orwo_schema_condition( orwo_product_condition( $id ) ),
			);
		}

		$graph[] = $node;
	}

	$crumbs = orwo_breadcrumbs();
	if ( count( $crumbs ) > 1 ) {
		$items = array();
		foreach ( $crumbs as $position => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $position + 1,
				'name'     => $crumb['label'],
			);
			if ( ! empty( $crumb['url'] ) ) {
				$item['item'] = $crumb['url'];
			}
			$items[] = $item;
		}
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	if ( ! $graph ) {
		return;
	}

	echo '<script type="application/ld+json">' . wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "</script>\n";
}
add_action( 'wp_head', 'orwo_print_schema', 20 );

/**
 * Maps a condition to a schema.org item condition.
 *
 * @param string $condition Condition name.
 * @return string
 */
function orwo_schema_condition( $condition ) {
	return 'New' === $condition ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition';
}

/**
 * Image alt text falls back to the product title, which is what the SEO rules call for.
 *
 * @param array  $attr       Image attributes.
 * @param object $attachment Attachment.
 * @return array
 */
function orwo_image_alt( $attr, $attachment ) {
	if ( ! empty( $attr['alt'] ) ) {
		return $attr;
	}
	$parent = (int) get_post_field( 'post_parent', $attachment->ID );
	if ( $parent && 'product' === get_post_type( $parent ) ) {
		$attr['alt'] = get_the_title( $parent );
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'orwo_image_alt', 10, 2 );

/**
 * Filtered URLs never reach the sitemap: only the base archives and products do.
 *
 * @param array<string,mixed> $providers Sitemap providers.
 * @return array<string,mixed>
 */
function orwo_sitemap_providers( $providers ) {
	// Decade and condition archives add nothing a search engine wants.
	return $providers;
}
add_filter( 'wp_sitemaps_add_provider', 'orwo_sitemap_providers' );

/**
 * Keeps the derived taxonomies out of the sitemap.
 *
 * @param array<string,mixed> $args     Taxonomy query arguments.
 * @param string              $taxonomy Taxonomy name.
 * @return array<string,mixed>
 */
function orwo_sitemap_taxonomies( $args, $taxonomy ) {
	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'orwo_sitemap_taxonomies', 10, 2 );

/**
 * Removes the taxonomies that should not be indexed from the sitemap index.
 *
 * @param array<string,WP_Taxonomy> $taxonomies Taxonomies.
 * @return array<string,WP_Taxonomy>
 */
function orwo_sitemap_taxonomy_list( $taxonomies ) {
	unset( $taxonomies['orwo_decade'], $taxonomies['orwo_condition'] );

	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'orwo_sitemap_taxonomy_list' );
