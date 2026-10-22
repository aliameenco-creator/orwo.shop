<?php
/**
 * Page not found: offer the search and the six formats rather than a dead end.
 *
 * @package orwo
 */

get_header();
?>
<div class="orwo-wrap orwo-page orwo-404">
	<h1 class="orwo-page__title"><?php esc_html_e( 'We could not find that page', 'orwo' ); ?></h1>
	<p><?php esc_html_e( 'It may have moved, or the item may have sold. Try a search, or browse a format.', 'orwo' ); ?></p>
	<?php orwo_search_form(); ?>
	<ul class="orwo-404__formats">
		<?php foreach ( orwo_format_links() as $orwo_format ) : ?>
			<li><a class="orwo-chip" href="<?php echo esc_url( $orwo_format['url'] ); ?>"><?php echo esc_html( $orwo_format['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
get_footer();
