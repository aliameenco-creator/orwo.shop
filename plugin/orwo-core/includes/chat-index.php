<?php
/**
 * The chatbot index.
 *
 * One JSON file written to the uploads folder and served as a static file, so it is cached by the CDN and costs the
 * server nothing. It holds only what the widget needs to match and show a product; no personal data and no prices
 * beyond the public ones already on the page.
 *
 * The index is rebuilt when a product is saved, trashed or its stock changes, and the URL carries the catalogue
 * version so a stale copy is never served.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Index records per build. Beyond this the same matching logic should move to a server endpoint.
 */
const ORWO_INDEX_LIMIT = 5000;

/**
 * Schedules a rebuild at the end of the request rather than rebuilding once per saved field.
 */
function orwo_queue_chat_index_rebuild() {
	if ( ! did_action( 'shutdown' ) ) {
		$GLOBALS['orwo_index_dirty'] = true;
	}
	update_option( 'orwo_index_stale', 1, false );
}

/**
 * Rebuilds the index on shutdown when something changed during the request.
 */
function orwo_maybe_rebuild_chat_index() {
	if ( ! empty( $GLOBALS['orwo_index_dirty'] ) ) {
		orwo_build_chat_index();
	}
}
add_action( 'shutdown', 'orwo_maybe_rebuild_chat_index' );

/**
 * The folder and URL the index is written to.
 *
 * @return array{dir:string,url:string}
 */
function orwo_chat_index_location() {
	$uploads = wp_get_upload_dir();

	return array(
		'dir' => trailingslashit( $uploads['basedir'] ) . 'orwo-chat',
		'url' => trailingslashit( $uploads['baseurl'] ) . 'orwo-chat',
	);
}

/**
 * The public URL of the current index, rebuilding it first when it is missing or stale.
 *
 * @return string
 */
function orwo_chat_index_url() {
	$location = orwo_chat_index_location();
	$version  = orwo_catalogue_version();
	$file     = $location['dir'] . '/index-' . $version . '.json';

	if ( ! file_exists( $file ) ) {
		orwo_build_chat_index();
	}

	return $location['url'] . '/index-' . $version . '.json';
}

/**
 * Writes the index file and removes older builds.
 *
 * @return array<string,mixed>|null The index, or null when it could not be written.
 */
function orwo_build_chat_index() {
	unset( $GLOBALS['orwo_index_dirty'] );

	$location = orwo_chat_index_location();
	if ( ! wp_mkdir_p( $location['dir'] ) ) {
		return null;
	}

	$index = orwo_chat_index_data();
	$file  = $location['dir'] . '/index-' . orwo_catalogue_version() . '.json';

	$written = file_put_contents( $file, wp_json_encode( $index ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Plain cache file in uploads.
	if ( false === $written ) {
		return null;
	}

	// Keep the folder tidy: only the current build is ever requested.
	foreach ( (array) glob( $location['dir'] . '/index-*.json' ) as $existing ) {
		if ( $existing !== $file ) {
			wp_delete_file( $existing );
		}
	}
	update_option( 'orwo_index_stale', 0, false );

	return $index;
}

/**
 * Builds the index data: products, the filter definitions the guided finder reuses, and the FAQ answers.
 *
 * @return array<string,mixed>
 */
function orwo_chat_index_data() {
	$products = array();

	$query = new WP_Query();
	$query->query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => ORWO_INDEX_LIMIT,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	foreach ( $query->posts as $post ) {
		$id      = $post->ID;
		$format  = orwo_product_format( $id );
		$creator = orwo_first_term( $id, 'orwo_creator' );
		$genre   = orwo_first_term( $id, 'orwo_genre' );
		$product = orwo_wc_product( $id );

		$record = array(
			'id'        => $id,
			'title'     => get_the_title( $id ),
			'creator'   => $creator ? $creator->name : '',
			'genre'     => $genre ? $genre->slug : '',
			'format'    => $format,
			'condition' => orwo_product_condition( $id ),
			'price'     => $product ? (float) $product->get_price() : 0.0,
			'priceHtml' => wp_strip_all_tags( orwo_price_html( $id ) ),
			'inStock'   => ! orwo_is_sold_out( $id ),
			'url'       => (string) get_permalink( $id ),
			'thumb'     => (string) get_the_post_thumbnail_url( $id, 'orwo-card' ),
			'keywords'  => array_values( array_filter( array_map( 'trim', explode( ',', (string) orwo_field( $id, 'chat_keywords' ) ) ) ) ),
			'why'       => (string) orwo_field( $id, 'why_we_like_it' ),
			'fields'    => array(),
		);

		// Only the fields the finder asks about travel with the index, which keeps it small.
		foreach ( Orwo_Formats::fields( $format, 'filter' ) as $key => $field ) {
			$value = orwo_field( $id, $key );
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( '' !== (string) $value && false !== $value ) {
				$record['fields'][ $key ] = is_bool( $value ) ? '1' : (string) $value;
			}
		}

		$products[] = $record;
	}

	$formats = array();
	foreach ( Orwo_Formats::all() as $slug => $format ) {
		$finder = array();
		foreach ( Orwo_Formats::fields( $slug, 'finder' ) as $key => $field ) {
			$finder[] = array(
				'key'      => $key,
				'label'    => $field['label'],
				'question' => sprintf( /* translators: %s: field label, for example Region. */ __( 'Any preference on %s?', 'orwo-core' ), strtolower( $field['label'] ) ),
				'options'  => array_map( static function ( $option ) {
					return html_entity_decode( $option, ENT_QUOTES, 'UTF-8' );
				}, $field['options'] ),
			);
		}
		$formats[] = array(
			'slug'   => $slug,
			'label'  => $format['label'],
			'url'    => user_trailingslashit( home_url( '/' . $slug ) ),
			'finder' => $finder,
		);
	}

	$answers = array();
	$found   = get_posts(
		array(
			'post_type'      => 'orwo_answer',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	foreach ( $found as $answer ) {
		$answers[] = array(
			'title'      => $answer->post_title,
			'text'       => wp_strip_all_tags( (string) apply_filters( 'the_content', $answer->post_content ) ),
			'triggers'   => orwo_answer_triggers( $answer->ID ),
			'buttonText' => (string) get_post_meta( $answer->ID, '_orwo_button_text', true ),
			'buttonUrl'  => (string) get_post_meta( $answer->ID, '_orwo_button_url', true ),
		);
	}

	return array(
		'version'   => orwo_catalogue_version(),
		'built'     => gmdate( 'c' ),
		'genres'    => orwo_index_terms( 'orwo_genre' ),
		'creators'  => orwo_index_terms( 'orwo_creator' ),
		'formats'   => $formats,
		'conditions' => Orwo_Formats::CONDITIONS,
		'budgets'   => orwo_budget_bands(),
		'synonyms'  => orwo_chat_synonyms(),
		'answers'   => $answers,
		'products'  => $products,
		'truncated' => count( $query->posts ) >= ORWO_INDEX_LIMIT,
	);
}

/**
 * Term names and slugs for the index.
 *
 * @param string $taxonomy Taxonomy.
 * @return array<int,array{slug:string,name:string,count:int}>
 */
function orwo_index_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => 300,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return array_map(
		static function ( $term ) {
			return array(
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => (int) $term->count,
			);
		},
		$terms
	);
}

/**
 * The budget bands the guided finder offers, editable in the chatbot settings.
 *
 * @return array<int,array{label:string,min:float,max:float}>
 */
function orwo_budget_bands() {
	$raw = (string) get_option( 'orwo_budget_bands', "0-15\n15-30\n30-60\n60-120\n120-" );
	$out = array();

	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '-', $line ) );
		$min   = isset( $parts[0] ) && is_numeric( $parts[0] ) ? (float) $parts[0] : 0.0;
		$max   = isset( $parts[1] ) && is_numeric( $parts[1] ) ? (float) $parts[1] : 0.0;

		if ( $max > 0 ) {
			$label = function_exists( 'wc_price' )
				? wp_strip_all_tags( wc_price( $min ) ) . ' – ' . wp_strip_all_tags( wc_price( $max ) )
				: $min . ' – ' . $max;
		} else {
			$label = function_exists( 'wc_price' )
				/* translators: %s: price. */
				? sprintf( __( '%s and over', 'orwo-core' ), wp_strip_all_tags( wc_price( $min ) ) )
				: $min . '+';
		}

		$out[] = array(
			'label' => $label,
			'min'   => $min,
			'max'   => $max,
		);
	}

	return $out;
}

/**
 * Everyday words mapped to shop terms, so "record" finds vinyl and "movie" finds discs.
 *
 * @return array<string,string[]>
 */
function orwo_chat_synonyms() {
	$synonyms = array(
		'record'   => array( 'vinyl' ),
		'records'  => array( 'vinyl' ),
		'lp'       => array( 'vinyl' ),
		'lps'      => array( 'vinyl' ),
		'album'    => array( 'vinyl', 'cds' ),
		'bluray'   => array( 'blu-ray' ),
		'blueray'  => array( 'blu-ray' ),
		'blu'      => array( 'blu-ray' ),
		'4k'       => array( 'blu-ray' ),
		'uhd'      => array( 'blu-ray' ),
		'movie'    => array( 'dvds', 'blu-ray' ),
		'movies'   => array( 'dvds', 'blu-ray' ),
		'film'     => array( 'dvds', 'blu-ray' ),
		'films'    => array( 'dvds', 'blu-ray' ),
		'dvd'      => array( 'dvds' ),
		'cd'       => array( 'cds' ),
		'print'    => array( 'photos', 'posters' ),
		'prints'   => array( 'photos', 'posters' ),
		'photo'    => array( 'photos' ),
		'picture'  => array( 'photos', 'posters' ),
		'pictures' => array( 'photos', 'posters' ),
		'poster'   => array( 'posters' ),
		'wall'     => array( 'posters', 'photos' ),
		'gift'     => array(),
	);

	/**
	 * Filters the chatbot synonym list.
	 *
	 * @param array<string,string[]> $synonyms Everyday word to format slugs.
	 */
	return apply_filters( 'orwo_chat_synonyms', $synonyms );
}

/**
 * Records a question the bot could not match.
 *
 * @param string $question Question text.
 */
function orwo_log_unanswered( $question ) {
	$log  = get_option( 'orwo_unanswered', array() );
	$key  = md5( mb_strtolower( $question ) );
	$now  = time();

	if ( isset( $log[ $key ] ) ) {
		$log[ $key ]['count']++;
		$log[ $key ]['last'] = $now;
	} else {
		$log[ $key ] = array(
			'question' => $question,
			'count'    => 1,
			'first'    => $now,
			'last'     => $now,
		);
	}

	if ( count( $log ) > 1000 ) {
		// Drop the least asked, oldest entries first.
		uasort(
			$log,
			static function ( $a, $b ) {
				return array( $b['count'], $b['last'] ) <=> array( $a['count'], $a['last'] );
			}
		);
		$log = array_slice( $log, 0, 1000, true );
	}

	update_option( 'orwo_unanswered', $log, false );
}
