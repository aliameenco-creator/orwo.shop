<?php
/**
 * Homepage options.
 *
 * One screen for the parts of the homepage a person writes: the hero, the editorial band, the trust row, the
 * announcement strip and the five chat chips. Every other row fills itself from the catalogue.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The option defaults. Copy is deliberately plain: it is placeholder wording for the owner to replace, not invented
 * marketing claims.
 *
 * @return array<string,mixed>
 */
function orwo_home_defaults() {
	return array(
		'announcement'      => '',
		'hero_image'        => 0,
		'hero_heading'      => __( 'Photographs, film and music, kept well', 'orwo' ),
		'hero_text'         => __( 'Six formats, chosen by hand and graded honestly.', 'orwo' ),
		'hero_link_text'    => '',
		'hero_link_url'     => '',
		'chat_prompt'       => __( 'Not sure where to start? Tell us what you are after', 'orwo' ),
		'chat_chips'        => "A gift for a film fan\nJazz on vinyl\nA poster for my living room\nBlu-ray, 4K only\nUnder 20 pounds",
		'editorial_image'   => 0,
		'editorial_heading' => __( 'This month: a staff pick', 'orwo' ),
		'editorial_text'    => __( 'Write a short paragraph here about one collection or a single item you want to put forward. Change it whenever you like.', 'orwo' ),
		'editorial_link'    => '',
		'trust'             => "How we grade condition|Every item is graded by hand and described honestly.\nDelivery|Sent tracked from the UK, packed flat and rigid.\nReturns|Fourteen days to change your mind.\nSecure payment|Card and PayPal, handled by the payment provider.",
	);
}

/**
 * One homepage option.
 *
 * @param string $key Option key.
 * @return mixed
 */
function orwo_home_option( $key ) {
	$saved    = (array) get_option( 'orwo_home', array() );
	$defaults = orwo_home_defaults();

	if ( ! array_key_exists( $key, $defaults ) ) {
		return '';
	}

	return array_key_exists( $key, $saved ) && '' !== $saved[ $key ] ? $saved[ $key ] : $defaults[ $key ];
}

/**
 * The chat chips as a list.
 *
 * @return string[]
 */
function orwo_chat_chips() {
	$raw = (string) orwo_home_option( 'chat_chips' );

	return array_slice( array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', $raw ) ) ) ), 0, 5 );
}

/**
 * The trust row as label and text pairs.
 *
 * @return array<int,array{title:string,text:string}>
 */
function orwo_trust_items() {
	$raw   = (string) orwo_home_option( 'trust' );
	$items = array();

	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts   = array_map( 'trim', explode( '|', $line, 2 ) );
		$items[] = array(
			'title' => $parts[0],
			'text'  => $parts[1] ?? '',
		);
	}

	return array_slice( $items, 0, 4 );
}

/**
 * Registers the homepage options screen.
 */
function orwo_home_menu() {
	add_theme_page(
		__( 'Homepage options', 'orwo' ),
		__( 'Homepage options', 'orwo' ),
		'edit_theme_options',
		'orwo-homepage',
		'orwo_render_home_screen'
	);
}
add_action( 'admin_menu', 'orwo_home_menu' );

/**
 * Saves and renders the homepage options.
 */
function orwo_render_home_screen() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You cannot edit the homepage.', 'orwo' ) );
	}

	if ( isset( $_POST['orwo_home_save'] ) && check_admin_referer( 'orwo_home_save' ) ) {
		$clean = array();
		foreach ( orwo_home_defaults() as $key => $default ) {
			$value = wp_unslash( $_POST[ 'orwo_' . $key ] ?? '' );
			if ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( in_array( $key, array( 'chat_chips', 'trust', 'editorial_text', 'hero_text' ), true ) ) {
				$clean[ $key ] = sanitize_textarea_field( $value );
			} elseif ( in_array( $key, array( 'hero_link_url', 'editorial_link' ), true ) ) {
				$url           = trim( (string) $value );
				$clean[ $key ] = 0 === strpos( $url, '/' ) ? '/' . ltrim( sanitize_text_field( $url ), '/' ) : esc_url_raw( $url );
			} else {
				$clean[ $key ] = sanitize_text_field( $value );
			}
		}
		update_option( 'orwo_home', $clean );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The homepage has been updated.', 'orwo' ) . '</p></div>';
	}

	$fields = array(
		'announcement'      => array( 'text', __( 'Announcement strip', 'orwo' ), __( 'One line shown above the header, dismissible. Leave empty to hide it.', 'orwo' ) ),
		'hero_image'        => array( 'image', __( 'Hero image', 'orwo' ), __( 'A wide editorial photograph. It is preloaded, so keep it under about 250 KB.', 'orwo' ) ),
		'hero_heading'      => array( 'text', __( 'Hero heading', 'orwo' ), __( 'The only h1 on the homepage.', 'orwo' ) ),
		'hero_text'         => array( 'textarea', __( 'Hero support line', 'orwo' ), '' ),
		'hero_link_text'    => array( 'text', __( 'Hero button text', 'orwo' ), __( 'Optional. Left empty, no button is shown.', 'orwo' ) ),
		'hero_link_url'     => array( 'text', __( 'Hero button link', 'orwo' ), __( 'A path such as /vinyl/ or a full URL.', 'orwo' ) ),
		'chat_prompt'       => array( 'text', __( 'Chat entry line', 'orwo' ), '' ),
		'chat_chips'        => array( 'textarea', __( 'Chat quick-start chips', 'orwo' ), __( 'One per line, up to five. Clicking one opens Ask ORWO with that request already answered.', 'orwo' ) ),
		'editorial_image'   => array( 'image', __( 'Editorial band image', 'orwo' ), '' ),
		'editorial_heading' => array( 'text', __( 'Editorial band heading', 'orwo' ), '' ),
		'editorial_text'    => array( 'textarea', __( 'Editorial band text', 'orwo' ), '' ),
		'editorial_link'    => array( 'text', __( 'Editorial band link', 'orwo' ), __( 'Optional path or URL for the "Read more" link.', 'orwo' ) ),
		'trust'             => array( 'textarea', __( 'Trust row', 'orwo' ), __( 'Four lines, each as Title|Text.', 'orwo' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Homepage options', 'orwo' ); ?></h1>
		<p><?php esc_html_e( 'Everything else on the homepage fills itself from the catalogue: the format tiles, new arrivals, the limited and back in stock row and the genre links.', 'orwo' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'orwo_home_save' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php list( $type, $label, $hint ) = $field; ?>
					<tr>
						<th scope="row"><label for="orwo_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<?php if ( 'textarea' === $type ) : ?>
								<textarea class="large-text" rows="4" id="orwo_<?php echo esc_attr( $key ); ?>" name="orwo_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( (string) orwo_home_option( $key ) ); ?></textarea>
							<?php elseif ( 'image' === $type ) : ?>
								<?php $image_id = (int) orwo_home_option( $key ); ?>
								<input type="number" min="0" step="1" class="small-text" id="orwo_<?php echo esc_attr( $key ); ?>" name="orwo_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>">
								<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open the Media Library', 'orwo' ); ?></a>
								<p class="description"><?php esc_html_e( 'Paste the attachment ID shown in the Media Library URL.', 'orwo' ); ?></p>
								<?php if ( $image_id && wp_attachment_is_image( $image_id ) ) : ?>
									<p><?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:260px;height:auto' ) ); ?></p>
								<?php endif; ?>
							<?php else : ?>
								<input type="text" class="large-text" id="orwo_<?php echo esc_attr( $key ); ?>" name="orwo_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) orwo_home_option( $key ) ); ?>">
							<?php endif; ?>
							<?php if ( $hint ) : ?>
								<p class="description"><?php echo esc_html( $hint ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save the homepage', 'orwo' ), 'primary', 'orwo_home_save' ); ?>
		</form>
	</div>
	<?php
}
