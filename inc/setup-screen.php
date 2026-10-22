<?php
/**
 * Appearance → ORWO Setup.
 *
 * Creates the editable pages the shop needs (about, shipping, returns, FAQ, contact, track order and the legal
 * pages), the footer menus and the WooCommerce page assignments. Everything it creates is an ordinary WordPress page
 * that can be edited in the block editor afterwards, and re-running it never overwrites an edited page.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages the setup creates, with placeholder copy the owner replaces.
 *
 * @return array<string,array<string,string>>
 */
function orwo_setup_pages() {
	return array(
		'about'       => array(
			'title'   => __( 'About', 'orwo' ),
			'content' => __( 'Write about the shop here: what you collect, how you grade it and why. This page is an ordinary WordPress page, so you can edit it in the block editor.', 'orwo' ),
			'menu'    => 'about',
		),
		'shipping'    => array(
			'title'   => __( 'Shipping', 'orwo' ),
			'content' => __( 'Set out where you ship, what it costs and how long it takes. Until the shipping rates are decided in WooCommerce, keep this page as a draft.', 'orwo' ),
			'menu'    => 'help',
		),
		'returns'     => array(
			'title'   => __( 'Returns', 'orwo' ),
			'content' => __( 'Explain the returns window, the condition items must come back in and who pays the postage.', 'orwo' ),
			'menu'    => 'help',
		),
		'faq'         => array(
			'title'   => __( 'FAQ', 'orwo' ),
			'content' => __( 'Answer the questions customers ask most. Each answer here can also be added under Ask ORWO so the chatbot can give it.', 'orwo' ),
			'menu'    => 'help',
		),
		'contact'     => array(
			'title'   => __( 'Contact', 'orwo' ),
			'content' => '[orwo_contact_form]',
			'menu'    => 'help',
		),
		'track-order' => array(
			'title'   => __( 'Track order', 'orwo' ),
			'content' => '[woocommerce_order_tracking]',
			'menu'    => 'help',
		),
		'privacy-policy' => array(
			'title'   => __( 'Privacy policy', 'orwo' ),
			'content' => __( 'This page needs the shop owner\'s own privacy policy before launch. It is created as a draft on purpose.', 'orwo' ),
			'menu'    => 'legal',
			'status'  => 'draft',
		),
		'terms'       => array(
			'title'   => __( 'Terms and conditions', 'orwo' ),
			'content' => __( 'This page needs the shop owner\'s own terms before launch. It is created as a draft on purpose.', 'orwo' ),
			'menu'    => 'legal',
			'status'  => 'draft',
		),
	);
}

/**
 * Registers the setup screen.
 */
function orwo_setup_menu() {
	add_theme_page(
		__( 'ORWO Setup', 'orwo' ),
		__( 'ORWO Setup', 'orwo' ),
		'edit_theme_options',
		'orwo-setup',
		'orwo_render_setup_screen'
	);
}
add_action( 'admin_menu', 'orwo_setup_menu' );

/**
 * The setup screen.
 */
function orwo_render_setup_screen() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You cannot run the setup.', 'orwo' ) );
	}

	$report = null;
	if ( isset( $_POST['orwo_run_setup'] ) && check_admin_referer( 'orwo_run_setup' ) ) {
		$report = orwo_run_setup();
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ORWO Setup', 'orwo' ); ?></h1>

		<?php if ( is_array( $report ) ) : ?>
			<div class="notice notice-success">
				<p><strong><?php esc_html_e( 'Setup finished.', 'orwo' ); ?></strong></p>
				<ul style="margin-left:1.5em;list-style:disc">
					<?php foreach ( $report as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<p><?php esc_html_e( 'This creates the pages, menus and page assignments the shop needs. Everything it makes is an ordinary WordPress page you can edit afterwards, and running it again never overwrites a page you have edited.', 'orwo' ); ?></p>

		<h2><?php esc_html_e( 'What it does', 'orwo' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Creates About, Shipping, Returns, FAQ, Contact and Track order as published pages, and the privacy policy and terms as drafts for the owner to write.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Builds the three footer menus.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Points WooCommerce at its own cart, checkout and account pages and sets this shop as the front page target.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Seeds the first set of chat answers so Ask ORWO can answer delivery, returns, payment and condition questions on day one.', 'orwo' ); ?></li>
		</ol>

		<form method="post">
			<?php wp_nonce_field( 'orwo_run_setup' ); ?>
			<?php submit_button( __( 'Run the ORWO setup', 'orwo' ), 'primary', 'orwo_run_setup' ); ?>
		</form>

		<h2><?php esc_html_e( 'Still to do by hand', 'orwo' ); ?></h2>
		<ul style="list-style:disc;margin-left:1.5em">
			<li><?php esc_html_e( 'Write the privacy policy and terms, then publish them.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Set the UK shipping zone, rates and tax in WooCommerce.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Connect Stripe and PayPal in test mode first.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Upload the logo under Appearance, and set the hero image under Homepage options.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Set the enquiry email address under Ask ORWO → Settings.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Set a private sign-in address under Settings → ORWO security, and write it down.', 'orwo' ); ?></li>
			<li><?php esc_html_e( 'Switch off WooCommerce&#8217;s &#8220;Coming soon&#8221; mode when the shop is ready to open.', 'orwo' ); ?></li>
		</ul>
	</div>
	<?php
}

/**
 * Creates the pages, menus, page assignments and first chat answers.
 *
 * @return string[] What it did, for the report.
 */
function orwo_run_setup() {
	$report = array();
	$pages  = array();

	foreach ( orwo_setup_pages() as $slug => $spec ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$pages[ $spec['menu'] ][] = $existing->ID;
			$pages['all'][ $slug ]    = $existing->ID;
			/* translators: %s: page title. */
			$report[] = sprintf( __( 'Kept the existing %s page, including any edits.', 'orwo' ), $spec['title'] );
			continue;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $spec['status'] ?? 'publish',
				'post_title'   => $spec['title'],
				'post_name'    => $slug,
				'post_content' => $spec['content'],
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$pages[ $spec['menu'] ][] = $page_id;
			$pages['all'][ $slug ]    = $page_id;
			/* translators: %s: page title. */
			$report[] = sprintf( __( 'Created the %s page.', 'orwo' ), $spec['title'] );
		}
	}

	foreach ( array(
		'help'  => __( 'Footer help', 'orwo' ),
		'about' => __( 'Footer about', 'orwo' ),
		'legal' => __( 'Footer legal', 'orwo' ),
	) as $location => $name ) {
		if ( empty( $pages[ $location ] ) ) {
			continue;
		}
		$menu = wp_get_nav_menu_object( $name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				continue;
			}
			foreach ( $pages[ $location ] as $page_id ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => get_the_title( $page_id ),
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $page_id,
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}
			/* translators: %s: menu name. */
			$report[] = sprintf( __( 'Built the %s menu.', 'orwo' ), $name );
		} else {
			$menu_id = (int) $menu->term_id;
		}

		$locations             = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page ) {
			if ( wc_get_page_id( $page ) > 0 ) {
				continue;
			}
			$report[] = __( 'WooCommerce pages are missing: open WooCommerce → Status → Tools and use "Create default WooCommerce pages".', 'orwo' );
			break;
		}
	}

	$report = array_merge( $report, orwo_seed_chat_answers() );

	if ( function_exists( 'orwo_build_chat_index' ) ) {
		orwo_build_chat_index();
		$report[] = __( 'Rebuilt the Ask ORWO index.', 'orwo' );
	}

	flush_rewrite_rules();

	return $report;
}

/**
 * The first set of chat answers, so the chatbot is useful on day one.
 *
 * The wording is deliberately factual and incomplete where only the owner can decide: a placeholder is better than
 * an invented delivery promise.
 *
 * @return string[]
 */
function orwo_seed_chat_answers() {
	if ( ! post_type_exists( 'orwo_answer' ) ) {
		return array();
	}

	$answers = array(
		array(
			'title'    => __( 'Delivery', 'orwo' ),
			'triggers' => "delivery\nshipping\npostage\nhow long\nwhen will it arrive\ndispatch",
			'content'  => __( 'We ship to the UK, sent tracked. The exact rates and times are on the shipping page — please confirm them there before ordering.', 'orwo' ),
			'button'   => array( __( 'Read the shipping page', 'orwo' ), '/shipping/' ),
		),
		array(
			'title'    => __( 'Returns', 'orwo' ),
			'triggers' => "returns\nrefund\nsend it back\nchange my mind\nexchange",
			'content'  => __( 'You can return an item within the window set out on the returns page. Tell us the order number and we will take it from there.', 'orwo' ),
			'button'   => array( __( 'Read the returns page', 'orwo' ), '/returns/' ),
		),
		array(
			'title'    => __( 'Payment methods', 'orwo' ),
			'triggers' => "payment\npay\ncard\npaypal\nstripe\nhow do i pay",
			'content'  => __( 'Checkout takes cards and PayPal. Payment is handled by the payment provider, so we never see your card details.', 'orwo' ),
			'button'   => array( '', '' ),
		),
		array(
			'title'    => __( 'How we grade condition', 'orwo' ),
			'triggers' => "condition\ngrading\ngrade\nmint\nvery good\nused\nscratches",
			'content'  => __( 'Every item is graded by hand: New, Like new, Very good, Good or Fair. Records also carry a media grade and a sleeve grade, from Mint down to Fair. Where an item has marks that matter, the product page says so.', 'orwo' ),
			'button'   => array( '', '' ),
		),
		array(
			'title'    => __( 'What region codes mean', 'orwo' ),
			'triggers' => "region\nregion code\nwill it play\nregion free\nregion 2\nregion b",
			'content'  => __( 'DVDs are sold by region (Region 2 covers the UK and Europe; Region free plays anywhere). Blu-rays use Region A, B or C, with Region B covering the UK. Every disc page states its region in the spec table.', 'orwo' ),
			'button'   => array( '', '' ),
		),
		array(
			'title'    => __( 'Authenticity', 'orwo' ),
			'triggers' => "authentic\noriginal\nreproduction\nreprint\nis it real\ngenuine",
			'content'  => __( 'Posters and photographs say whether they are an original or a reproduction in the spec table, and limited editions carry their edition number. If anything is unclear on a listing, ask and we will check it.', 'orwo' ),
			'button'   => array( '', '' ),
		),
	);

	$created = 0;
	foreach ( $answers as $answer ) {
		$existing = get_posts(
			array(
				'post_type'      => 'orwo_answer',
				'title'          => $answer['title'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => 'any',
			)
		);
		if ( $existing ) {
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'orwo_answer',
				'post_status'  => 'publish',
				'post_title'   => $answer['title'],
				'post_content' => $answer['content'],
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		update_post_meta( $post_id, '_orwo_triggers', $answer['triggers'] );
		if ( $answer['button'][0] ) {
			update_post_meta( $post_id, '_orwo_button_text', $answer['button'][0] );
			update_post_meta( $post_id, '_orwo_button_url', $answer['button'][1] );
		}
		++$created;
	}

	if ( ! $created ) {
		return array( __( 'Chat answers were already in place and were left alone.', 'orwo' ) );
	}

	/* translators: %d: number of answers. */
	return array( sprintf( _n( 'Added %d chat answer.', 'Added %d chat answers.', $created, 'orwo' ), $created ) );
}

/**
 * The contact form shortcode used by the Contact page: the same fields as the chatbot's Ask a question route.
 *
 * @return string
 */
function orwo_contact_form_shortcode() {
	ob_start();
	?>
	<form class="orwo-contact" data-orwo-contact>
		<p>
			<label for="orwo-contact-name"><?php esc_html_e( 'Your name', 'orwo' ); ?></label>
			<input type="text" id="orwo-contact-name" name="name" required autocomplete="name">
		</p>
		<p>
			<label for="orwo-contact-email"><?php esc_html_e( 'Your email address', 'orwo' ); ?></label>
			<input type="email" id="orwo-contact-email" name="email" required autocomplete="email">
		</p>
		<p>
			<label for="orwo-contact-question"><?php esc_html_e( 'Your question', 'orwo' ); ?></label>
			<textarea id="orwo-contact-question" name="question" rows="5" required></textarea>
		</p>
		<p class="orwo-contact__trap" aria-hidden="true">
			<label for="orwo-contact-website"><?php esc_html_e( 'Leave this field empty', 'orwo' ); ?></label>
			<input type="text" id="orwo-contact-website" name="website" tabindex="-1" autocomplete="off">
		</p>
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'orwo_enquiry' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_url( orwo_current_url() ); ?>">
		<p><button type="submit" class="orwo-button"><?php esc_html_e( 'Send the question', 'orwo' ); ?></button></p>
		<p class="orwo-contact__status" role="status" aria-live="polite"></p>
	</form>
	<?php

	return (string) ob_get_clean();
}
add_shortcode( 'orwo_contact_form', 'orwo_contact_form_shortcode' );
