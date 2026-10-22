<?php
/**
 * The single source of truth for the catalogue.
 *
 * Every format, its fields, which of those fields filter, which appear in the spec table and which the chatbot's
 * guided finder asks about are declared once here. The filter panel, REST endpoint, product screen, spec table,
 * CSV templates and chatbot all read this file, so there is never a second set of rules to maintain.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format and field registry.
 */
class Orwo_Formats {

	/**
	 * Condition terms, best first. Media and sleeve grades only apply when the condition is not New.
	 */
	const CONDITIONS = array( 'New', 'Like new', 'Very good', 'Good', 'Fair' );

	/**
	 * Record and sleeve grading scale, best first.
	 */
	const GRADES = array( 'Mint', 'Near mint', 'Very good plus', 'Very good', 'Good', 'Fair' );

	/**
	 * Cached definition array.
	 *
	 * @var array<string,array>|null
	 */
	protected static $formats = null;

	/**
	 * All formats, in the fixed display order used by the format bar and the homepage tiles.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		if ( null !== self::$formats ) {
			return self::$formats;
		}

		$grades    = self::GRADES;
		$collector = 'Collector&#8217;s edition';

		$formats = array(
			'photos'  => array(
				'label'    => __( 'Photos', 'orwo-core' ),
				'singular' => __( 'Photo', 'orwo-core' ),
				'fields'   => array(
					'print_size'     => array(
						'label'   => __( 'Print size', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( '8x10 in', '11x14 in', '12x16 in', '16x20 in', '20x24 in', '24x30 in' ),
						'filter'  => true,
						'finder'  => true,
					),
					'finish'         => array(
						'label'   => __( 'Finish', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Matte', 'Lustre', 'Gloss' ),
						'filter'  => true,
					),
					'edition'        => array(
						'label'   => __( 'Edition', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Open edition', 'Limited edition' ),
						'filter'  => true,
					),
					'edition_number' => array(
						'label' => __( 'Edition number', 'orwo-core' ),
						'type'  => 'text',
						'hint'  => __( 'For example 12 of 50.', 'orwo-core' ),
					),
					'signed'         => array(
						'label'  => __( 'Signed', 'orwo-core' ),
						'type'   => 'bool',
						'filter' => true,
					),
					'framed'         => array(
						'label'  => __( 'Framed', 'orwo-core' ),
						'type'   => 'bool',
						'filter' => true,
					),
					'orientation'    => array(
						'label'   => __( 'Orientation', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Portrait', 'Landscape', 'Square' ),
						'filter'  => true,
					),
				),
			),
			'dvds'    => array(
				'label'    => __( 'DVDs', 'orwo-core' ),
				'singular' => __( 'DVD', 'orwo-core' ),
				'fields'   => array(
					'region'    => array(
						'label'   => __( 'Region', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Region free', 'Region 1', 'Region 2', 'Region 3', 'Region 4', 'Region 5', 'Region 6' ),
						'filter'  => true,
						'finder'  => true,
					),
					'runtime'   => array(
						'label'  => __( 'Runtime', 'orwo-core' ),
						'type'   => 'number',
						'suffix' => __( 'minutes', 'orwo-core' ),
					),
					'subtitles' => array(
						'label'  => __( 'Subtitle languages', 'orwo-core' ),
						'type'   => 'list',
						'filter' => true,
					),
					'audio'     => array(
						'label' => __( 'Audio languages', 'orwo-core' ),
						'type'  => 'list',
					),
					'edition'   => array(
						'label'   => __( 'Edition', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Standard', 'Special edition', $collector ),
						'filter'  => true,
					),
					'discs'     => array(
						'label' => __( 'Disc count', 'orwo-core' ),
						'type'  => 'number',
					),
					'trailer'   => array(
						'label' => __( 'Trailer', 'orwo-core' ),
						'type'  => 'text',
						'spec'  => false,
						'hint'  => __( 'A YouTube or Vimeo link, or just the video ID. Shown under the product.', 'orwo-core' ),
					),
				),
			),
			'blu-ray' => array(
				'label'    => __( 'Blu-ray', 'orwo-core' ),
				'singular' => __( 'Blu-ray', 'orwo-core' ),
				'fields'   => array(
					'region'     => array(
						'label'   => __( 'Region', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Region free', 'Region A', 'Region B', 'Region C' ),
						'filter'  => true,
						'finder'  => true,
					),
					'resolution' => array(
						'label'   => __( 'Resolution', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'HD', '4K UHD' ),
						'filter'  => true,
						'finder'  => true,
					),
					'runtime'    => array(
						'label'  => __( 'Runtime', 'orwo-core' ),
						'type'   => 'number',
						'suffix' => __( 'minutes', 'orwo-core' ),
					),
					'subtitles'  => array(
						'label'  => __( 'Subtitle languages', 'orwo-core' ),
						'type'   => 'list',
						'filter' => true,
					),
					'audio'      => array(
						'label' => __( 'Audio languages', 'orwo-core' ),
						'type'  => 'list',
					),
					'edition'    => array(
						'label'   => __( 'Edition', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Standard', 'Special edition', $collector, 'Steelbook' ),
						'filter'  => true,
					),
					'discs'      => array(
						'label' => __( 'Disc count', 'orwo-core' ),
						'type'  => 'number',
					),
					'trailer'    => array(
						'label' => __( 'Trailer', 'orwo-core' ),
						'type'  => 'text',
						'spec'  => false,
						'hint'  => __( 'A YouTube or Vimeo link, or just the video ID. Shown under the product.', 'orwo-core' ),
					),
				),
			),
			'posters' => array(
				'label'    => __( 'Posters', 'orwo-core' ),
				'singular' => __( 'Poster', 'orwo-core' ),
				'fields'   => array(
					'size'        => array(
						'label'   => __( 'Size', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'A3', 'A2', 'A1', '18x24 in', '24x36 in' ),
						'filter'  => true,
						'finder'  => true,
					),
					'orientation' => array(
						'label'   => __( 'Orientation', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Portrait', 'Landscape' ),
						'filter'  => true,
					),
					'finish'      => array(
						'label'   => __( 'Paper finish', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Matte', 'Satin', 'Gloss' ),
						'filter'  => true,
					),
					'edition'     => array(
						'label'   => __( 'Edition', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Original', 'Reproduction' ),
						'filter'  => true,
						'finder'  => true,
					),
					'framed'      => array(
						'label'  => __( 'Framed', 'orwo-core' ),
						'type'   => 'bool',
						'filter' => true,
					),
				),
			),
			'vinyl'   => array(
				'label'    => __( 'Vinyl', 'orwo-core' ),
				'singular' => __( 'Vinyl record', 'orwo-core' ),
				'fields'   => array(
					'disc_size'    => array(
						'label'   => __( 'Disc size', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( '12 in', '10 in', '7 in' ),
						'filter'  => true,
					),
					'speed'        => array(
						'label'   => __( 'Speed', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( '33 rpm', '45 rpm' ),
						'filter'  => true,
					),
					'colour'       => array(
						'label'  => __( 'Colour', 'orwo-core' ),
						'type'   => 'text',
						'filter' => true,
					),
					'pressing'     => array(
						'label'   => __( 'Pressing', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Original', 'Reissue', 'Limited' ),
						'filter'  => true,
						'finder'  => true,
					),
					'media_grade'  => array(
						'label'      => __( 'Media grade', 'orwo-core' ),
						'type'       => 'select',
						'options'    => $grades,
						'filter'     => true,
						'used_items' => true,
					),
					'sleeve_grade' => array(
						'label'      => __( 'Sleeve grade', 'orwo-core' ),
						'type'       => 'select',
						'options'    => $grades,
						'used_items' => true,
					),
					'discs'        => array(
						'label' => __( 'Disc count', 'orwo-core' ),
						'type'  => 'number',
					),
				),
			),
			'cds'     => array(
				'label'    => __( 'CDs', 'orwo-core' ),
				'singular' => __( 'CD', 'orwo-core' ),
				'fields'   => array(
					'edition' => array(
						'label'   => __( 'Edition', 'orwo-core' ),
						'type'    => 'select',
						'options' => array( 'Standard', 'Deluxe', 'Remaster' ),
						'filter'  => true,
						'finder'  => true,
					),
					'discs'   => array(
						'label' => __( 'Disc count', 'orwo-core' ),
						'type'  => 'number',
					),
					'tracks'  => array(
						'label' => __( 'Track count', 'orwo-core' ),
						'type'  => 'number',
					),
				),
			),
		);

		foreach ( $formats as $slug => $format ) {
			$formats[ $slug ]['slug'] = $slug;
			foreach ( $format['fields'] as $key => $field ) {
				$formats[ $slug ]['fields'][ $key ] = array_merge(
					array(
						'key'        => $key,
						'type'       => 'text',
						'options'    => array(),
						'filter'     => false,
						'finder'     => false,
						'spec'       => true,
						'used_items' => false,
						'hint'       => '',
						'suffix'     => '',
					),
					$field
				);
			}
		}

		/**
		 * Filters the format and field registry.
		 *
		 * @param array<string,array> $formats Format definitions keyed by slug.
		 */
		self::$formats = apply_filters( 'orwo_formats', $formats );

		return self::$formats;
	}

	/**
	 * Format slugs in display order.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_keys( self::all() );
	}

	/**
	 * One format definition.
	 *
	 * @param string $slug Format slug.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$formats = self::all();

		return isset( $formats[ $slug ] ) ? $formats[ $slug ] : null;
	}

	/**
	 * Human label for a format slug.
	 *
	 * @param string $slug Format slug.
	 * @return string
	 */
	public static function label( $slug ) {
		$format = self::get( $slug );

		return $format ? $format['label'] : '';
	}

	/**
	 * Fields of one format, optionally limited to a role such as filter, finder or spec.
	 *
	 * @param string $slug Format slug.
	 * @param string $role Optional. One of filter, finder, spec.
	 * @return array<string,array>
	 */
	public static function fields( $slug, $role = '' ) {
		$format = self::get( $slug );
		if ( ! $format ) {
			return array();
		}
		if ( '' === $role ) {
			return $format['fields'];
		}

		return array_filter(
			$format['fields'],
			static function ( $field ) use ( $role ) {
				return ! empty( $field[ $role ] );
			}
		);
	}

	/**
	 * Every field key used by any format, with the formats that use it.
	 *
	 * A shared key such as "edition" carries different options per format, so the options are merged for the
	 * all-formats view at /shop/.
	 *
	 * @return array<string,array>
	 */
	public static function all_fields() {
		$all = array();
		foreach ( self::all() as $slug => $format ) {
			foreach ( $format['fields'] as $key => $field ) {
				if ( ! isset( $all[ $key ] ) ) {
					$all[ $key ] = array(
						'field'   => $field,
						'formats' => array(),
					);
				}
				$all[ $key ]['formats'][]        = $slug;
				$all[ $key ]['field']['options'] = array_values( array_unique( array_merge( $all[ $key ]['field']['options'], $field['options'] ) ) );
				$all[ $key ]['field']['filter']  = $all[ $key ]['field']['filter'] || $field['filter'];
			}
		}

		return $all;
	}

	/**
	 * Meta key used to store a field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public static function meta_key( $key ) {
		return '_orwo_' . $key;
	}

	/**
	 * Decade label for a year, for example 1960s.
	 *
	 * @param int $year Four digit year.
	 * @return string
	 */
	public static function decade( $year ) {
		$year = (int) $year;
		if ( $year < 1000 || $year > 2999 ) {
			return '';
		}

		return (string) ( (int) floor( $year / 10 ) * 10 ) . 's';
	}
}
