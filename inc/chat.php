<?php
/**
 * Ask ORWO: the panel markup and the script that costs nothing until it is used.
 *
 * The script loads when the browser is idle or on the first click, and the product index is only downloaded when the
 * panel opens. Matching runs in the visitor's browser over the shop's own catalogue: no outside requests, no API key.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the chat script without enqueuing it: the loader below decides when it runs.
 */
function orwo_register_chat_script() {
	wp_register_script( 'orwo-chat', get_theme_file_uri( 'assets/chat.js' ), array(), ORWO_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'orwo_register_chat_script' );

/**
 * The data the widget needs: where the index is, what to say first, and the product in view.
 *
 * @return array<string,mixed>
 */
function orwo_chat_config() {
	$product = array();

	if ( function_exists( 'is_product' ) && is_product() && orwo_core_active() ) {
		$id      = get_queried_object_id();
		$format  = orwo_product_format( $id );
		$product = array(
			'id'        => $id,
			'title'     => get_the_title( $id ),
			'format'    => $format,
			'formatLabel' => $format ? Orwo_Formats::label( $format ) : '',
			'condition' => orwo_product_condition( $id ),
			'inStock'   => ! orwo_is_sold_out( $id ),
			'price'     => wp_strip_all_tags( orwo_price_html( $id ) ),
			'specs'     => orwo_spec_rows( $id ),
			'creator'   => orwo_creator_name( $id ),
		);
	}

	return array(
		'index'    => orwo_core_active() ? orwo_chat_index_url() : '',
		'rest'     => esc_url_raw( rest_url( 'orwo/v1/' ) ),
		'nonce'    => wp_create_nonce( 'orwo_enquiry' ),
		'greeting' => (string) get_option( 'orwo_chat_greeting', __( 'Hello. Tell me what you are after and I will find it.', 'orwo' ) ),
		'page'     => esc_url_raw( orwo_current_url() ),
		'product'  => $product,
		'shop'     => orwo_shop_url(),
		'strings'  => array(
			'title'        => __( 'Ask ORWO', 'orwo' ),
			'close'        => __( 'Close', 'orwo' ),
			'placeholder'  => __( 'Type your question…', 'orwo' ),
			'send'         => __( 'Send', 'orwo' ),
			'choose'       => __( 'Help me choose', 'orwo' ),
			'browse'       => __( 'Browse a format', 'orwo' ),
			'delivery'     => __( 'Delivery and returns', 'orwo' ),
			'track'        => __( 'Track my order', 'orwo' ),
			'ask'          => __( 'Ask a question', 'orwo' ),
			'seeAll'       => __( 'See all', 'orwo' ),
			'didYouMean'   => __( 'Did you mean…', 'orwo' ),
			'noMatch'      => __( 'I could not find that. Here are the six formats, or send us a question and a person will reply.', 'orwo' ),
			'anyFormat'    => __( 'Any format', 'orwo' ),
			'noPreference' => __( 'No preference', 'orwo' ),
			'budget'       => __( 'What is your budget?', 'orwo' ),
			'genreAsk'     => __( 'Any particular genre?', 'orwo' ),
			'results'      => __( 'Here is what fits:', 'orwo' ),
			'nothingFits'  => __( 'Nothing in stock fits all of that. Here is the closest I have.', 'orwo' ),
			'name'         => __( 'Your name', 'orwo' ),
			'email'        => __( 'Your email address', 'orwo' ),
			'question'     => __( 'Your question', 'orwo' ),
			'sending'      => __( 'Sending…', 'orwo' ),
			'thanks'       => __( 'Thank you. We have your question and will reply by email.', 'orwo' ),
			'failed'       => __( 'That did not send. Please email us instead.', 'orwo' ),
			'inStock'      => __( 'In stock', 'orwo' ),
			'soldOut'      => __( 'Sold out', 'orwo' ),
			'specsTitle'   => __( 'What condition is it?', 'orwo' ),
			'similar'      => __( 'Show similar', 'orwo' ),
			'willItPlay'   => __( 'Will it play on my player?', 'orwo' ),
			'isInStock'    => __( 'Is it in stock?', 'orwo' ),
			'trackText'    => __( 'You can check an order with its number and the email address you used.', 'orwo' ),
			'trackUrl'     => user_trailingslashit( home_url( '/track-order' ) ),
		),
	);
}

/**
 * The URL of the current request, used to tell us which page a question came from.
 *
 * @return string
 */
function orwo_current_url() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

	return home_url( $path );
}

/**
 * The panel, its launcher and the loader that fetches the script when the browser is idle.
 */
function orwo_render_chat() {
	if ( ! orwo_core_active() ) {
		return;
	}
	$config = orwo_chat_config();
	$src    = (string) wp_scripts()->registered['orwo-chat']->src;
	$src    = add_query_arg( 'ver', ORWO_VERSION, $src );
	?>
	<button type="button" class="orwo-chat-launch" data-orwo-chat-open aria-expanded="false" aria-controls="orwo-chat-panel">
		<svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
			<path d="M3 5.5h14v8.5H9l-4 3v-3H3z"></path>
		</svg>
		<span><?php esc_html_e( 'Ask ORWO', 'orwo' ); ?></span>
	</button>

	<aside id="orwo-chat-panel" class="orwo-chat" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Ask ORWO', 'orwo' ); ?>" hidden>
		<header class="orwo-chat__head">
			<h2><?php esc_html_e( 'Ask ORWO', 'orwo' ); ?></h2>
			<button type="button" class="orwo-chat__close" data-orwo-chat-close>
				<span class="orwo-visually-hidden"><?php esc_html_e( 'Close', 'orwo' ); ?></span>
				<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 3 10 10M13 3 3 13"></path></svg>
			</button>
		</header>
		<div class="orwo-chat__log" data-orwo-chat-log role="log" aria-live="polite" aria-atomic="false"></div>
		<form class="orwo-chat__form" data-orwo-chat-form>
			<label class="orwo-visually-hidden" for="orwo-chat-input"><?php esc_html_e( 'Your question', 'orwo' ); ?></label>
			<input type="text" id="orwo-chat-input" autocomplete="off" placeholder="<?php esc_attr_e( 'Type your question…', 'orwo' ); ?>">
			<button type="submit" class="orwo-button"><?php esc_html_e( 'Send', 'orwo' ); ?></button>
		</form>
	</aside>

	<script id="orwo-chat-loader">
		window.orwoChat = <?php echo wp_json_encode( $config ); ?>;
		(function () {
			var src = <?php echo wp_json_encode( $src ); ?>;
			var loaded = false;
			function load() {
				if ( loaded ) { return; }
				loaded = true;
				var script = document.createElement( 'script' );
				script.src = src;
				script.defer = true;
				document.head.appendChild( script );
			}
			document.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-orwo-chat-open],[data-orwo-chip],[data-orwo-chat-start]' ) ) { load(); }
			}, true );
			if ( 'requestIdleCallback' in window ) {
				requestIdleCallback( load, { timeout: 6000 } );
			} else {
				window.addEventListener( 'load', function () { setTimeout( load, 3000 ); } );
			}
		}());
	</script>
	<?php
}
add_action( 'wp_footer', 'orwo_render_chat', 20 );
