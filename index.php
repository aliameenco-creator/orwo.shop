<?php
/**
 * The fallback template. Posts are not part of this shop, but a blog or an archive must still render.
 *
 * @package orwo
 */

get_header();
?>
<div class="orwo-wrap orwo-page">
	<?php if ( have_posts() ) : ?>
		<h1 class="orwo-page__title">
			<?php
			if ( is_search() ) {
				/* translators: %s: search term. */
				echo esc_html( sprintf( __( 'Search: %s', 'orwo' ), get_search_query() ) );
			} elseif ( is_archive() ) {
				echo esc_html( wp_strip_all_tags( (string) get_the_archive_title() ) );
			} else {
				echo esc_html( (string) get_bloginfo( 'name' ) );
			}
			?>
		</h1>
		<ul class="orwo-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<li>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28, '…' ) ); ?></p>
				</li>
			<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
	<?php else : ?>
		<h1 class="orwo-page__title"><?php esc_html_e( 'Nothing found', 'orwo' ); ?></h1>
		<p><?php esc_html_e( 'Try a search, or browse the six formats from the menu.', 'orwo' ); ?></p>
		<?php orwo_search_form(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
