<?php
/**
 * CSV import and export, one template per format.
 *
 * The columns of a template are exactly that format's fields, so the first catalogue load and later price or stock
 * updates need no mapping screen. Rows are matched on SKU, so re-running an import updates rather than duplicates.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The CSV columns for a format.
 *
 * @param string $format Format slug.
 * @return string[]
 */
function orwo_csv_columns( $format ) {
	$columns = array( 'sku', 'title', 'slug', 'creator', 'genre', 'label', 'year', 'condition', 'price', 'sale_price', 'stock', 'stock_status', 'why_we_like_it', 'chat_keywords', 'image_url', 'gallery_urls', 'status' );

	foreach ( array_keys( Orwo_Formats::fields( $format ) ) as $key ) {
		$columns[] = $key;
	}

	return $columns;
}

/**
 * The import and export screen.
 */
function orwo_render_import_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot view this screen.', 'orwo-core' ) );
	}

	$report = null;
	if ( isset( $_POST['orwo_import'] ) && check_admin_referer( 'orwo_import' ) ) {
		$format  = isset( $_POST['orwo_import_format'] ) ? sanitize_key( wp_unslash( $_POST['orwo_import_format'] ) ) : '';
		$dry_run = ! empty( $_POST['orwo_dry_run'] );
		$report  = orwo_handle_import_upload( $format, $dry_run );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ORWO import and export', 'orwo-core' ); ?></h1>

		<?php if ( is_wp_error( $report ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $report->get_error_message() ); ?></p></div>
		<?php elseif ( is_array( $report ) ) : ?>
			<div class="notice notice-<?php echo empty( $report['errors'] ) ? 'success' : 'warning'; ?>">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: created count, 2: updated count, 3: skipped count. */
							__( '%1$d created, %2$d updated, %3$d skipped.', 'orwo-core' ),
							$report['created'],
							$report['updated'],
							$report['skipped']
						)
					);
					echo $report['dry_run'] ? ' ' . esc_html__( 'Dry run: nothing was saved.', 'orwo-core' ) : '';
					?>
				</p>
				<?php if ( ! empty( $report['errors'] ) ) : ?>
					<ul style="margin-left:1.5em;list-style:disc">
						<?php foreach ( array_slice( $report['errors'], 0, 20 ) as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Import', 'orwo-core' ); ?></h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'orwo_import' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="orwo_import_format"><?php esc_html_e( 'Format', 'orwo-core' ); ?></label></th>
					<td>
						<select name="orwo_import_format" id="orwo_import_format" required>
							<?php foreach ( Orwo_Formats::all() as $slug => $format ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $format['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="orwo_csv"><?php esc_html_e( 'CSV file', 'orwo-core' ); ?></label></th>
					<td><input type="file" name="orwo_csv" id="orwo_csv" accept=".csv,text/csv" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dry run', 'orwo-core' ); ?></th>
					<td><label><input type="checkbox" name="orwo_dry_run" value="1" checked> <?php esc_html_e( 'Check the file and report without saving anything', 'orwo-core' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( __( 'Run the import', 'orwo-core' ), 'primary', 'orwo_import' ); ?>
		</form>

		<h2><?php esc_html_e( 'Templates and exports', 'orwo-core' ); ?></h2>
		<p><?php esc_html_e( 'A template holds the column headings for that format. An export holds the products you already have, ready to edit and import back.', 'orwo-core' ); ?></p>
		<table class="widefat striped" style="max-width:40em">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Format', 'orwo-core' ); ?></th>
					<th><?php esc_html_e( 'Empty template', 'orwo-core' ); ?></th>
					<th><?php esc_html_e( 'Export', 'orwo-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( Orwo_Formats::all() as $slug => $format ) : ?>
					<tr>
						<td><?php echo esc_html( $format['label'] ); ?></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwo_export_csv&format=' . $slug . '&template=1' ), 'orwo_export_csv' ) ); ?>"><?php esc_html_e( 'Download', 'orwo-core' ); ?></a></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwo_export_csv&format=' . $slug ), 'orwo_export_csv' ) ); ?>"><?php esc_html_e( 'Download', 'orwo-core' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Reads the uploaded file and runs the import.
 *
 * @param string $format  Format slug.
 * @param bool   $dry_run Whether to save anything.
 * @return array<string,mixed>|WP_Error
 */
function orwo_handle_import_upload( $format, $dry_run ) {
	if ( ! Orwo_Formats::get( $format ) ) {
		return new WP_Error( 'orwo_bad_format', __( 'Choose a format.', 'orwo-core' ) );
	}
	if ( empty( $_FILES['orwo_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['orwo_csv']['tmp_name'] ) ) {
		return new WP_Error( 'orwo_no_file', __( 'Choose a CSV file to import.', 'orwo-core' ) );
	}

	$check = wp_check_filetype( sanitize_file_name( (string) $_FILES['orwo_csv']['name'] ), array( 'csv' => 'text/csv' ) );
	if ( 'csv' !== $check['ext'] ) {
		return new WP_Error( 'orwo_not_csv', __( 'That file is not a CSV.', 'orwo-core' ) );
	}

	return orwo_import_csv( (string) $_FILES['orwo_csv']['tmp_name'], $format, $dry_run );
}

/**
 * Imports a CSV file for one format.
 *
 * @param string $path    Path to the file.
 * @param string $format  Format slug.
 * @param bool   $dry_run Whether to save anything.
 * @return array<string,mixed>|WP_Error
 */
function orwo_import_csv( $path, $format, $dry_run = true ) {
	$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading an uploaded CSV row by row.
	if ( ! $handle ) {
		return new WP_Error( 'orwo_unreadable', __( 'The file could not be read.', 'orwo-core' ) );
	}

	$heading = fgetcsv( $handle );
	if ( ! $heading ) {
		fclose( $handle );

		return new WP_Error( 'orwo_empty', __( 'The file has no heading row.', 'orwo-core' ) );
	}
	$heading = array_map(
		static function ( $cell ) {
			return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $cell ) ) );
		},
		$heading
	);

	$report = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => array(),
		'dry_run' => (bool) $dry_run,
	);
	$line   = 1;

	while ( false !== ( $cells = fgetcsv( $handle ) ) ) {
		++$line;
		if ( array( null ) === $cells || ! array_filter( $cells, 'strlen' ) ) {
			continue;
		}
		$row = array();
		foreach ( $heading as $index => $column ) {
			$row[ $column ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
		}
		if ( '' === ( $row['title'] ?? '' ) && '' === ( $row['sku'] ?? '' ) ) {
			++$report['skipped'];
			continue;
		}

		$result = orwo_import_row( $row, $format, $dry_run );
		if ( is_wp_error( $result ) ) {
			++$report['skipped'];
			/* translators: 1: CSV line number, 2: error message. */
			$report['errors'][] = sprintf( __( 'Line %1$d: %2$s', 'orwo-core' ), $line, $result->get_error_message() );
			continue;
		}
		++$report[ $result ];
	}

	fclose( $handle );

	if ( ! $dry_run ) {
		orwo_flush_filter_cache();
		orwo_build_chat_index();
	}

	return $report;
}

/**
 * Imports one row, matching on SKU and then on slug.
 *
 * @param array<string,string> $row     Row values keyed by column.
 * @param string               $format  Format slug.
 * @param bool                 $dry_run Whether to save anything.
 * @return string|WP_Error "created", "updated" or an error.
 */
function orwo_import_row( $row, $format, $dry_run ) {
	if ( ! orwo_woocommerce_active() ) {
		return new WP_Error( 'orwo_no_woo', __( 'WooCommerce must be active to import products.', 'orwo-core' ) );
	}

	$sku   = $row['sku'] ?? '';
	$title = $row['title'] ?? '';
	$slug  = $row['slug'] ?? '';

	$existing_id = 0;
	if ( '' !== $sku ) {
		$existing_id = (int) wc_get_product_id_by_sku( $sku );
	}
	if ( ! $existing_id && '' !== $slug ) {
		$found       = orwo_find_product_by_slug( sanitize_title( $slug ) );
		$existing_id = $found ? (int) $found->ID : 0;
	}
	if ( ! $existing_id && '' === $title ) {
		return new WP_Error( 'orwo_no_title', __( 'A new product needs a title.', 'orwo-core' ) );
	}

	if ( $dry_run ) {
		return $existing_id ? 'updated' : 'created';
	}

	$product = $existing_id ? wc_get_product( $existing_id ) : new WC_Product_Simple();
	if ( ! $product ) {
		return new WP_Error( 'orwo_bad_product', __( 'The existing product could not be loaded.', 'orwo-core' ) );
	}

	if ( '' !== $title ) {
		$product->set_name( $title );
	}
	if ( '' !== $slug ) {
		$product->set_slug( sanitize_title( $slug ) );
	}
	if ( '' !== $sku ) {
		$product->set_sku( $sku );
	}
	if ( isset( $row['price'] ) && is_numeric( $row['price'] ) ) {
		$product->set_regular_price( (string) (float) $row['price'] );
	}
	if ( isset( $row['sale_price'] ) && is_numeric( $row['sale_price'] ) ) {
		$product->set_sale_price( (string) (float) $row['sale_price'] );
	} elseif ( isset( $row['sale_price'] ) && '' === $row['sale_price'] ) {
		$product->set_sale_price( '' );
	}
	if ( isset( $row['stock'] ) && '' !== $row['stock'] && is_numeric( $row['stock'] ) ) {
		$product->set_manage_stock( true );
		$product->set_stock_quantity( (int) $row['stock'] );
	}
	if ( ! empty( $row['stock_status'] ) && in_array( $row['stock_status'], array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
		$product->set_stock_status( $row['stock_status'] );
	}
	if ( ! empty( $row['why_we_like_it'] ) ) {
		$product->set_short_description( sanitize_textarea_field( $row['why_we_like_it'] ) );
	}
	$status = ! empty( $row['status'] ) && in_array( $row['status'], array( 'publish', 'draft', 'private' ), true ) ? $row['status'] : 'publish';
	$product->set_status( $status );

	$product_id = $product->save();
	if ( ! $product_id ) {
		return new WP_Error( 'orwo_save_failed', __( 'The product could not be saved.', 'orwo-core' ) );
	}

	wp_set_object_terms( $product_id, array( $format ), 'orwo_format', false );

	foreach ( array(
		'creator' => 'orwo_creator',
		'genre'   => 'orwo_genre',
		'label'   => 'orwo_label',
	) as $column => $taxonomy ) {
		if ( ! isset( $row[ $column ] ) ) {
			continue;
		}
		$names = array_values( array_filter( array_map( 'trim', explode( ',', (string) $row[ $column ] ) ) ) );
		wp_set_object_terms( $product_id, $names, $taxonomy, false );
	}

	if ( isset( $row['condition'] ) ) {
		$condition = '';
		foreach ( Orwo_Formats::CONDITIONS as $option ) {
			if ( 0 === strcasecmp( $option, $row['condition'] ) ) {
				$condition = $option;
			}
		}
		wp_set_object_terms( $product_id, $condition ? array( $condition ) : array(), 'orwo_condition', false );
	}

	if ( isset( $row['year'] ) ) {
		orwo_set_year( $product_id, (int) $row['year'] );
	}
	if ( isset( $row['why_we_like_it'] ) ) {
		update_post_meta( $product_id, Orwo_Formats::meta_key( 'why_we_like_it' ), sanitize_textarea_field( $row['why_we_like_it'] ) );
	}
	if ( isset( $row['chat_keywords'] ) ) {
		update_post_meta( $product_id, Orwo_Formats::meta_key( 'chat_keywords' ), sanitize_text_field( $row['chat_keywords'] ) );
	}

	foreach ( Orwo_Formats::fields( $format ) as $key => $field ) {
		if ( ! array_key_exists( $key, $row ) ) {
			continue;
		}
		$value = $row[ $key ];
		if ( 'bool' === $field['type'] ) {
			$value = in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'y' ), true );
		}
		if ( 'select' === $field['type'] && '' !== $value ) {
			foreach ( $field['options'] as $option ) {
				if ( 0 === strcasecmp( html_entity_decode( $option, ENT_QUOTES, 'UTF-8' ), (string) $value ) ) {
					$value = $option;
					break;
				}
			}
		}
		orwo_save_field( $product_id, $field, $value );
	}

	if ( ! empty( $row['image_url'] ) ) {
		$attachment_id = orwo_sideload_image( $row['image_url'], $product_id );
		if ( $attachment_id ) {
			set_post_thumbnail( $product_id, $attachment_id );
		}
	}
	if ( ! empty( $row['gallery_urls'] ) ) {
		$gallery = array();
		foreach ( array_slice( array_filter( array_map( 'trim', explode( '|', (string) $row['gallery_urls'] ) ) ), 0, 5 ) as $url ) {
			$attachment_id = orwo_sideload_image( $url, $product_id );
			if ( $attachment_id ) {
				$gallery[] = $attachment_id;
			}
		}
		if ( $gallery ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery ) );
		}
	}

	return $existing_id ? 'updated' : 'created';
}

/**
 * Copies an image into the Media Library once, reusing it if the same URL was imported before.
 *
 * @param string $url        Image URL.
 * @param int    $product_id Product the image belongs to.
 * @return int Attachment ID, or 0.
 */
function orwo_sideload_image( $url, $product_id ) {
	$url = esc_url_raw( $url );
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return 0;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact match, import only.
				array(
					'key'   => '_orwo_source_url',
					'value' => $url,
				),
			),
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_sideload_image( $url, $product_id, null, 'id' );
	if ( is_wp_error( $attachment_id ) ) {
		return 0;
	}

	update_post_meta( $attachment_id, '_orwo_source_url', $url );
	// Alt text from the product title, which is what the SEO rules call for.
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', get_the_title( $product_id ) );

	return (int) $attachment_id;
}

/**
 * Streams a template or an export for one format.
 */
function orwo_export_csv() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'orwo_export_csv' ) ) {
		wp_die( esc_html__( 'You cannot export the catalogue.', 'orwo-core' ) );
	}

	$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
	if ( ! Orwo_Formats::get( $format ) ) {
		wp_die( esc_html__( 'Unknown format.', 'orwo-core' ) );
	}
	$template = ! empty( $_GET['template'] );
	$columns  = orwo_csv_columns( $format );

	orwo_send_csv_headers( 'orwo-' . $format . ( $template ? '-template' : '-export' ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, $columns );

	if ( ! $template ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Export of one format.
					array(
						'taxonomy' => 'orwo_format',
						'field'    => 'slug',
						'terms'    => array( $format ),
					),
				),
			)
		);

		foreach ( $products as $post ) {
			fputcsv( $out, orwo_export_row( $post, $format, $columns ) );
		}
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_orwo_export_csv', 'orwo_export_csv' );

/**
 * One export row.
 *
 * @param WP_Post  $post    Product.
 * @param string   $format  Format slug.
 * @param string[] $columns Column order.
 * @return array<int,string>
 */
function orwo_export_row( $post, $format, $columns ) {
	$product = orwo_wc_product( $post->ID );
	$terms   = static function ( $taxonomy ) use ( $post ) {
		$found = get_the_terms( $post->ID, $taxonomy );

		return ( $found && ! is_wp_error( $found ) ) ? implode( ', ', wp_list_pluck( $found, 'name' ) ) : '';
	};

	$values = array(
		'sku'            => $product ? $product->get_sku() : '',
		'title'          => $post->post_title,
		'slug'           => $post->post_name,
		'creator'        => $terms( 'orwo_creator' ),
		'genre'          => $terms( 'orwo_genre' ),
		'label'          => $terms( 'orwo_label' ),
		'year'           => (string) orwo_field( $post->ID, 'year' ),
		'condition'      => orwo_product_condition( $post->ID ),
		'price'          => $product ? (string) $product->get_regular_price() : '',
		'sale_price'     => $product ? (string) $product->get_sale_price() : '',
		'stock'          => $product && $product->managing_stock() ? (string) $product->get_stock_quantity() : '',
		'stock_status'   => $product ? $product->get_stock_status() : '',
		'why_we_like_it' => (string) orwo_field( $post->ID, 'why_we_like_it' ),
		'chat_keywords'  => (string) orwo_field( $post->ID, 'chat_keywords' ),
		'image_url'      => (string) get_the_post_thumbnail_url( $post->ID, 'full' ),
		'gallery_urls'   => '',
		'status'         => $post->post_status,
	);

	if ( $product ) {
		$gallery = array();
		foreach ( $product->get_gallery_image_ids() as $image_id ) {
			$gallery[] = (string) wp_get_attachment_url( $image_id );
		}
		$values['gallery_urls'] = implode( '|', array_filter( $gallery ) );
	}

	foreach ( Orwo_Formats::fields( $format ) as $key => $field ) {
		$value = orwo_field( $post->ID, $key );
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		if ( is_bool( $value ) ) {
			$value = $value ? 'yes' : '';
		}
		$values[ $key ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	$row = array();
	foreach ( $columns as $column ) {
		$row[] = isset( $values[ $column ] ) ? $values[ $column ] : '';
	}

	return $row;
}
