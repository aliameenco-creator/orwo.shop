<?php
/**
 * Filters and sorting.
 *
 * Filters are plain query-string rules: `pre_get_posts` turns the whitelisted variables into a tax_query and
 * meta_query on the normal product query, which is the version that works with JavaScript switched off. The REST
 * endpoint builds the same query from the same parser, so the two can never drift apart.
 *
 * Price, stock, sale and best-selling use WooCommerce's product lookup table rather than meta queries, which keeps
 * a large catalogue fast.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products shown per page.
 */
const ORWO_PER_PAGE = 24;

/**
 * Sort options, in the order they appear in the dropdown.
 *
 * @return array<string,string>
 */
function orwo_sort_options() {
	return array(
		'newest'     => __( 'Newest', 'orwo-core' ),
		'price-asc'  => __( 'Price low to high', 'orwo-core' ),
		'price-desc' => __( 'Price high to low', 'orwo-core' ),
		'title-asc'  => __( 'Title A to Z', 'orwo-core' ),
		'year-desc'  => __( 'Year, newest first', 'orwo-core' ),
		'popular'    => __( 'Best selling', 'orwo-core' ),
	);
}

/**
 * The whitelist of filter query variables. Anything else in the query string is ignored.
 *
 * @return string[]
 */
function orwo_filter_query_vars() {
	$vars = array( 'format', 'genre', 'creator', 'label', 'decade', 'condition', 'min_price', 'max_price', 'instock', 'sort', 'q' );

	foreach ( orwo_field_filter_keys() as $key ) {
		$vars[] = $key;
	}

	return array_values( array_unique( $vars ) );
}

/**
 * Field keys that act as filters in at least one format.
 *
 * @return string[]
 */
function orwo_field_filter_keys() {
	$keys = array();
	foreach ( Orwo_Formats::all_fields() as $key => $entry ) {
		if ( ! empty( $entry['field']['filter'] ) ) {
			$keys[] = $key;
		}
	}

	return $keys;
}

/**
 * The facets available in a context, in display order.
 *
 * Global facets come first, then the extra facets of the current format. On /shop/ only the global facets and the
 * format facet are offered, because format-specific options would be meaningless across six formats.
 *
 * @param string $format Format slug, or an empty string for the all-products view.
 * @return array<string,array> Facet definitions keyed by query variable.
 */
function orwo_facet_definitions( $format = '' ) {
	$facets = array();

	if ( '' === $format ) {
		$facets['format'] = array(
			'label'    => __( 'Format', 'orwo-core' ),
			'source'   => 'taxonomy',
			'taxonomy' => 'orwo_format',
			'type'     => 'checkbox',
			'order'    => Orwo_Formats::slugs(),
		);
	}

	$facets['price'] = array(
		'label'  => __( 'Price', 'orwo-core' ),
		'source' => 'price',
		'type'   => 'range',
	);

	$facets['instock'] = array(
		'label'  => __( 'Availability', 'orwo-core' ),
		'source' => 'stock',
		'type'   => 'toggle',
	);

	$facets['condition'] = array(
		'label'    => __( 'Condition', 'orwo-core' ),
		'source'   => 'taxonomy',
		'taxonomy' => 'orwo_condition',
		'type'     => 'checkbox',
		'order'    => array_map( 'sanitize_title', Orwo_Formats::CONDITIONS ),
	);

	foreach ( array(
		'genre'   => array( 'orwo_genre', __( 'Genre', 'orwo-core' ) ),
		'creator' => array( 'orwo_creator', __( 'Creator', 'orwo-core' ) ),
		'label'   => array( 'orwo_label', __( 'Label or studio', 'orwo-core' ) ),
		'decade'  => array( 'orwo_decade', __( 'Decade', 'orwo-core' ) ),
	) as $var => $spec ) {
		$facets[ $var ] = array(
			'label'    => $spec[1],
			'source'   => 'taxonomy',
			'taxonomy' => $spec[0],
			'type'     => 'checkbox',
		);
	}

	if ( '' !== $format ) {
		foreach ( Orwo_Formats::fields( $format, 'filter' ) as $key => $field ) {
			$facets[ $key ] = array(
				'label'      => $field['label'],
				'source'     => 'meta',
				'meta_key'   => Orwo_Formats::meta_key( $key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Facet definition, not a query.
				'type'       => 'bool' === $field['type'] ? 'toggle' : 'checkbox',
				'order'      => $field['options'],
				'used_items' => ! empty( $field['used_items'] ),
			);
		}
	}

	/**
	 * Filters the facets offered in a context.
	 *
	 * @param array<string,array> $facets Facet definitions.
	 * @param string              $format Format slug or empty string.
	 */
	return apply_filters( 'orwo_facet_definitions', $facets, $format );
}

/**
 * Reads and sanitises the filter values from a source array, normally the query string.
 *
 * @param array<string,mixed> $source Raw values.
 * @return array<string,mixed> Clean values, with empty filters removed.
 */
function orwo_parse_filters( $source ) {
	$clean = array();

	foreach ( array( 'format', 'genre', 'creator', 'label', 'decade', 'condition' ) as $var ) {
		if ( empty( $source[ $var ] ) ) {
			continue;
		}
		$values = is_array( $source[ $var ] ) ? $source[ $var ] : explode( ',', (string) $source[ $var ] );
		$values = array_values( array_filter( array_map( 'sanitize_title', $values ) ) );
		if ( $values ) {
			$clean[ $var ] = $values;
		}
	}

	$fields = Orwo_Formats::all_fields();
	foreach ( orwo_field_filter_keys() as $key ) {
		if ( ! isset( $source[ $key ] ) || '' === $source[ $key ] ) {
			continue;
		}
		$type = $fields[ $key ]['field']['type'];
		if ( 'bool' === $type ) {
			if ( in_array( (string) $source[ $key ], array( '1', 'yes', 'true' ), true ) ) {
				$clean[ $key ] = true;
			}
			continue;
		}
		$values = is_array( $source[ $key ] ) ? $source[ $key ] : explode( ',', (string) $source[ $key ] );
		$values = array_values( array_filter( array_map( static function ( $value ) {
			return sanitize_text_field( trim( (string) $value ) );
		}, $values ) ) );
		if ( $values ) {
			$clean[ $key ] = $values;
		}
	}

	foreach ( array( 'min_price', 'max_price' ) as $var ) {
		if ( isset( $source[ $var ] ) && '' !== $source[ $var ] && is_numeric( $source[ $var ] ) ) {
			$value = max( 0, (float) $source[ $var ] );
			// A zero minimum is the same as no minimum: keeping it would show a pointless chip.
			if ( 'min_price' === $var && 0.0 === $value ) {
				continue;
			}
			$clean[ $var ] = $value;
		}
	}
	if ( isset( $clean['min_price'], $clean['max_price'] ) && $clean['min_price'] > $clean['max_price'] ) {
		unset( $clean['max_price'] );
	}

	if ( ! empty( $source['instock'] ) && in_array( (string) $source['instock'], array( '1', 'yes', 'true' ), true ) ) {
		$clean['instock'] = true;
	}

	if ( ! empty( $source['sort'] ) && array_key_exists( (string) $source['sort'], orwo_sort_options() ) ) {
		$clean['sort'] = (string) $source['sort'];
	}

	if ( ! empty( $source['q'] ) ) {
		$clean['q'] = sanitize_text_field( (string) $source['q'] );
	}

	return $clean;
}

/**
 * The filters of the current request, read from the whitelisted query variables.
 *
 * @return array<string,mixed>
 */
function orwo_current_filters() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$source = array();
	foreach ( orwo_filter_query_vars() as $var ) {
		$value = get_query_var( $var );
		if ( '' === $value || null === $value ) {
			// Fall back to the raw query string for variables WordPress has not registered yet.
			$value = isset( $_GET[ $var ] ) ? wp_unslash( $_GET[ $var ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only browsing filter.
		}
		if ( '' !== $value && null !== $value ) {
			$source[ $var ] = $value;
		}
	}

	$cached = orwo_parse_filters( $source );

	return $cached;
}

/**
 * Builds the WP_Query arguments for a catalogue view.
 *
 * @param array<string,mixed> $filters Parsed filters.
 * @param array<string,mixed> $context Keys: format, genre, creator, label, view, paged, per_page, ids_only.
 * @return array<string,mixed>
 */
function orwo_query_args( $filters, $context = array() ) {
	$context = wp_parse_args(
		$context,
		array(
			'format'   => '',
			'genre'    => '',
			'creator'  => '',
			'label'    => '',
			'view'     => '',
			'paged'    => 1,
			'per_page' => ORWO_PER_PAGE,
			'ids_only' => false,
			'skip'     => '',
		)
	);

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $context['per_page'],
		'paged'               => max( 1, (int) $context['paged'] ),
		'ignore_sticky_posts' => true,
		'orwo_filtered'       => true,
	);

	if ( $context['ids_only'] ) {
		$args['fields']         = 'ids';
		$args['no_found_rows']  = true;
		$args['posts_per_page'] = 2000;
		$args['paged']          = 1;
	}

	$tax_query  = array( 'relation' => 'AND' );
	$meta_query = array( 'relation' => 'AND' );

	// The path sets the context; the query string can only narrow it further.
	if ( $context['format'] ) {
		$tax_query[] = array(
			'taxonomy' => 'orwo_format',
			'field'    => 'slug',
			'terms'    => array( $context['format'] ),
		);
	}
	foreach ( array(
		'genre'   => 'orwo_genre',
		'creator' => 'orwo_creator',
		'label'   => 'orwo_label',
	) as $key => $taxonomy ) {
		if ( $context[ $key ] ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => array( $context[ $key ] ),
			);
		}
	}

	$taxonomy_filters = array(
		'format'    => 'orwo_format',
		'genre'     => 'orwo_genre',
		'creator'   => 'orwo_creator',
		'label'     => 'orwo_label',
		'decade'    => 'orwo_decade',
		'condition' => 'orwo_condition',
	);
	foreach ( $taxonomy_filters as $var => $taxonomy ) {
		if ( empty( $filters[ $var ] ) || $context['skip'] === $var ) {
			continue;
		}
		if ( ! empty( $context[ $var ] ) ) {
			// The path already pins this taxonomy, so the query string cannot widen it.
			continue;
		}
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $filters[ $var ],
			'operator' => 'IN',
		);
	}

	$fields = Orwo_Formats::all_fields();
	foreach ( orwo_field_filter_keys() as $key ) {
		if ( ! isset( $filters[ $key ] ) || $context['skip'] === $key ) {
			continue;
		}
		$meta_key = Orwo_Formats::meta_key( $key );
		if ( 'bool' === $fields[ $key ]['field']['type'] ) {
			$meta_query[] = array(
				'key'   => $meta_key,
				'value' => '1',
			);
			continue;
		}
		$meta_query[] = array(
			'key'     => $meta_key,
			'value'   => (array) $filters[ $key ],
			'compare' => 'IN',
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term relationships.
	}
	if ( count( $meta_query ) > 1 ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small per-format field set.
	}

	if ( ! empty( $filters['q'] ) ) {
		// The shop search covers the same ground as the chatbot: titles, creators, genres, labels and the chat
		// keywords, not just the post content WordPress would search on its own.
		$found             = orwo_search_ids( (string) $filters['q'] );
		$args['post__in']  = $found ? $found : array( 0 );
	}

	// Price, stock, sale and sorting are handled against the product lookup table in orwo_lookup_clauses().
	$args['orwo_price']   = array(
		'min' => isset( $filters['min_price'] ) && 'price' !== $context['skip'] ? $filters['min_price'] : null,
		'max' => isset( $filters['max_price'] ) && 'price' !== $context['skip'] ? $filters['max_price'] : null,
	);
	$args['orwo_instock'] = ! empty( $filters['instock'] ) && 'instock' !== $context['skip'];
	$args['orwo_onsale']  = 'sale' === $context['view'];
	$args['orwo_sort']    = isset( $filters['sort'] ) ? $filters['sort'] : ( 'sale' === $context['view'] || 'new' === $context['view'] ? 'newest' : 'newest' );

	if ( 'new' === $context['view'] ) {
		$args['orwo_sort'] = 'newest';
	}

	return $args;
}

/**
 * The product IDs matching a search term across titles, creators, genres, labels and chat keywords.
 *
 * Each word is looked for separately, so "miles jazz" finds a Miles Davis jazz record. The chatbot does the finer
 * scoring in the browser; this only has to gather the candidates.
 *
 * @param string $term Search term.
 * @return int[]
 */
function orwo_search_ids( $term ) {
	global $wpdb;

	$term  = trim( $term );
	$words = array_slice(
		array_values(
			array_filter(
				preg_split( '/\s+/', $term ),
				static function ( $word ) {
					return mb_strlen( $word ) > 1;
				}
			)
		),
		0,
		6
	);
	if ( ! $words && '' !== $term ) {
		$words = array( $term );
	}
	if ( ! $words ) {
		return array();
	}

	$ids = array();

	foreach ( $words as $word ) {
		$like = '%' . $wpdb->esc_like( $word ) . '%';

		$by_text = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'product' AND post_status = 'publish'
				 AND ( post_title LIKE %s OR post_excerpt LIKE %s )
				 LIMIT 200",
				$like,
				$like
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Search over an indexed post type, capped.

		$by_term = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tr.object_id FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 WHERE tt.taxonomy IN ( 'orwo_creator', 'orwo_genre', 'orwo_label', 'orwo_format' )
				 AND t.name LIKE %s
				 LIMIT 200",
				$like
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Indexed term relationships, capped.

		$by_meta = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value LIKE %s
				 LIMIT 200",
				Orwo_Formats::meta_key( 'chat_keywords' ),
				$like
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One meta key, capped.

		$ids = array_merge( $ids, $by_text, $by_term, $by_meta );
	}

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Marks our own queries so the lookup-table clauses only apply to them.
 *
 * @param array<string,mixed> $clauses Query clauses.
 * @param WP_Query            $query   Query object.
 * @return array<string,mixed>
 */
function orwo_lookup_clauses( $clauses, $query ) {
	if ( ! $query->get( 'orwo_filtered' ) || ! orwo_woocommerce_active() ) {
		return $clauses;
	}

	global $wpdb;
	$price = (array) $query->get( 'orwo_price' );
	$sort  = (string) $query->get( 'orwo_sort' );

	// WooCommerce joins this table under the bare alias and references it that way in its own price-filter clause,
	// so we use the same alias and the same guard. Using an alias of our own leaves one of the two clauses pointing
	// at a table that is not in the query.
	$table = $wpdb->prefix . 'wc_product_meta_lookup';
	$alias = 'wc_product_meta_lookup';
	if ( ! preg_match( '/\bwc_product_meta_lookup\b/', (string) $clauses['join'] ) ) {
		$clauses['join'] .= " LEFT JOIN {$table} {$alias} ON {$wpdb->posts}.ID = {$alias}.product_id ";
	}

	if ( isset( $price['min'] ) && null !== $price['min'] ) {
		$clauses['where'] .= $wpdb->prepare( " AND {$alias}.max_price >= %f ", (float) $price['min'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed alias.
	}
	if ( isset( $price['max'] ) && null !== $price['max'] ) {
		$clauses['where'] .= $wpdb->prepare( " AND {$alias}.min_price <= %f ", (float) $price['max'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed alias.
	}
	if ( $query->get( 'orwo_instock' ) ) {
		$clauses['where'] .= " AND {$alias}.stock_status = 'instock' ";
	}
	if ( $query->get( 'orwo_onsale' ) ) {
		$clauses['where'] .= " AND {$alias}.onsale = 1 ";
	}

	switch ( $sort ) {
		case 'price-asc':
			$clauses['orderby'] = "{$alias}.min_price ASC, " . $wpdb->posts . '.ID DESC';
			break;
		case 'price-desc':
			$clauses['orderby'] = "{$alias}.max_price DESC, " . $wpdb->posts . '.ID DESC';
			break;
		case 'popular':
			$clauses['orderby'] = "{$alias}.total_sales DESC, " . $wpdb->posts . '.post_date DESC';
			break;
	}

	// Sold-out items stay visible but sort last, whichever sort is chosen. The comparison is written so that both
	// MySQL and the SQLite drop-in used by the local sandbox understand it.
	if ( ! $query->get( 'orwo_instock' ) && '' !== (string) $clauses['orderby'] ) {
		$clauses['orderby'] = "({$alias}.stock_status = 'outofstock') ASC, " . $clauses['orderby'];
	}

	return $clauses;
}
add_filter( 'posts_clauses', 'orwo_lookup_clauses', 20, 2 );

/**
 * Applies the orderings that need no lookup table, and keeps sold-out items last.
 *
 * @param WP_Query $query Query object.
 */
function orwo_apply_sort( $query ) {
	if ( ! $query->get( 'orwo_filtered' ) ) {
		return;
	}

	switch ( (string) $query->get( 'orwo_sort' ) ) {
		case 'title-asc':
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			break;
		case 'year-desc':
			$query->set( 'meta_key', Orwo_Formats::meta_key( 'year' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Explicit sort choice.
			$query->set( 'orderby', array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) );
			break;
		case 'price-asc':
		case 'price-desc':
		case 'popular':
			break;
		default:
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
	}
}

/**
 * Turns the whitelisted query variables into the main catalogue query.
 *
 * This is the version that works with JavaScript switched off.
 *
 * @param WP_Query $query Query object.
 */
function orwo_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$context = orwo_view_context( $query );
	if ( ! $context ) {
		return;
	}

	$args = orwo_query_args( orwo_current_filters(), $context );

	foreach ( $args as $key => $value ) {
		if ( in_array( $key, array( 'paged', 'post_status' ), true ) && $query->get( $key ) ) {
			continue;
		}
		$query->set( $key, $value );
	}
	$query->set( 'post_type', 'product' );
	orwo_apply_sort( $query );
}
add_action( 'pre_get_posts', 'orwo_pre_get_posts' );

/**
 * Describes the catalogue view a query represents, or null when it is not one.
 *
 * @param WP_Query $query Query object.
 * @return array<string,mixed>|null
 */
function orwo_view_context( $query ) {
	$view = (string) $query->get( 'orwo_view' );
	$base = array(
		'format'   => '',
		'genre'    => '',
		'creator'  => '',
		'label'    => '',
		'view'     => $view,
		'paged'    => max( 1, (int) $query->get( 'paged' ) ),
		'per_page' => ORWO_PER_PAGE,
	);

	if ( in_array( $view, array( 'new', 'sale', 'search' ), true ) ) {
		return $base;
	}
	if ( $query->get( 'orwo_format' ) ) {
		$base['format'] = sanitize_title( (string) $query->get( 'orwo_format' ) );
		$base['view']   = 'format';

		return $base;
	}
	foreach ( array(
		'orwo_genre'   => 'genre',
		'orwo_creator' => 'creator',
		'orwo_label'   => 'label',
	) as $var => $key ) {
		if ( $query->get( $var ) ) {
			$base[ $key ] = sanitize_title( (string) $query->get( $var ) );
			$base['view'] = $key;

			return $base;
		}
	}
	if ( function_exists( 'is_shop' ) && $query->is_post_type_archive( 'product' ) ) {
		$base['view'] = 'shop';

		return $base;
	}

	return null;
}

/**
 * Live option counts for every facet in a context.
 *
 * Each facet is counted with all the other filters applied, so the numbers answer "how many would I get if I also
 * chose this". Options with no results are returned as zero so the panel can disable them.
 *
 * @param array<string,mixed> $filters Parsed filters.
 * @param array<string,mixed> $context View context.
 * @return array<string,array<string,int>> Counts keyed by facet then option value.
 */
function orwo_facet_counts( $filters, $context ) {
	$format = (string) ( $context['format'] ?? '' );
	$facets = orwo_facet_definitions( $format );
	$key    = 'orwo_counts_' . md5( wp_json_encode( array( $filters, $context, orwo_catalogue_version() ) ) );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$counts    = array();
	$base_ids  = null;
	$id_cache  = array();

	foreach ( $facets as $var => $facet ) {
		if ( in_array( $facet['source'], array( 'price', 'stock' ), true ) ) {
			continue;
		}

		// Counting a facet ignores that facet's own selection, so a visitor can always widen it again.
		$skip = isset( $filters[ $var ] ) ? $var : '';
		if ( '' === $skip ) {
			if ( null === $base_ids ) {
				$base_ids = orwo_matching_ids( $filters, $context, '' );
			}
			$ids = $base_ids;
		} else {
			if ( ! isset( $id_cache[ $skip ] ) ) {
				$id_cache[ $skip ] = orwo_matching_ids( $filters, $context, $skip );
			}
			$ids = $id_cache[ $skip ];
		}

		if ( 'taxonomy' === $facet['source'] ) {
			$counts[ $var ] = orwo_count_terms( $ids, $facet['taxonomy'] );
		} else {
			$counts[ $var ] = orwo_count_meta( $ids, $facet['meta_key'] );
		}
	}

	set_transient( $key, $counts, 12 * HOUR_IN_SECONDS );

	return $counts;
}

/**
 * The product IDs matching a filter set.
 *
 * @param array<string,mixed> $filters Parsed filters.
 * @param array<string,mixed> $context View context.
 * @param string              $skip    Facet to ignore.
 * @return int[]
 */
function orwo_matching_ids( $filters, $context, $skip = '' ) {
	$args             = orwo_query_args( $filters, array_merge( $context, array( 'ids_only' => true, 'skip' => $skip ) ) );
	$query            = new WP_Query();
	$args['fields']   = 'ids';
	$posts            = $query->query( $args );

	return array_map( 'intval', (array) $posts );
}

/**
 * Counts term slugs across a set of products.
 *
 * @param int[]  $ids      Product IDs.
 * @param string $taxonomy Taxonomy.
 * @return array<string,int>
 */
function orwo_count_terms( $ids, $taxonomy ) {
	global $wpdb;
	if ( empty( $ids ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$sql          = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from a counted integer array.
		"SELECT t.slug AS slug, COUNT(DISTINCT tr.object_id) AS total
		 FROM {$wpdb->term_relationships} tr
		 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE tt.taxonomy = %s AND tr.object_id IN ({$placeholders})
		 GROUP BY t.slug",
		array_merge( array( $taxonomy ), $ids )
	);

	$rows   = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared above; counts are cached.
	$counts = array();
	foreach ( (array) $rows as $row ) {
		$counts[ (string) $row->slug ] = (int) $row->total;
	}

	return $counts;
}

/**
 * Counts meta values across a set of products.
 *
 * @param int[]  $ids      Product IDs.
 * @param string $meta_key Meta key.
 * @return array<string,int>
 */
function orwo_count_meta( $ids, $meta_key ) {
	global $wpdb;
	if ( empty( $ids ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$sql          = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from a counted integer array.
		"SELECT meta_value AS value, COUNT(DISTINCT post_id) AS total
		 FROM {$wpdb->postmeta}
		 WHERE meta_key = %s AND post_id IN ({$placeholders}) AND meta_value <> ''
		 GROUP BY meta_value",
		array_merge( array( $meta_key ), $ids )
	);

	$rows   = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared above; counts are cached.
	$counts = array();
	foreach ( (array) $rows as $row ) {
		$counts[ (string) $row->value ] = (int) $row->total;
	}

	return $counts;
}

/**
 * The catalogue version, bumped whenever a product changes so cached counts and indexes fall out of date.
 *
 * @return int
 */
function orwo_catalogue_version() {
	$version = (int) get_option( 'orwo_catalogue_version', 0 );

	return $version > 0 ? $version : 1;
}

/**
 * Invalidates the cached option counts.
 */
function orwo_flush_filter_cache() {
	update_option( 'orwo_catalogue_version', orwo_catalogue_version() + 1, false );
}

/**
 * The active filters as removable chips.
 *
 * @param array<string,mixed> $filters Parsed filters.
 * @param array<string,mixed> $context View context.
 * @return array<int,array{facet:string,value:string,label:string}>
 */
function orwo_filter_chips( $filters, $context ) {
	$facets = orwo_facet_definitions( (string) ( $context['format'] ?? '' ) );
	$chips  = array();

	foreach ( $facets as $var => $facet ) {
		if ( 'price' === $facet['source'] ) {
			$min = $filters['min_price'] ?? null;
			$max = $filters['max_price'] ?? null;
			if ( null === $min && null === $max ) {
				continue;
			}
			$chips[] = array(
				'facet' => 'price',
				'value' => '',
				'label' => orwo_price_chip_label( $min, $max ),
			);
			continue;
		}
		if ( 'stock' === $facet['source'] ) {
			if ( empty( $filters['instock'] ) ) {
				continue;
			}
			$chips[] = array(
				'facet' => 'instock',
				'value' => '1',
				'label' => __( 'In stock only', 'orwo-core' ),
			);
			continue;
		}
		if ( empty( $filters[ $var ] ) ) {
			continue;
		}
		if ( true === $filters[ $var ] ) {
			$chips[] = array(
				'facet' => $var,
				'value' => '1',
				'label' => $facet['label'],
			);
			continue;
		}
		foreach ( (array) $filters[ $var ] as $value ) {
			$chips[] = array(
				'facet' => $var,
				'value' => (string) $value,
				'label' => orwo_facet_option_label( $facet, (string) $value ),
			);
		}
	}

	return $chips;
}

/**
 * The label of one facet option.
 *
 * @param array  $facet Facet definition.
 * @param string $value Option value or term slug.
 * @return string
 */
function orwo_facet_option_label( $facet, $value ) {
	if ( 'taxonomy' === $facet['source'] ) {
		$term = get_term_by( 'slug', $value, $facet['taxonomy'] );

		return $term && ! is_wp_error( $term ) ? $term->name : $value;
	}

	return html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Human label for an active price range.
 *
 * @param float|null $min Minimum.
 * @param float|null $max Maximum.
 * @return string
 */
function orwo_price_chip_label( $min, $max ) {
	$format = static function ( $amount ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format_i18n( (float) $amount, 2 );
		}

		// wc_price() returns markup with currency entities: a chip needs the plain characters.
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	};

	if ( null !== $min && null !== $max ) {
		/* translators: 1: minimum price, 2: maximum price. */
		return sprintf( __( '%1$s to %2$s', 'orwo-core' ), $format( $min ), $format( $max ) );
	}
	if ( null !== $min ) {
		/* translators: %s: minimum price. */
		return sprintf( __( '%s and over', 'orwo-core' ), $format( $min ) );
	}

	/* translators: %s: maximum price. */
	return sprintf( __( 'Up to %s', 'orwo-core' ), $format( $max ) );
}

/**
 * The price range of the catalogue, used for the range slider bounds.
 *
 * @return array{min:float,max:float}
 */
function orwo_price_bounds() {
	global $wpdb;
	if ( ! orwo_woocommerce_active() ) {
		return array( 'min' => 0.0, 'max' => 0.0 );
	}

	$key    = 'orwo_price_bounds_' . orwo_catalogue_version();
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from the prefix; result cached.
	$row = $wpdb->get_row( "SELECT MIN(min_price) AS low, MAX(max_price) AS high FROM {$lookup} WHERE min_price IS NOT NULL" );

	$bounds = array(
		'min' => $row ? (float) floor( (float) $row->low ) : 0.0,
		'max' => $row ? (float) ceil( (float) $row->high ) : 0.0,
	);
	set_transient( $key, $bounds, 12 * HOUR_IN_SECONDS );

	return $bounds;
}

/**
 * Builds a catalogue URL for a base path plus filters.
 *
 * @param string              $base    Absolute base URL.
 * @param array<string,mixed> $filters Parsed filters.
 * @return string
 */
function orwo_filter_url( $base, $filters ) {
	$query = array();

	foreach ( $filters as $var => $value ) {
		if ( true === $value ) {
			$query[ $var ] = '1';
		} elseif ( is_array( $value ) ) {
			$query[ $var ] = implode( ',', $value );
		} elseif ( '' !== (string) $value ) {
			$query[ $var ] = (string) $value;
		}
	}

	return $query ? add_query_arg( $query, $base ) : $base;
}
