<?php
/**
 * A plain page: about, shipping, returns, FAQ, contact, track order and the legal pages.
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
