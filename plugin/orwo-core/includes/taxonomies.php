<?php
/**
 * Catalogue taxonomies.
 *
 * Format, genre, creator, label, decade and condition are taxonomies rather than meta so that filtering and option
 * counts use indexed term relationships instead of slow meta queries. Decade and condition are kept in step with the
 * Year and Condition inputs on the product screen.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the six catalogue taxonomies.
 *
 * Public term archives (genre, creator, label) are resolved by orwo_resolve_request() rather than by rewrite rules,
 * so a host that never flushes rewrites still serves /genre/jazz/ correctly.
 */
function orwo_register_taxonomies() {
	$defaults = array(
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'show_admin_column'  => true,
		'hierarchical'       => false,
		'rewrite'            => false,
		'query_var'          => true,
	);

	register_taxonomy(
		'orwo_format',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'       => orwo_taxonomy_labels( __( 'Format', 'orwo-core' ), __( 'Formats', 'orwo-core' ) ),
				'meta_box_cb'  => false,
				'show_in_menu' => true,
				'capabilities' => array(
					// Formats are fixed at launch: nobody edits them from the product screen.
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_products',
				),
			)
		)
	);

	register_taxonomy(
		'orwo_genre',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'      => orwo_taxonomy_labels( __( 'Genre', 'orwo-core' ), __( 'Genres', 'orwo-core' ) ),
				'meta_box_cb' => false,
			)
		)
	);

	register_taxonomy(
		'orwo_creator',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'      => orwo_taxonomy_labels( __( 'Creator', 'orwo-core' ), __( 'Creators', 'orwo-core' ) ),
				'meta_box_cb' => false,
			)
		)
	);

	register_taxonomy(
		'orwo_label',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'      => orwo_taxonomy_labels( __( 'Label or studio', 'orwo-core' ), __( 'Labels and studios', 'orwo-core' ) ),
				'meta_box_cb' => false,
			)
		)
	);

	register_taxonomy(
		'orwo_decade',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'            => orwo_taxonomy_labels( __( 'Decade', 'orwo-core' ), __( 'Decades', 'orwo-core' ) ),
				'meta_box_cb'       => false,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				// Derived from the Year field on save, never edited by hand.
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_products',
				),
			)
		)
	);

	register_taxonomy(
		'orwo_condition',
		array( 'product' ),
		array_merge(
			$defaults,
			array(
				'labels'            => orwo_taxonomy_labels( __( 'Condition', 'orwo-core' ), __( 'Conditions', 'orwo-core' ) ),
				'meta_box_cb'       => false,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_products',
				),
			)
		)
	);
}
add_action( 'init', 'orwo_register_taxonomies', 5 );

/**
 * Standard label set for a taxonomy.
 *
 * @param string $singular Singular name.
 * @param string $plural   Plural name.
 * @return array<string,string>
 */
function orwo_taxonomy_labels( $singular, $plural ) {
	return array(
		'name'                       => $plural,
		'singular_name'              => $singular,
		'menu_name'                  => $plural,
		'search_items'               => sprintf( /* translators: %s: plural taxonomy name. */ __( 'Search %s', 'orwo-core' ), $plural ),
		'all_items'                  => sprintf( /* translators: %s: plural taxonomy name. */ __( 'All %s', 'orwo-core' ), $plural ),
		'edit_item'                  => sprintf( /* translators: %s: singular taxonomy name. */ __( 'Edit %s', 'orwo-core' ), $singular ),
		'update_item'                => sprintf( /* translators: %s: singular taxonomy name. */ __( 'Update %s', 'orwo-core' ), $singular ),
		'add_new_item'               => sprintf( /* translators: %s: singular taxonomy name. */ __( 'Add new %s', 'orwo-core' ), $singular ),
		'new_item_name'              => sprintf( /* translators: %s: singular taxonomy name. */ __( 'New %s name', 'orwo-core' ), $singular ),
		'not_found'                  => sprintf( /* translators: %s: plural taxonomy name. */ __( 'No %s found', 'orwo-core' ), strtolower( $plural ) ),
		'separate_items_with_commas' => sprintf( /* translators: %s: plural taxonomy name. */ __( 'Separate %s with commas', 'orwo-core' ), strtolower( $plural ) ),
	);
}

/**
 * Seeds the fixed format and condition terms. Run on activation and from the plugin's own setup check.
 */
function orwo_seed_terms() {
	foreach ( Orwo_Formats::all() as $slug => $format ) {
		if ( ! term_exists( $slug, 'orwo_format' ) ) {
			wp_insert_term( $format['label'], 'orwo_format', array( 'slug' => $slug ) );
		}
	}
	foreach ( Orwo_Formats::CONDITIONS as $condition ) {
		if ( ! term_exists( sanitize_title( $condition ), 'orwo_condition' ) ) {
			wp_insert_term( $condition, 'orwo_condition', array( 'slug' => sanitize_title( $condition ) ) );
		}
	}
}

/**
 * Format terms in the fixed order declared by the registry, skipping any that are missing.
 *
 * @param bool $hide_empty Only return formats that have published products.
 * @return WP_Term[]
 */
function orwo_format_terms( $hide_empty = false ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'orwo_format',
			'hide_empty' => (bool) $hide_empty,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$by_slug = array();
	foreach ( $terms as $term ) {
		$by_slug[ $term->slug ] = $term;
	}
	$ordered = array();
	foreach ( Orwo_Formats::slugs() as $slug ) {
		if ( isset( $by_slug[ $slug ] ) ) {
			$ordered[] = $by_slug[ $slug ];
		}
	}

	return $ordered;
}

/**
 * The format slug of a product, or an empty string when none is set.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function orwo_product_format( $product_id ) {
	$terms = get_the_terms( $product_id, 'orwo_format' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	return $terms[0]->slug;
}

/**
 * The condition of a product, for example "Very good".
 *
 * @param int $product_id Product ID.
 * @return string
 */
function orwo_product_condition( $product_id ) {
	$terms = get_the_terms( $product_id, 'orwo_condition' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	return $terms[0]->name;
}

/**
 * The first term name of a taxonomy on a product, used for the creator line on cards.
 *
 * @param int    $product_id Product ID.
 * @param string $taxonomy   Taxonomy name.
 * @return WP_Term|null
 */
function orwo_first_term( $product_id, $taxonomy ) {
	$terms = get_the_terms( $product_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	return $terms[0];
}
