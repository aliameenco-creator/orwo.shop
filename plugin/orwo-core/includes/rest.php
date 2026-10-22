<?php
/**
 * REST endpoints.
 *
 * /wp-json/orwo/v1/products returns the grid HTML and the option counts for the same query the no-JavaScript
 * version would have run, so the panel can swap the grid in place without a reload.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitises a slug parameter.
 *
 * REST sanitise callbacks are handed the value, the request and the key. sanitize_title() reads its second argument
 * as a fallback title, so passing it straight through returns the request object for an empty value. This wrapper
 * takes the value alone.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function orwo_rest_slug( $value ) {
	return sanitize_title( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * Registers the endpoints.
 */
function orwo_register_rest_routes() {
	register_rest_route(
		'orwo/v1',
		'/products',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'orwo_rest_products',
			'permission_callback' => '__return_true',
			'args'                => array(
				'view'    => array(
					'type'              => 'string',
					'default'           => 'shop',
					'sanitize_callback' => 'sanitize_key',
				),
				'format'  => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'orwo_rest_slug',
				),
				'genre'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'orwo_rest_slug',
				),
				'creator' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'orwo_rest_slug',
				),
				'label'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'orwo_rest_slug',
				),
				'paged'   => array(
					'type'    => 'integer',
					'default' => 1,
				),
			),
		)
	);

	register_rest_route(
		'orwo/v1',
		'/enquiry',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'orwo_rest_enquiry',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'orwo/v1',
		'/unanswered',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'orwo_rest_unanswered',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'orwo/v1',
		'/signup',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'orwo_rest_signup',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'orwo_register_rest_routes' );

/**
 * Returns the product grid, the counts and the chips for a filtered view.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function orwo_rest_products( $request ) {
	$view = (string) $request->get_param( 'view' );
	$view = in_array( $view, array( 'shop', 'format', 'genre', 'creator', 'label', 'new', 'sale', 'search' ), true ) ? $view : 'shop';

	$context = array(
		'format'   => (string) $request->get_param( 'format' ),
		'genre'    => (string) $request->get_param( 'genre' ),
		'creator'  => (string) $request->get_param( 'creator' ),
		'label'    => (string) $request->get_param( 'label' ),
		'view'     => $view,
		'paged'    => max( 1, (int) $request->get_param( 'paged' ) ),
		'per_page' => ORWO_PER_PAGE,
	);

	if ( $context['format'] && ! Orwo_Formats::get( $context['format'] ) ) {
		$context['format'] = '';
	}

	$filters = orwo_parse_filters( (array) $request->get_params() );
	$args    = orwo_query_args( $filters, $context );

	$query = new WP_Query();
	add_action( 'pre_get_posts', 'orwo_rest_sort_hook' );
	$query->query( $args );
	remove_action( 'pre_get_posts', 'orwo_rest_sort_hook' );

	$total = (int) $query->found_posts;
	$pages = (int) $query->max_num_pages;

	return new WP_REST_Response(
		array(
			'html'    => orwo_product_grid( $query ),
			'total'   => $total,
			'pages'   => $pages,
			'paged'   => $context['paged'],
			'counts'  => orwo_facet_counts( $filters, $context ),
			'chips'   => orwo_filter_chips( $filters, $context ),
			'summary' => sprintf(
				/* translators: %s: number of products. */
				_n( '%s product', '%s products', $total, 'orwo-core' ),
				number_format_i18n( $total )
			),
		)
	);
}

/**
 * Applies the non-lookup orderings to the REST query.
 *
 * @param WP_Query $query Query.
 */
function orwo_rest_sort_hook( $query ) {
	orwo_apply_sort( $query );
}

/**
 * Stores and emails an "Ask a question" enquiry.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function orwo_rest_enquiry( $request ) {
	$nonce = (string) $request->get_param( 'nonce' );
	if ( ! wp_verify_nonce( $nonce, 'orwo_enquiry' ) ) {
		return new WP_Error( 'orwo_bad_nonce', __( 'Please reload the page and try again.', 'orwo-core' ), array( 'status' => 403 ) );
	}
	// Hidden field: a real visitor never fills it in.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	$limit_key = 'orwo_enq_' . md5( orwo_client_fingerprint() );
	if ( get_transient( $limit_key ) ) {
		return new WP_Error( 'orwo_rate_limited', __( 'Thank you, we already have your message. Please wait a few minutes before sending another.', 'orwo-core' ), array( 'status' => 429 ) );
	}

	$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$email    = sanitize_email( (string) $request->get_param( 'email' ) );
	$question = sanitize_textarea_field( (string) $request->get_param( 'question' ) );
	$page     = esc_url_raw( (string) $request->get_param( 'page' ) );

	if ( '' === $name || ! is_email( $email ) || '' === $question ) {
		return new WP_Error( 'orwo_incomplete', __( 'Please add your name, a valid email address and your question.', 'orwo-core' ), array( 'status' => 400 ) );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'orwo_enquiry',
			'post_status'  => 'publish',
			'post_title'   => sprintf( /* translators: %s: visitor name. */ __( 'Question from %s', 'orwo-core' ), $name ),
			'post_content' => $question,
			'meta_input'   => array(
				'_orwo_name'  => $name,
				'_orwo_email' => $email,
				'_orwo_page'  => $page,
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'orwo_save_failed', __( 'We could not save your question. Please email us instead.', 'orwo-core' ), array( 'status' => 500 ) );
	}

	wp_set_object_terms( $post_id, 'new', 'orwo_enquiry_status', false );
	set_transient( $limit_key, 1, 2 * MINUTE_IN_SECONDS );

	$to      = orwo_enquiry_recipient();
	$subject = sprintf( /* translators: %s: site name. */ __( '[%s] New question', 'orwo-core' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	$body    = implode(
		"\n",
		array(
			__( 'Name:', 'orwo-core' ) . ' ' . $name,
			__( 'Email:', 'orwo-core' ) . ' ' . $email,
			__( 'Page:', 'orwo-core' ) . ' ' . $page,
			'',
			$question,
		)
	);

	if ( $to ) {
		wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	}

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => __( 'Thank you. We have your question and will reply by email.', 'orwo-core' ),
		)
	);
}

/**
 * Logs a question the chatbot could not match. Text only, never personal data.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function orwo_rest_unanswered( $request ) {
	$question = sanitize_text_field( (string) $request->get_param( 'question' ) );
	$question = trim( mb_substr( $question, 0, 200 ) );

	if ( '' === $question || mb_strlen( $question ) < 3 ) {
		return new WP_REST_Response( array( 'ok' => true ) );
	}
	if ( false !== strpos( $question, '@' ) ) {
		// Never keep anything that looks like an email address.
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	$limit_key = 'orwo_unans_' . md5( orwo_client_fingerprint() );
	$recent    = (int) get_transient( $limit_key );
	if ( $recent > 20 ) {
		return new WP_REST_Response( array( 'ok' => true ) );
	}
	set_transient( $limit_key, $recent + 1, HOUR_IN_SECONDS );

	orwo_log_unanswered( $question );

	return new WP_REST_Response( array( 'ok' => true ) );
}

/**
 * Stores a newsletter sign-up.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function orwo_rest_signup( $request ) {
	if ( ! wp_verify_nonce( (string) $request->get_param( 'nonce' ), 'orwo_signup' ) ) {
		return new WP_Error( 'orwo_bad_nonce', __( 'Please reload the page and try again.', 'orwo-core' ), array( 'status' => 403 ) );
	}
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'orwo_bad_email', __( 'Please check the email address.', 'orwo-core' ), array( 'status' => 400 ) );
	}

	$existing = get_posts(
		array(
			'post_type'      => 'orwo_signup',
			'post_status'    => 'publish',
			'title'          => $email,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	if ( ! $existing ) {
		wp_insert_post(
			array(
				'post_type'   => 'orwo_signup',
				'post_status' => 'publish',
				'post_title'  => $email,
			)
		);
	}

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => __( 'Thank you. You are on the list.', 'orwo-core' ),
		)
	);
}

/**
 * A coarse per-visitor key for rate limiting. Hashed, never stored as an address.
 *
 * @return string
 */
function orwo_client_fingerprint() {
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 100 ) : '';

	return wp_hash( $ip . '|' . $agent );
}

/**
 * Where enquiries are emailed. Set in the site's own settings, never in the repository.
 *
 * @return string
 */
function orwo_enquiry_recipient() {
	$configured = (string) get_option( 'orwo_enquiry_email', '' );
	$to          = $configured ? $configured : (string) get_option( 'admin_email', '' );

	return is_email( $to ) ? $to : '';
}
