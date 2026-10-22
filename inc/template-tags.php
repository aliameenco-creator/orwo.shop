<?php
/**
 * Template tags: breadcrumbs, the archive header, the filter panel, chips, sorting and pagination.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The breadcrumb trail of the current page: Home, Format, Product.
 *
 * @return array<int,array{label:string,url:string}>
 */
function orwo_breadcrumbs() {
	$crumbs = array(
		array(
			'label' => __( 'Home', 'orwo' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_front_page() ) {
		return $crumbs;
	}

	if ( function_exists( 'is_product' ) && is_product() && orwo_core_active() ) {
		$id     = get_queried_object_id();
		$format = orwo_product_format( $id );
		if ( $format ) {
			$crumbs[] = array(
				'label' => Orwo_Formats::label( $format ),
				'url'   => user_trailingslashit( home_url( '/' . $format ) ),
			);
		}
		$crumbs[] = array(
			'label' => get_the_title( $id ),
			'url'   => '',
		);

		return $crumbs;
	}

	if ( orwo_is_catalogue_view() ) {
		$view = orwo_current_view();
		if ( in_array( $view['kind'], array( 'genre', 'creator', 'label' ), true ) ) {
			$crumbs[] = array(
				'label' => __( 'All products', 'orwo' ),
				'url'   => orwo_shop_url(),
			);
		}
		$crumbs[] = array(
			'label' => $view['title'],
			'url'   => '',
		);

		return $crumbs;
	}

	if ( is_singular() ) {
		$parent_id = (int) get_post_field( 'post_parent', get_queried_object_id() );
		if ( $parent_id ) {
			$crumbs[] = array(
				'label' => get_the_title( $parent_id ),
				'url'   => (string) get_permalink( $parent_id ),
			);
		}
		$crumbs[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);

		return $crumbs;
	}

	if ( is_search() ) {
		$crumbs[] = array(
			'label' => __( 'Search', 'orwo' ),
			'url'   => '',
		);
	} elseif ( is_404() ) {
		$crumbs[] = array(
			'label' => __( 'Page not found', 'orwo' ),
			'url'   => '',
		);
	} elseif ( is_archive() || is_home() ) {
		$crumbs[] = array(
			'label' => wp_strip_all_tags( (string) get_the_archive_title() ),
			'url'   => '',
		);
	}

	return $crumbs;
}

/**
 * Prints the breadcrumb trail.
 */
function orwo_the_breadcrumbs() {
	$crumbs = orwo_breadcrumbs();
	if ( count( $crumbs ) < 2 ) {
		return;
	}
	?>
	<nav class="orwo-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'orwo' ); ?>">
		<div class="orwo-wrap">
			<ol>
				<?php foreach ( $crumbs as $crumb ) : ?>
					<li>
						<?php if ( $crumb['url'] ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</nav>
	<?php
}

/**
 * The sort dropdown. It submits on its own with JavaScript, and as a form without it.
 *
 * @param array<string,mixed> $view    Current view.
 * @param array<string,mixed> $filters Active filters.
 */
function orwo_sort_control( $view, $filters ) {
	$current = isset( $filters['sort'] ) ? $filters['sort'] : 'newest';
	$hidden  = $filters;
	unset( $hidden['sort'] );
	?>
	<form class="orwo-sort" method="get" action="<?php echo esc_url( $view['base'] ); ?>" data-orwo-sort>
		<?php foreach ( $hidden as $key => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( is_array( $value ) ? implode( ',', $value ) : ( true === $value ? '1' : $value ) ); ?>">
		<?php endforeach; ?>
		<label for="orwo-sort-select"><?php esc_html_e( 'Sort by', 'orwo' ); ?></label>
		<select name="sort" id="orwo-sort-select">
			<?php foreach ( orwo_sort_options() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="orwo-button orwo-button--quiet"><?php esc_html_e( 'Apply', 'orwo' ); ?></button></noscript>
	</form>
	<?php
}

/**
 * Pagination for a catalogue view: real links, with a Load more button added by the script.
 *
 * @param WP_Query            $query   Query.
 * @param array<string,mixed> $view    Current view.
 * @param array<string,mixed> $filters Active filters.
 */
function orwo_pagination( $query, $view, $filters ) {
	$total = (int) $query->max_num_pages;
	if ( $total < 2 ) {
		return;
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$links = paginate_links(
		array(
			'base'      => orwo_filter_url( trailingslashit( $view['base'] ) . 'page/%#%/', $filters ),
			'format'    => '',
			'current'   => $paged,
			'total'     => $total,
			'mid_size'  => 1,
			'type'      => 'array',
			'prev_text' => __( 'Previous', 'orwo' ),
			'next_text' => __( 'Next', 'orwo' ),
		)
	);

	if ( ! $links ) {
		return;
	}
	?>
	<nav class="orwo-pagination" aria-label="<?php esc_attr_e( 'Products', 'orwo' ); ?>" data-orwo-pagination data-total="<?php echo esc_attr( (string) $total ); ?>" data-paged="<?php echo esc_attr( (string) $paged ); ?>">
		<?php if ( $paged < $total ) : ?>
			<button type="button" class="orwo-button orwo-button--more" data-orwo-more hidden><?php esc_html_e( 'Load more', 'orwo' ); ?></button>
		<?php endif; ?>
		<ul class="orwo-pagination__list">
			<?php foreach ( $links as $link ) : ?>
				<li><?php echo wp_kses_post( $link ); ?></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * The format bar links, in the fixed order.
 *
 * @return array<int,array{label:string,url:string,slug:string}>
 */
function orwo_format_links() {
	if ( ! orwo_core_active() ) {
		return array();
	}

	$links = array();
	foreach ( Orwo_Formats::all() as $slug => $format ) {
		$links[] = array(
			'slug'  => $slug,
			'label' => $format['label'],
			'url'   => user_trailingslashit( home_url( '/' . $slug ) ),
		);
	}

	return $links;
}

/**
 * The item count of a format, for the homepage tiles.
 *
 * @param string $slug Format slug.
 * @return int
 */
function orwo_format_count( $slug ) {
	$term = get_term_by( 'slug', $slug, 'orwo_format' );

	return ( $term && ! is_wp_error( $term ) ) ? (int) $term->count : 0;
}

/**
 * Top terms of a taxonomy within one format, used by the mega menu.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $format   Format slug, or empty for the whole catalogue.
 * @param int    $limit    How many.
 * @return WP_Term[]
 */
function orwo_top_terms( $taxonomy, $format = '', $limit = 6 ) {
	$cache_key = 'orwo_top_' . md5( $taxonomy . '|' . $format . '|' . $limit . '|' . ( orwo_core_active() ? orwo_catalogue_version() : '0' ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_filter( array_map( 'get_term', $cached ) );
	}

	if ( '' === $format ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => $limit,
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;
	} else {
		// Terms that actually occur in this format, counted across that format's products only.
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Cached menu data.
					array(
						'taxonomy' => 'orwo_format',
						'field'    => 'slug',
						'terms'    => array( $format ),
					),
				),
			)
		);

		$counts = $ids && orwo_core_active() ? orwo_count_terms( $ids, $taxonomy ) : array();
		arsort( $counts );
		$terms = array();
		foreach ( array_slice( array_keys( $counts ), 0, $limit ) as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$terms[] = $term;
			}
		}
	}

	set_transient( $cache_key, wp_list_pluck( $terms, 'term_id' ), 12 * HOUR_IN_SECONDS );

	return $terms;
}

/**
 * The search form used in the header and on /search/.
 *
 * @param string $value Current term.
 */
function orwo_search_form( $value = '' ) {
	?>
	<form class="orwo-search" method="get" action="<?php echo esc_url( user_trailingslashit( home_url( '/search' ) ) ); ?>" role="search" data-orwo-search>
		<label class="orwo-visually-hidden" for="orwo-search-field"><?php esc_html_e( 'Search the shop', 'orwo' ); ?></label>
		<input type="search" id="orwo-search-field" name="q" value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php esc_attr_e( 'Search titles, artists, directors…', 'orwo' ); ?>" autocomplete="off"
			aria-describedby="orwo-search-hint">
		<button type="submit" class="orwo-search__submit">
			<span class="orwo-visually-hidden"><?php esc_html_e( 'Search', 'orwo' ); ?></span>
			<svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6">
				<circle cx="7.5" cy="7.5" r="5.5"></circle>
				<path d="M11.5 11.5 16 16"></path>
			</svg>
		</button>
		<p class="orwo-visually-hidden" id="orwo-search-hint"><?php esc_html_e( 'Suggestions appear as you type.', 'orwo' ); ?></p>
		<div class="orwo-search__suggestions" data-orwo-suggestions hidden></div>
	</form>
	<?php
}

/**
 * The current year, always rendered at runtime so a footer never carries a stale year.
 *
 * @return string
 */
function orwo_current_year() {
	return current_time( 'Y' );
}
