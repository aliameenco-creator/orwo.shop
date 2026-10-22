<?php
/**
 * Theme setup: supports, menus, image sizes and the template routing for the catalogue views.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme supports and menus.
 */
function orwo_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// WooCommerce stays the order engine and its own pages stay visible, styled by this theme. The product gallery
	// is ours, so the gallery scripts (and their jQuery dependency) are never loaded on a product page.
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 1200,
			'product_grid'          => array(
				'default_columns' => 4,
				'default_rows'    => 6,
			),
		)
	);

	add_editor_style( 'assets/editor.css' );

	register_nav_menus(
		array(
			'help'   => __( 'Footer: Help', 'orwo' ),
			'about'  => __( 'Footer: About', 'orwo' ),
			'legal'  => __( 'Footer: Legal', 'orwo' ),
		)
	);

	load_theme_textdomain( 'orwo', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'orwo_setup' );

/**
 * Content width used by oEmbed.
 */
function orwo_content_width() {
	$GLOBALS['content_width'] = 1280;
}
add_action( 'after_setup_theme', 'orwo_content_width', 0 );

/**
 * Routes the catalogue views to their templates.
 *
 * The /new/, /sale/ and /search/ views are resolved from query variables rather than a post type archive, so the
 * template hierarchy needs telling where to look.
 *
 * @param string $template Template path chosen by WordPress.
 * @return string
 */
function orwo_template_include( $template ) {
	if ( ! orwo_core_active() ) {
		return $template;
	}

	$view = get_query_var( 'orwo_view' );
	if ( in_array( (string) $view, array( 'new', 'sale', 'search' ), true ) ) {
		$found = locate_template( array( 'archive-product.php' ) );
		if ( $found ) {
			return $found;
		}
	}

	if ( get_query_var( 'orwo_format' ) || get_query_var( 'orwo_genre' ) || get_query_var( 'orwo_creator' ) || get_query_var( 'orwo_label' ) ) {
		$found = locate_template( array( 'archive-product.php' ) );
		if ( $found ) {
			return $found;
		}
	}

	return $template;
}
add_filter( 'template_include', 'orwo_template_include', 20 );

/**
 * Keeps the catalogue views out of the 404 state: an empty filtered view is still a valid page.
 */
function orwo_force_200() {
	if ( ! orwo_core_active() ) {
		return;
	}
	if ( is_404() && ( get_query_var( 'orwo_view' ) || get_query_var( 'orwo_format' ) ) ) {
		status_header( 200 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'orwo_force_200', 1 );

/**
 * The view currently being shown, with the labels and base URL its template needs.
 *
 * @return array<string,mixed>
 */
function orwo_current_view() {
	static $view = null;
	if ( null !== $view ) {
		return $view;
	}

	$format  = (string) get_query_var( 'orwo_format' );
	$genre   = (string) get_query_var( 'orwo_genre' );
	$creator = (string) get_query_var( 'orwo_creator' );
	$label   = (string) get_query_var( 'orwo_label' );
	$named   = (string) get_query_var( 'orwo_view' );

	$view = array(
		'kind'        => 'shop',
		'format'      => '',
		'genre'       => '',
		'creator'     => '',
		'label'       => '',
		'title'       => __( 'All products', 'orwo' ),
		'subtitle'    => '',
		'base'        => orwo_shop_url(),
		'indexable'   => true,
		'description' => '',
	);

	if ( 'new' === $named ) {
		$view['kind']        = 'new';
		$view['title']       = __( 'New arrivals', 'orwo' );
		$view['base']        = user_trailingslashit( home_url( '/new' ) );
		$view['description'] = __( 'The latest additions across every format.', 'orwo' );
	} elseif ( 'sale' === $named ) {
		$view['kind']        = 'sale';
		$view['title']       = __( 'Sale', 'orwo' );
		$view['base']        = user_trailingslashit( home_url( '/sale' ) );
		$view['description'] = __( 'Everything with a reduced price right now.', 'orwo' );
	} elseif ( 'search' === $named ) {
		$filters             = orwo_core_active() ? orwo_current_filters() : array();
		$term                = isset( $filters['q'] ) ? $filters['q'] : '';
		$view['kind']        = 'search';
		$view['title']       = $term ? sprintf( /* translators: %s: search term. */ __( 'Search: %s', 'orwo' ), $term ) : __( 'Search', 'orwo' );
		$view['base']        = user_trailingslashit( home_url( '/search' ) );
		$view['indexable']   = false;
	} elseif ( $format ) {
		$definition          = orwo_core_active() ? Orwo_Formats::get( $format ) : null;
		$view['kind']        = 'format';
		$view['format']      = $format;
		$view['title']       = $definition ? $definition['label'] : ucfirst( $format );
		$view['base']        = user_trailingslashit( home_url( '/' . $format ) );
	} elseif ( $genre || $creator || $label ) {
		$map = array(
			'genre'   => array( $genre, 'orwo_genre', 'genre/' ),
			'creator' => array( $creator, 'orwo_creator', 'by/' ),
			'label'   => array( $label, 'orwo_label', 'label/' ),
		);
		foreach ( $map as $kind => $spec ) {
			if ( ! $spec[0] ) {
				continue;
			}
			$term            = get_term_by( 'slug', $spec[0], $spec[1] );
			$view['kind']    = $kind;
			$view[ $kind ]   = $spec[0];
			$view['title']   = $term && ! is_wp_error( $term ) ? $term->name : $spec[0];
			$view['base']    = user_trailingslashit( home_url( '/' . $spec[2] . $spec[0] ) );
			if ( 'creator' === $kind ) {
				/* translators: %s: creator name. */
				$view['subtitle'] = sprintf( __( 'Everything by %s', 'orwo' ), $view['title'] );
			}
			break;
		}
	}

	// A filtered view is never indexed: it is a shareable URL, not a page of its own.
	$filters = orwo_core_active() ? orwo_current_filters() : array();
	unset( $filters['sort'] );
	if ( $filters ) {
		$view['indexable'] = false;
	}

	return $view;
}

/**
 * The all-products URL. WooCommerce's shop page when it exists, otherwise /shop/.
 *
 * @return string
 */
function orwo_shop_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'shop' );
		if ( $url ) {
			return $url;
		}
	}

	return user_trailingslashit( home_url( '/shop' ) );
}
