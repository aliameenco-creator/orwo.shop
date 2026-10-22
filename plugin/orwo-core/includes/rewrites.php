<?php
/**
 * URLs: /format/product/, /genre/, /by/, /label/, /new/, /sale/, /search/.
 *
 * Rewrite rules are registered so WordPress generates tidy links, but every custom path is also resolved in the
 * `request` filter. A host that never flushes its rewrite rules therefore still serves these URLs, which is a
 * lesson learned the hard way on earlier sites in this portfolio.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reserved first path segments. A page with one of these slugs will not be served as a page.
 *
 * @return string[]
 */
function orwo_reserved_segments() {
	return array_merge( Orwo_Formats::slugs(), array( 'genre', 'by', 'label', 'new', 'sale', 'search' ) );
}

/**
 * Registers the custom query variables.
 *
 * @param string[] $vars Public query variables.
 * @return string[]
 */
function orwo_query_vars( $vars ) {
	$vars[] = 'orwo_view';
	$vars[] = 'orwo_q';

	return array_merge( $vars, orwo_filter_query_vars() );
}
add_filter( 'query_vars', 'orwo_query_vars' );

/**
 * Adds the catalogue rewrite rules ahead of WordPress's own page rule.
 */
function orwo_add_rewrite_rules() {
	$formats = implode( '|', array_map( 'preg_quote', Orwo_Formats::slugs() ) );

	// Archives that fill themselves.
	add_rewrite_rule( '^new/?$', 'index.php?orwo_view=new', 'top' );
	add_rewrite_rule( '^new/page/([0-9]{1,})/?$', 'index.php?orwo_view=new&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^sale/?$', 'index.php?orwo_view=sale', 'top' );
	add_rewrite_rule( '^sale/page/([0-9]{1,})/?$', 'index.php?orwo_view=sale&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^search/?$', 'index.php?orwo_view=search', 'top' );
	add_rewrite_rule( '^search/page/([0-9]{1,})/?$', 'index.php?orwo_view=search&paged=$matches[1]', 'top' );

	// Indexable term archives.
	foreach ( array(
		'genre' => 'orwo_genre',
		'by'    => 'orwo_creator',
		'label' => 'orwo_label',
	) as $segment => $taxonomy ) {
		add_rewrite_rule( '^' . $segment . '/([^/]+)/?$', 'index.php?' . $taxonomy . '=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $segment . '/([^/]+)/page/([0-9]{1,})/?$', 'index.php?' . $taxonomy . '=$matches[1]&paged=$matches[2]', 'top' );
	}

	// Format archives and products, which sit exactly two levels deep.
	add_rewrite_rule( '^(' . $formats . ')/?$', 'index.php?orwo_format=$matches[1]', 'top' );
	add_rewrite_rule( '^(' . $formats . ')/page/([0-9]{1,})/?$', 'index.php?orwo_format=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( '^(' . $formats . ')/([^/]+)/?$', 'index.php?post_type=product&name=$matches[2]', 'top' );
}
add_action( 'init', 'orwo_add_rewrite_rules', 20 );

/**
 * The request path, without the installation subdirectory, leading or trailing slashes.
 *
 * @return string
 */
function orwo_request_path() {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$home    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	if ( '/' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}

	return trim( rawurldecode( $path ), '/' );
}

/**
 * Resolves the catalogue URLs whether or not the rewrite rules are in place.
 *
 * @param array<string,mixed> $vars Parsed query variables.
 * @return array<string,mixed>
 */
function orwo_resolve_request( $vars ) {
	if ( is_admin() || ! empty( $vars['orwo_view'] ) || ! empty( $vars['orwo_format'] ) ) {
		return $vars;
	}
	if ( ! empty( $vars['post_type'] ) && 'product' === $vars['post_type'] && ! empty( $vars['name'] ) ) {
		return $vars;
	}

	$path = orwo_request_path();
	if ( '' === $path ) {
		return $vars;
	}

	$paged    = 0;
	$segments = explode( '/', $path );
	$count    = count( $segments );
	if ( $count >= 2 && 'page' === $segments[ $count - 2 ] && ctype_digit( $segments[ $count - 1 ] ) ) {
		$paged    = max( 1, (int) $segments[ $count - 1 ] );
		$segments = array_slice( $segments, 0, $count - 2 );
	}
	if ( empty( $segments ) ) {
		return $vars;
	}

	$first  = $segments[0];
	$second = isset( $segments[1] ) ? $segments[1] : '';
	$extra  = count( $segments ) > 2;

	$resolved = null;

	if ( in_array( $first, array( 'new', 'sale', 'search' ), true ) && '' === $second ) {
		$resolved = array( 'orwo_view' => $first );
	} elseif ( in_array( $first, array( 'genre', 'by', 'label' ), true ) && '' !== $second && ! $extra ) {
		$taxonomies = array(
			'genre' => 'orwo_genre',
			'by'    => 'orwo_creator',
			'label' => 'orwo_label',
		);
		$resolved   = array( $taxonomies[ $first ] => $second );
	} elseif ( Orwo_Formats::get( $first ) && ! $extra ) {
		if ( '' === $second ) {
			$resolved = array( 'orwo_format' => $first );
		} else {
			// Whether the product exists is settled later: orwo_canonical_product_redirect() and
			// orwo_missing_product_redirect() handle the canonical URL and the dead end on template_redirect.
			$resolved = array(
				'post_type' => 'product',
				'name'      => $second,
				'product'   => $second,
			);
		}
	}

	if ( null === $resolved ) {
		return $vars;
	}

	foreach ( array( 'pagename', 'name', 'page', 'attachment', 'error', 'category_name', 'post_type' ) as $key ) {
		unset( $vars[ $key ] );
	}
	if ( $paged ) {
		$resolved['paged'] = $paged;
	}

	return array_merge( $vars, $resolved );
}
add_filter( 'request', 'orwo_resolve_request' );

/**
 * Keeps product URLs canonical once the request has been resolved.
 *
 * This runs whether the URL was matched by a rewrite rule or by orwo_resolve_request(), which is why it lives here
 * rather than only inside the resolver: a flushed rewrite rule matches /photos/a-vinyl-record/ perfectly well and
 * would otherwise serve the product at two URLs.
 */
function orwo_canonical_product_redirect() {
	if ( is_admin() || ! is_singular( 'product' ) ) {
		return;
	}

	$path      = orwo_request_path();
	$canonical = orwo_product_path( get_queried_object_id() );
	if ( ! $canonical ) {
		return;
	}

	// Pagination and preview URLs are left alone.
	if ( $path === $canonical || is_preview() || get_query_var( 'page' ) || get_query_var( 'paged' ) ) {
		return;
	}

	orwo_redirect( user_trailingslashit( home_url( '/' . $canonical ) ) );
}
add_action( 'template_redirect', 'orwo_canonical_product_redirect', 5 );

/**
 * Turns a missing product under a real format into a redirect rather than a dead end.
 */
function orwo_missing_product_redirect() {
	if ( is_admin() || ! is_404() ) {
		return;
	}

	$segments = explode( '/', orwo_request_path() );
	if ( 2 !== count( $segments ) || ! Orwo_Formats::get( $segments[0] ) || '' === $segments[1] ) {
		return;
	}

	orwo_maybe_redirect_old_slug( $segments[1], $segments[0] );
}
add_action( 'template_redirect', 'orwo_missing_product_redirect', 6 );

/**
 * A published product by slug, ignoring its format.
 *
 * @param string $slug Product slug.
 * @return WP_Post|null
 */
function orwo_find_product_by_slug( $slug ) {
	$posts = get_posts(
		array(
			'post_type'        => 'product',
			'name'             => $slug,
			'post_status'      => array( 'publish', 'private' ),
			'posts_per_page'   => 1,
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return $posts ? $posts[0] : null;
}

/**
 * Redirects a retired product URL: an old slug goes to the product, a deleted product to its format page.
 *
 * @param string $slug   Requested product slug.
 * @param string $format Requested format slug.
 */
function orwo_maybe_redirect_old_slug( $slug, $format ) {
	$posts = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One indexed exact match, only on a miss.
				array(
					'key'   => '_orwo_old_slug',
					'value' => $slug,
				),
			),
		)
	);
	if ( $posts ) {
		orwo_redirect( get_permalink( $posts[0] ) );
	}

	$gone = get_option( 'orwo_gone_slugs', array() );
	if ( isset( $gone[ $slug ] ) ) {
		orwo_redirect( user_trailingslashit( home_url( '/' . $gone[ $slug ] ) ) );
	}
	if ( Orwo_Formats::get( $format ) ) {
		// An unknown product under a real format: the format page is the most useful place to land.
		orwo_redirect( user_trailingslashit( home_url( '/' . $format ) ) );
	}
}

/**
 * A permanent redirect that is skipped during unit tests.
 *
 * @param string $url Destination.
 */
function orwo_redirect( $url ) {
	if ( ! $url || ( defined( 'ORWO_NO_REDIRECT' ) && ORWO_NO_REDIRECT ) ) {
		return;
	}
	wp_safe_redirect( $url, 301 );
	exit;
}

/**
 * The canonical path of a product, without leading or trailing slashes.
 *
 * @param WP_Post|int $product Product.
 * @return string
 */
function orwo_product_path( $product ) {
	$post = get_post( $product );
	if ( ! $post ) {
		return '';
	}
	$format = orwo_product_format( $post->ID );

	return ( $format ? $format : 'shop' ) . '/' . $post->post_name;
}

/**
 * Products live at /format/slug/.
 *
 * @param string  $permalink Default permalink.
 * @param WP_Post $post      Post.
 * @return string
 */
function orwo_product_permalink( $permalink, $post ) {
	if ( ! $post || 'product' !== $post->post_type || ! $post->post_name ) {
		return $permalink;
	}

	return user_trailingslashit( home_url( '/' . orwo_product_path( $post ) ) );
}
add_filter( 'post_type_link', 'orwo_product_permalink', 10, 2 );

/**
 * Term archives: formats at the root, the rest under their own segment.
 *
 * @param string  $url  Default term URL.
 * @param WP_Term $term Term.
 * @return string
 */
function orwo_term_link( $url, $term ) {
	$segments = array(
		'orwo_format'  => '',
		'orwo_genre'   => 'genre/',
		'orwo_creator' => 'by/',
		'orwo_label'   => 'label/',
	);

	if ( ! isset( $segments[ $term->taxonomy ] ) ) {
		return $url;
	}

	return user_trailingslashit( home_url( '/' . $segments[ $term->taxonomy ] . $term->slug ) );
}
add_filter( 'term_link', 'orwo_term_link', 10, 2 );

/**
 * Records the previous slug so a renamed product keeps its old URL working.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $after   Post after the update.
 * @param WP_Post $before  Post before the update.
 */
function orwo_track_slug_change( $post_id, $after, $before ) {
	if ( 'product' !== $after->post_type || $after->post_name === $before->post_name || ! $before->post_name ) {
		return;
	}
	$old = get_post_meta( $post_id, '_orwo_old_slug', false );
	if ( ! in_array( $before->post_name, (array) $old, true ) ) {
		add_post_meta( $post_id, '_orwo_old_slug', $before->post_name );
	}
	delete_post_meta( $post_id, '_orwo_old_slug', $after->post_name );
}
add_action( 'post_updated', 'orwo_track_slug_change', 10, 3 );

/**
 * Remembers where a trashed or deleted product's URL should point.
 *
 * @param int $post_id Post ID.
 */
function orwo_track_removed_product( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}
	$format = orwo_product_format( $post_id );
	if ( ! $format ) {
		return;
	}
	$gone = get_option( 'orwo_gone_slugs', array() );
	$slugs = array_merge( array( $post->post_name ), (array) get_post_meta( $post_id, '_orwo_old_slug', false ) );
	foreach ( array_filter( $slugs ) as $slug ) {
		$gone[ $slug ] = $format;
	}
	if ( count( $gone ) > 2000 ) {
		$gone = array_slice( $gone, -2000, null, true );
	}
	update_option( 'orwo_gone_slugs', $gone, false );
	orwo_flush_filter_cache();
	orwo_queue_chat_index_rebuild();
}
add_action( 'wp_trash_post', 'orwo_track_removed_product' );
add_action( 'before_delete_post', 'orwo_track_removed_product' );

/**
 * Clears the redirect record when a product comes back from the trash.
 *
 * @param int $post_id Post ID.
 */
function orwo_untrack_restored_product( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}
	$gone = get_option( 'orwo_gone_slugs', array() );
	unset( $gone[ $post->post_name ] );
	update_option( 'orwo_gone_slugs', $gone, false );
}
add_action( 'untrashed_post', 'orwo_untrack_restored_product' );
