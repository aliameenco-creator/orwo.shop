<?php
/**
 * Assets.
 *
 * One stylesheet with the above-the-fold styles inlined, one deferred script on browsing pages, self-hosted Poppins
 * in two weights, and WooCommerce's own styles and scripts only on the pages that need them.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end styles and scripts.
 */
function orwo_enqueue_assets() {
	wp_enqueue_style( 'orwo', get_theme_file_uri( 'assets/orwo.css' ), array(), ORWO_VERSION );

	wp_enqueue_script(
		'orwo',
		get_theme_file_uri( 'assets/orwo.js' ),
		array(),
		ORWO_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	$view = orwo_current_view();
	wp_add_inline_script(
		'orwo',
		'window.orwoShop=' . wp_json_encode(
			array(
				'rest'    => esc_url_raw( rest_url( 'orwo/v1/' ) ),
				'home'    => esc_url_raw( home_url( '/' ) ),
				'view'    => $view['kind'],
				'format'  => $view['format'],
				'genre'   => $view['genre'],
				'creator' => $view['creator'],
				'label'   => $view['label'],
				'base'    => $view['base'],
				'cart'    => orwo_cart_endpoint(),
				'strings' => array(
					'loading'  => __( 'Loading products…', 'orwo' ),
					'results'  => __( 'Show %s results', 'orwo' ),
					'none'     => __( 'Nothing matches those filters yet. Try removing one.', 'orwo' ),
					'clear'    => __( 'Clear all', 'orwo' ),
					'remove'   => __( 'Remove filter', 'orwo' ),
					'more'     => __( 'Load more', 'orwo' ),
					'announce' => __( '%s products found', 'orwo' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'orwo_enqueue_assets' );

/**
 * Preloads the two fonts and, on pages with a hero, its image.
 */
function orwo_preload_assets() {
	foreach ( array( 'poppins-400-latin', 'poppins-500-latin' ) as $font ) {
		echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( 'assets/fonts/' . $font . '.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}

	if ( is_front_page() ) {
		$hero = orwo_home_option( 'hero_image' );
		if ( $hero ) {
			$url = wp_get_attachment_image_url( (int) $hero, 'full' );
			if ( $url ) {
				echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high">' . "\n";
			}
		}
	}
}
add_action( 'wp_head', 'orwo_preload_assets', 2 );

/**
 * Self-hosted Poppins: two weights, Latin subsets, size-matched fallback so text does not jump.
 *
 * @return string
 */
function orwo_font_css() {
	$dir       = get_theme_file_uri( 'assets/fonts/' );
	$latin     = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
	$latin_ext = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C4,U+2113,U+2C60-2C7F,U+A720-A7FF';

	$css = '';
	foreach ( array( '400', '500' ) as $weight ) {
		foreach ( array( 'latin' => $latin, 'latin-ext' => $latin_ext ) as $subset => $range ) {
			$css .= "@font-face{font-family:'Poppins';font-style:normal;font-weight:{$weight};font-display:swap;"
				. "src:url({$dir}poppins-{$weight}-{$subset}.woff2) format('woff2');unicode-range:{$range}}";
		}
	}

	// Matched fallback: the metrics keep the layout still while Poppins loads.
	$css .= "@font-face{font-family:'Poppins Fallback';src:local('Arial');size-adjust:112%;ascent-override:95%;descent-override:25%;line-gap-override:0%}";

	return $css;
}

/**
 * Prints the font CSS before the stylesheet so the first paint already has the right metrics.
 */
function orwo_print_font_css() {
	echo '<style id="orwo-fonts">' . orwo_font_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed CSS with theme file URLs.
}
add_action( 'wp_head', 'orwo_print_font_css', 3 );

/**
 * The same fonts inside the block editor.
 */
function orwo_editor_fonts() {
	wp_add_inline_style( 'wp-block-library', orwo_font_css() );
}
add_action( 'enqueue_block_editor_assets', 'orwo_editor_fonts' );

/**
 * Body classes the stylesheet keys off.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function orwo_body_class( $classes ) {
	$view = orwo_current_view();

	if ( is_front_page() ) {
		$classes[] = 'orwo-is-home';
	}
	if ( orwo_is_catalogue_view() ) {
		// Deliberately not "orwo-archive": that class is the archive layout grid, and a body class of the same
		// name turned the whole document into that grid.
		$classes[] = 'orwo-is-archive';
		$classes[] = 'orwo-view-' . $view['kind'];
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'orwo-is-product';
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		$classes[] = 'orwo-is-commerce';
	}

	return $classes;
}
add_filter( 'body_class', 'orwo_body_class' );

/**
 * Whether the current request is one of the catalogue listing views.
 *
 * @return bool
 */
function orwo_is_catalogue_view() {
	if ( ! orwo_core_active() ) {
		return false;
	}
	if ( get_query_var( 'orwo_format' ) || get_query_var( 'orwo_genre' ) || get_query_var( 'orwo_creator' ) || get_query_var( 'orwo_label' ) ) {
		return true;
	}
	if ( in_array( (string) get_query_var( 'orwo_view' ), array( 'new', 'sale', 'search' ), true ) ) {
		return true;
	}

	return function_exists( 'is_shop' ) && is_shop();
}

/**
 * Tells the browser how wide each kind of image is really shown, so it picks a small file from srcset.
 *
 * @param string $sizes Default sizes attribute.
 * @param array  $size  Image size.
 * @return string
 */
function orwo_image_sizes_attr( $sizes, $size ) {
	$width = is_array( $size ) ? (int) ( $size[0] ?? 0 ) : 0;

	if ( 600 === $width ) {
		// Product cards: four across on desktop, two on a phone.
		return '(max-width: 640px) 46vw, (max-width: 1024px) 31vw, 300px';
	}

	return $sizes;
}
add_filter( 'wp_calculate_image_sizes', 'orwo_image_sizes_attr', 10, 2 );

/**
 * Speculative loading: prefetch on hover, but never the pages that must not be fetched in the background.
 *
 * @param array<string,mixed> $config Speculation rules configuration.
 * @return array<string,mixed>
 */
function orwo_speculation_rules( $config ) {
	$exclude = array( '/cart/*', '/checkout/*', '/account/*', '/my-account/*', '/wp-login.php', '/wp-admin/*' );

	foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			continue;
		}
		$url  = (string) wc_get_page_permalink( $page );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( $path && '/' !== $path ) {
			$exclude[] = rtrim( $path, '/' ) . '/*';
		}
	}

	$config['eagerness'] = 'moderate';
	$config['mode']      = 'prefetch';
	$config['href_exclude_paths'] = array_values( array_unique( array_merge( (array) ( $config['href_exclude_paths'] ?? array() ), $exclude ) ) );

	return $config;
}
add_filter( 'wp_speculation_rules_configuration', 'orwo_speculation_rules' );
