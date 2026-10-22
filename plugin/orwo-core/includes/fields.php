<?php
/**
 * Per-format product fields, registered natively and shown in one panel on the product screen.
 *
 * Choosing a Format reveals only that format's fields. List fields (subtitle and audio languages) are stored as one
 * meta row per value so that filtering uses an exact indexed match rather than a LIKE scan.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every field as post meta so the REST API and the block editor see it.
 */
function orwo_register_meta() {
	$shared = array(
		'year'              => 'integer',
		'why_we_like_it'    => 'string',
		'chat_keywords'     => 'string',
		'limited_edition'   => 'boolean',
		'back_in_stock_at'  => 'string',
	);

	foreach ( $shared as $key => $type ) {
		register_post_meta(
			'product',
			Orwo_Formats::meta_key( $key ),
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => 'orwo_can_edit_products',
			)
		);
	}

	foreach ( Orwo_Formats::all_fields() as $key => $entry ) {
		$field  = $entry['field'];
		$single = 'list' !== $field['type'];
		register_post_meta(
			'product',
			Orwo_Formats::meta_key( $key ),
			array(
				'type'          => 'number' === $field['type'] ? 'integer' : 'string',
				'single'        => $single,
				'show_in_rest'  => true,
				'auth_callback' => 'orwo_can_edit_products',
			)
		);
	}
}
add_action( 'init', 'orwo_register_meta', 6 );

/**
 * Whether the current user may edit the catalogue.
 *
 * @return bool
 */
function orwo_can_edit_products() {
	return current_user_can( 'edit_products' ) || current_user_can( 'edit_posts' );
}

/**
 * Reads one field from a product.
 *
 * @param int    $product_id Product ID.
 * @param string $key        Field key.
 * @return mixed String, int, bool or array for list fields.
 */
function orwo_field( $product_id, $key ) {
	$meta_key = Orwo_Formats::meta_key( $key );
	$fields   = Orwo_Formats::all_fields();
	$type     = isset( $fields[ $key ] ) ? $fields[ $key ]['field']['type'] : 'text';

	if ( 'list' === $type ) {
		$values = get_post_meta( $product_id, $meta_key, false );

		return array_values( array_filter( array_map( 'strval', (array) $values ) ) );
	}

	$value = get_post_meta( $product_id, $meta_key, true );

	if ( 'bool' === $type ) {
		return '1' === (string) $value;
	}
	if ( 'number' === $type ) {
		return '' === $value ? '' : (int) $value;
	}

	return is_string( $value ) ? $value : '';
}

/**
 * The spec table rows for a product: every non-empty field of its format, plus the shared fields.
 *
 * @param int $product_id Product ID.
 * @return array<int,array{label:string,value:string}>
 */
function orwo_spec_rows( $product_id ) {
	$format = orwo_product_format( $product_id );
	$rows   = array();

	if ( $format ) {
		$rows[] = array(
			'label' => __( 'Format', 'orwo-core' ),
			'value' => Orwo_Formats::label( $format ),
		);
	}

	$creator = orwo_first_term( $product_id, 'orwo_creator' );
	if ( $creator ) {
		$rows[] = array(
			'label' => __( 'Creator', 'orwo-core' ),
			'value' => $creator->name,
		);
	}
	$label_term = orwo_first_term( $product_id, 'orwo_label' );
	if ( $label_term ) {
		$rows[] = array(
			'label' => __( 'Label or studio', 'orwo-core' ),
			'value' => $label_term->name,
		);
	}
	// A film is often two genres at once, so list them all rather than the first one found.
	$genres = get_the_terms( $product_id, 'orwo_genre' );
	if ( $genres && ! is_wp_error( $genres ) ) {
		$rows[] = array(
			'label'  => _n( 'Genre', 'Genres', count( $genres ), 'orwo-core' ),
			'value'  => implode( ', ', wp_list_pluck( $genres, 'name' ) ),
		);
	}
	$year = orwo_field( $product_id, 'year' );
	if ( $year ) {
		$rows[] = array(
			'label' => __( 'Year', 'orwo-core' ),
			'value' => (string) $year,
		);
	}
	$condition = orwo_product_condition( $product_id );
	if ( $condition ) {
		$rows[] = array(
			'label' => __( 'Condition', 'orwo-core' ),
			'value' => $condition,
		);
	}

	$is_new = 'New' === $condition;
	foreach ( Orwo_Formats::fields( $format, 'spec' ) as $key => $field ) {
		if ( $field['used_items'] && $is_new ) {
			// Media and sleeve grades only mean something on a used item.
			continue;
		}
		$value = orwo_field( $product_id, $key );
		if ( 'bool' === $field['type'] ) {
			if ( ! $value ) {
				continue;
			}
			$value = __( 'Yes', 'orwo-core' );
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		if ( '' === (string) $value ) {
			continue;
		}
		if ( $field['suffix'] ) {
			$value .= ' ' . $field['suffix'];
		}
		$rows[] = array(
			'label' => $field['label'],
			'value' => (string) $value,
		);
	}

	return $rows;
}

/**
 * Adds the catalogue panel to the product screen.
 */
function orwo_add_product_panel() {
	add_meta_box(
		'orwo-catalogue',
		__( 'ORWO catalogue details', 'orwo-core' ),
		'orwo_render_product_panel',
		'product',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_product', 'orwo_add_product_panel' );

/**
 * Renders the catalogue panel: the format first, then the shared fields, then one group per format.
 *
 * @param WP_Post $post Product being edited.
 */
function orwo_render_product_panel( $post ) {
	wp_nonce_field( 'orwo_save_product', 'orwo_nonce' );
	$current_format = orwo_product_format( $post->ID );
	?>
	<div class="orwo-panel" data-format="<?php echo esc_attr( $current_format ); ?>">
		<p class="orwo-panel__row">
			<label for="orwo-format"><strong><?php esc_html_e( 'Format', 'orwo-core' ); ?></strong></label><br>
			<select name="orwo_format" id="orwo-format">
				<option value=""><?php esc_html_e( '— Choose a format —', 'orwo-core' ); ?></option>
				<?php foreach ( Orwo_Formats::all() as $slug => $format ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_format, $slug ); ?>><?php echo esc_html( $format['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'The format sets the product URL and which fields appear below.', 'orwo-core' ); ?></span>
		</p>

		<div class="orwo-panel__grid">
			<?php
			foreach ( array(
				'orwo_genre'   => __( 'Genre', 'orwo-core' ),
				'orwo_creator' => __( 'Creator (artist, director, photographer or designer)', 'orwo-core' ),
				'orwo_label'   => __( 'Label or studio', 'orwo-core' ),
			) as $taxonomy => $label ) :
				$terms = get_the_terms( $post->ID, $taxonomy );
				$value = ( $terms && ! is_wp_error( $terms ) ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
				?>
				<p class="orwo-panel__row">
					<label for="<?php echo esc_attr( $taxonomy ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br>
					<input type="text" class="widefat orwo-suggest" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
						id="<?php echo esc_attr( $taxonomy ); ?>" name="<?php echo esc_attr( $taxonomy ); ?>"
						value="<?php echo esc_attr( $value ); ?>" autocomplete="off">
					<span class="description"><?php esc_html_e( 'Separate several with commas. Existing names are suggested as you type.', 'orwo-core' ); ?></span>
				</p>
			<?php endforeach; ?>

			<p class="orwo-panel__row">
				<label for="orwo-year"><strong><?php esc_html_e( 'Year', 'orwo-core' ); ?></strong></label><br>
				<input type="number" id="orwo-year" name="orwo_year" min="1800" max="2100" step="1" value="<?php echo esc_attr( orwo_field( $post->ID, 'year' ) ); ?>">
				<span class="description"><?php esc_html_e( 'Sets the decade filter.', 'orwo-core' ); ?></span>
			</p>

			<p class="orwo-panel__row">
				<label for="orwo-condition"><strong><?php esc_html_e( 'Condition', 'orwo-core' ); ?></strong></label><br>
				<?php $condition = orwo_product_condition( $post->ID ); ?>
				<select name="orwo_condition" id="orwo-condition">
					<option value=""><?php esc_html_e( '— Not stated —', 'orwo-core' ); ?></option>
					<?php foreach ( Orwo_Formats::CONDITIONS as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $condition, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="orwo-panel__row">
				<label><strong><?php esc_html_e( 'Homepage flag', 'orwo-core' ); ?></strong></label><br>
				<label><input type="checkbox" name="orwo_limited_edition" value="1" <?php checked( orwo_field( $post->ID, 'limited_edition' ) ); ?>>
					<?php esc_html_e( 'Show in the Limited and back in stock row', 'orwo-core' ); ?></label>
			</p>
		</div>

		<p class="orwo-panel__row">
			<label for="orwo-why"><strong><?php esc_html_e( 'Why we like it', 'orwo-core' ); ?></strong></label><br>
			<textarea id="orwo-why" name="orwo_why_we_like_it" rows="3" class="widefat"><?php echo esc_textarea( orwo_field( $post->ID, 'why_we_like_it' ) ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One short paragraph. Shown on the product page, used by the chatbot and as the meta description.', 'orwo-core' ); ?></span>
		</p>

		<p class="orwo-panel__row">
			<label for="orwo-keywords"><strong><?php esc_html_e( 'Chat keywords', 'orwo-core' ); ?></strong></label><br>
			<input type="text" id="orwo-keywords" name="orwo_chat_keywords" class="widefat" value="<?php echo esc_attr( orwo_field( $post->ID, 'chat_keywords' ) ); ?>">
			<span class="description"><?php esc_html_e( 'Comma separated, never shown to visitors. Words a customer might use for this item.', 'orwo-core' ); ?></span>
		</p>

		<?php foreach ( Orwo_Formats::all() as $slug => $format ) : ?>
			<fieldset class="orwo-fields" data-for="<?php echo esc_attr( $slug ); ?>" <?php echo $slug === $current_format ? '' : 'hidden'; ?>>
				<legend><strong><?php echo esc_html( sprintf( /* translators: %s: format name. */ __( '%s details', 'orwo-core' ), $format['label'] ) ); ?></strong></legend>
				<div class="orwo-panel__grid">
					<?php foreach ( $format['fields'] as $key => $field ) : ?>
						<?php
						$name  = 'orwo_field[' . $slug . '][' . $key . ']';
						$id    = 'orwo-' . $slug . '-' . $key;
						$value = orwo_field( $post->ID, $key );
						?>
						<p class="orwo-panel__row">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label><br>
							<?php if ( 'select' === $field['type'] ) : ?>
								<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
									<option value="">&mdash;</option>
									<?php foreach ( $field['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>" <?php selected( (string) $value, $option ); ?>><?php echo esc_html( html_entity_decode( $option, ENT_QUOTES, 'UTF-8' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'bool' === $field['type'] ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( (bool) $value ); ?>> <?php esc_html_e( 'Yes', 'orwo-core' ); ?></label>
							<?php elseif ( 'number' === $field['type'] ) : ?>
								<input type="number" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" min="0" step="1" value="<?php echo esc_attr( $value ); ?>">
							<?php elseif ( 'list' === $field['type'] ) : ?>
								<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( implode( ', ', (array) $value ) ); ?>">
							<?php else : ?>
								<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
							<?php if ( $field['hint'] ) : ?>
								<span class="description"><?php echo esc_html( $field['hint'] ); ?></span>
							<?php endif; ?>
							<?php if ( $field['used_items'] ) : ?>
								<span class="description"><?php esc_html_e( 'Only shown when the condition is not New.', 'orwo-core' ); ?></span>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Saves the catalogue panel.
 *
 * @param int     $post_id Product ID.
 * @param WP_Post $post    Product.
 */
function orwo_save_product_panel( $post_id, $post ) {
	if ( 'product' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['orwo_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['orwo_nonce'] ) ), 'orwo_save_product' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$format = isset( $_POST['orwo_format'] ) ? sanitize_key( wp_unslash( $_POST['orwo_format'] ) ) : '';
	$format = Orwo_Formats::get( $format ) ? $format : '';
	wp_set_object_terms( $post_id, $format ? array( $format ) : array(), 'orwo_format', false );

	foreach ( array( 'orwo_genre', 'orwo_creator', 'orwo_label' ) as $taxonomy ) {
		$raw   = isset( $_POST[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_POST[ $taxonomy ] ) ) : '';
		$names = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
		wp_set_object_terms( $post_id, $names, $taxonomy, false );
	}

	$condition = isset( $_POST['orwo_condition'] ) ? sanitize_text_field( wp_unslash( $_POST['orwo_condition'] ) ) : '';
	$condition = in_array( $condition, Orwo_Formats::CONDITIONS, true ) ? $condition : '';
	wp_set_object_terms( $post_id, $condition ? array( $condition ) : array(), 'orwo_condition', false );

	$year = isset( $_POST['orwo_year'] ) ? (int) $_POST['orwo_year'] : 0;
	orwo_set_year( $post_id, $year );

	update_post_meta( $post_id, Orwo_Formats::meta_key( 'why_we_like_it' ), sanitize_textarea_field( wp_unslash( $_POST['orwo_why_we_like_it'] ?? '' ) ) );
	update_post_meta( $post_id, Orwo_Formats::meta_key( 'chat_keywords' ), sanitize_text_field( wp_unslash( $_POST['orwo_chat_keywords'] ?? '' ) ) );
	update_post_meta( $post_id, Orwo_Formats::meta_key( 'limited_edition' ), empty( $_POST['orwo_limited_edition'] ) ? '' : '1' );

	// Only the chosen format's values are kept: switching format must not leave the old format's fields behind.
	$submitted = isset( $_POST['orwo_field'] ) && is_array( $_POST['orwo_field'] ) ? wp_unslash( $_POST['orwo_field'] ) : array();
	$values    = ( $format && isset( $submitted[ $format ] ) && is_array( $submitted[ $format ] ) ) ? $submitted[ $format ] : array();

	foreach ( Orwo_Formats::all_fields() as $key => $entry ) {
		$meta_key = Orwo_Formats::meta_key( $key );
		$field    = $format ? ( Orwo_Formats::fields( $format )[ $key ] ?? null ) : null;
		if ( ! $field ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}
		orwo_save_field( $post_id, $field, $values[ $key ] ?? '' );
	}

	orwo_flush_filter_cache();
	orwo_queue_chat_index_rebuild();
}
add_action( 'save_post', 'orwo_save_product_panel', 20, 2 );

/**
 * Stores one field value with the sanitiser its type calls for.
 *
 * @param int    $post_id Product ID.
 * @param array  $field   Field definition.
 * @param mixed  $raw     Submitted value.
 */
function orwo_save_field( $post_id, $field, $raw ) {
	$meta_key = Orwo_Formats::meta_key( $field['key'] );

	switch ( $field['type'] ) {
		case 'bool':
			if ( $raw ) {
				update_post_meta( $post_id, $meta_key, '1' );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
			break;
		case 'number':
			$number = is_scalar( $raw ) ? trim( (string) $raw ) : '';
			if ( '' === $number ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, (int) $number );
			}
			break;
		case 'select':
			$value = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
			if ( '' === $value || ! in_array( $value, $field['options'], true ) ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
			break;
		case 'list':
			delete_post_meta( $post_id, $meta_key );
			$items = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
			$items = array_values( array_unique( array_filter( array_map( static function ( $item ) {
				return sanitize_text_field( trim( (string) $item ) );
			}, $items ) ) ) );
			foreach ( $items as $item ) {
				add_post_meta( $post_id, $meta_key, $item );
			}
			break;
		default:
			$value = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
	}
}

/**
 * Stores the year and keeps the derived decade term in step.
 *
 * @param int $post_id Product ID.
 * @param int $year    Four digit year, or 0 to clear.
 */
function orwo_set_year( $post_id, $year ) {
	$year   = (int) $year;
	$decade = Orwo_Formats::decade( $year );

	if ( ! $decade ) {
		delete_post_meta( $post_id, Orwo_Formats::meta_key( 'year' ) );
		wp_set_object_terms( $post_id, array(), 'orwo_decade', false );
		return;
	}

	update_post_meta( $post_id, Orwo_Formats::meta_key( 'year' ), $year );
	wp_set_object_terms( $post_id, array( $decade ), 'orwo_decade', false );
}

/**
 * Admin assets for the product screen: the format switch and the term suggestions.
 *
 * @param string $hook Current admin page.
 */
function orwo_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'orwo-admin', ORWO_CORE_URL . 'assets/admin.css', array(), ORWO_CORE_VERSION );
	wp_enqueue_script( 'orwo-admin', ORWO_CORE_URL . 'assets/admin.js', array(), ORWO_CORE_VERSION, true );

	$terms = array();
	foreach ( array( 'orwo_genre', 'orwo_creator', 'orwo_label' ) as $taxonomy ) {
		$found = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
				'fields'     => 'names',
			)
		);
		$terms[ $taxonomy ] = is_wp_error( $found ) ? array() : $found;
	}

	wp_add_inline_script( 'orwo-admin', 'window.orwoAdmin=' . wp_json_encode( array( 'terms' => $terms ) ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'orwo_admin_assets' );
