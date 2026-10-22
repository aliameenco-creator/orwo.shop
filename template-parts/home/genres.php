<?php
/**
 * Browse by genre: large-type text links to the genre pages, no images.
 *
 * @package orwo
 */

$orwo_genres = orwo_top_terms( 'orwo_genre', '', 14 );
if ( ! $orwo_genres ) {
	return;
}
?>
<section class="orwo-wrap orwo-genres">
	<h2 class="orwo-section__title"><?php esc_html_e( 'Browse by genre', 'orwo' ); ?></h2>
	<ul class="orwo-genres__list">
		<?php foreach ( $orwo_genres as $orwo_term ) : ?>
			<li><a href="<?php echo esc_url( (string) get_term_link( $orwo_term ) ); ?>"><?php echo esc_html( $orwo_term->name ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</section>
