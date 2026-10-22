<?php
/**
 * A single post, kept simple: this shop's content lives in pages and products.
 *
 * @package orwo
 */

get_header();
the_post();
?>
<article class="orwo-wrap orwo-page">
	<h1 class="orwo-page__title"><?php the_title(); ?></h1>
	<div class="orwo-page__content">
		<?php the_content(); ?>
	</div>
</article>
<?php
get_footer();
