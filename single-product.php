<?php
/**
 * The product page.
 *
 * Our own gallery and layout; the add-to-cart form is WooCommerce's own, so variations, stock and validation stay
 * the proven code rather than something rebuilt here. The spec table is built from the fields, skipping any that
 * are empty.
 *
 * @package orwo
 */

get_header();

the_post();

$orwo_id        = get_the_ID();
$orwo_format    = orwo_core_active() ? orwo_product_format( $orwo_id ) : '';
$orwo_creator   = orwo_core_active() ? orwo_first_term( $orwo_id, 'orwo_creator' ) : null;
$orwo_specs     = orwo_core_active() ? orwo_spec_rows( $orwo_id ) : array();
$orwo_why       = orwo_core_active() ? (string) orwo_field( $orwo_id, 'why_we_like_it' ) : '';
$orwo_badges    = orwo_core_active() ? orwo_product_badges( $orwo_id ) : array();
$orwo_product   = orwo_core_active() ? orwo_wc_product( $orwo_id ) : null;
?>

<article class="orwo-wrap orwo-single">
	<div class="orwo-single__media">
		<?php orwo_product_gallery( $orwo_id ); ?>
	</div>

	<div class="orwo-single__summary">
		<?php if ( $orwo_badges ) : ?>
			<p class="orwo-single__badges">
				<?php foreach ( $orwo_badges as $orwo_badge ) : ?>
					<span class="orwo-badge orwo-badge--<?php echo esc_attr( $orwo_badge['kind'] ); ?>"><?php echo esc_html( $orwo_badge['text'] ); ?></span>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ( $orwo_format ) : ?>
			<p class="orwo-single__format">
				<a href="<?php echo esc_url( user_trailingslashit( home_url( '/' . $orwo_format ) ) ); ?>"><?php echo esc_html( Orwo_Formats::label( $orwo_format ) ); ?></a>
			</p>
		<?php endif; ?>

		<h1 class="orwo-single__title"><?php the_title(); ?></h1>

		<?php if ( $orwo_creator ) : ?>
			<p class="orwo-single__creator">
				<a href="<?php echo esc_url( (string) get_term_link( $orwo_creator ) ); ?>"><?php echo esc_html( $orwo_creator->name ); ?></a>
			</p>
		<?php endif; ?>

		<?php if ( $orwo_product ) : ?>
			<div class="orwo-single__price"><?php echo wp_kses_post( $orwo_product->get_price_html() ); ?></div>

			<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
				<?php wc_print_notices(); ?>
			<?php endif; ?>

			<div class="orwo-single__cart">
				<?php
				/**
				 * WooCommerce's own add-to-cart template: simple products need no JavaScript at all, and a variable
				 * product loads WooCommerce's variation script, which is the one place jQuery still appears.
				 */
				do_action( 'woocommerce_before_add_to_cart_form' );
				woocommerce_template_single_add_to_cart();
				do_action( 'woocommerce_after_add_to_cart_form' );
				?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $orwo_why ) : ?>
			<div class="orwo-single__why">
				<h2><?php esc_html_e( 'Why we like it', 'orwo' ); ?></h2>
				<p><?php echo esc_html( $orwo_why ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $orwo_specs ) : ?>
			<div class="orwo-single__specs">
				<h2><?php esc_html_e( 'Details', 'orwo' ); ?></h2>
				<table>
					<tbody>
						<?php foreach ( $orwo_specs as $orwo_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $orwo_row['label'] ); ?></th>
								<td><?php echo esc_html( html_entity_decode( $orwo_row['value'], ENT_QUOTES, 'UTF-8' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<p class="orwo-single__ask">
			<button type="button" class="orwo-button orwo-button--quiet" data-orwo-chat-open aria-controls="orwo-chat-panel">
				<?php esc_html_e( 'Ask about this item', 'orwo' ); ?>
			</button>
		</p>
	</div>

	<?php if ( get_the_content() ) : ?>
		<div class="orwo-single__description">
			<h2><?php esc_html_e( 'More about this item', 'orwo' ); ?></h2>
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<?php
	$orwo_trailer = orwo_core_active() ? orwo_product_trailer( $orwo_id ) : null;
	if ( $orwo_trailer ) :
		// Click to play: nothing is fetched from the video host until the visitor asks for it, so the page costs
		// no third-party request and the shop sets no outside cookies on arrival.
		$orwo_poster = (string) get_the_post_thumbnail_url( $orwo_id, 'orwo-tile' );
		?>
		<section class="orwo-trailer" id="orwo-trailer">
			<h2><?php esc_html_e( 'Watch the trailer', 'orwo' ); ?></h2>
			<div class="orwo-trailer__frame" data-orwo-trailer data-embed="<?php echo esc_url( $orwo_trailer['embed'] ); ?>"
				data-title="<?php echo esc_attr( sprintf( /* translators: %s: product title. */ __( '%s trailer', 'orwo' ), get_the_title( $orwo_id ) ) ); ?>">
				<button type="button" class="orwo-trailer__play">
					<?php if ( $orwo_poster ) : ?>
						<img src="<?php echo esc_url( $orwo_poster ); ?>" alt="" loading="lazy" decoding="async" class="orwo-trailer__poster">
					<?php endif; ?>
					<span class="orwo-trailer__button" aria-hidden="true">
						<svg width="26" height="26" viewBox="0 0 26 26" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6.5 19 13 9 19.5z"></path></svg>
					</span>
					<span class="orwo-visually-hidden">
						<?php
						/* translators: %s: product title. */
						echo esc_html( sprintf( __( 'Play the %s trailer', 'orwo' ), get_the_title( $orwo_id ) ) );
						?>
					</span>
				</button>
			</div>
			<p class="orwo-trailer__note">
				<a href="<?php echo esc_url( $orwo_trailer['url'] ); ?>" rel="noopener" target="_blank">
					<?php
					echo esc_html(
						'youtube' === $orwo_trailer['provider']
							? __( 'Watch on YouTube', 'orwo' )
							: __( 'Watch on Vimeo', 'orwo' )
					);
					?>
				</a>
				<?php esc_html_e( '— the trailer loads only when you press play.', 'orwo' ); ?>
			</p>
		</section>
	<?php endif; ?>
</article>

<?php
// Similar means the same format with a shared genre or creator, which is also what the chatbot means by it.
$orwo_similar_filters = array();
$orwo_genre_term      = orwo_core_active() ? orwo_first_term( $orwo_id, 'orwo_genre' ) : null;
if ( $orwo_genre_term ) {
	$orwo_similar_filters['genre'] = array( $orwo_genre_term->slug );
}

$orwo_similar = orwo_core_active() ? orwo_products(
	array(
		'format'  => $orwo_format,
		'limit'   => 5,
		'filters' => $orwo_similar_filters,
	)
) : null;

if ( $orwo_similar && $orwo_similar->have_posts() ) :
	$orwo_cards = 0;
	?>
	<section class="orwo-wrap orwo-row">
		<div class="orwo-row__head">
			<h2><?php esc_html_e( 'You might also like', 'orwo' ); ?></h2>
			<?php if ( $orwo_format ) : ?>
				<a href="<?php echo esc_url( user_trailingslashit( home_url( '/' . $orwo_format ) ) ); ?>"><?php esc_html_e( 'View all', 'orwo' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="orwo-grid orwo-grid--row">
			<?php
			foreach ( $orwo_similar->posts as $orwo_post ) {
				if ( (int) $orwo_post->ID === (int) $orwo_id || $orwo_cards >= 4 ) {
					continue;
				}
				++$orwo_cards;
				echo orwo_product_card( (int) $orwo_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the card template.
			}
			?>
		</div>
	</section>
	<?php
endif;

get_footer();
