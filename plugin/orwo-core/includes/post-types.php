<?php
/**
 * The plugin's own records: chat answers, enquiries and newsletter sign-ups.
 *
 * All three are private to the admin: none of them is publicly queryable, and no personal data is ever written to
 * the chatbot index or any export in this repository.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the post types.
 */
function orwo_register_post_types() {
	register_post_type(
		'orwo_answer',
		array(
			'labels'          => array(
				'name'          => __( 'Chat answers', 'orwo-core' ),
				'singular_name' => __( 'Chat answer', 'orwo-core' ),
				'add_new_item'  => __( 'Add a chat answer', 'orwo-core' ),
				'edit_item'     => __( 'Edit chat answer', 'orwo-core' ),
				'menu_name'     => __( 'Chat answers', 'orwo-core' ),
				'not_found'     => __( 'No chat answers yet', 'orwo-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'orwo-chat',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	register_post_type(
		'orwo_enquiry',
		array(
			'labels'          => array(
				'name'          => __( 'Enquiries', 'orwo-core' ),
				'singular_name' => __( 'Enquiry', 'orwo-core' ),
				'menu_name'     => __( 'Enquiries', 'orwo-core' ),
				'not_found'     => __( 'No enquiries yet', 'orwo-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'orwo-chat',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'capabilities'    => array(
				'create_posts' => 'do_not_allow',
			),
		)
	);

	register_post_type(
		'orwo_signup',
		array(
			'labels'          => array(
				'name'          => __( 'Newsletter sign-ups', 'orwo-core' ),
				'singular_name' => __( 'Sign-up', 'orwo-core' ),
				'menu_name'     => __( 'Sign-ups', 'orwo-core' ),
				'not_found'     => __( 'No sign-ups yet', 'orwo-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'orwo-chat',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'capabilities'    => array(
				'create_posts' => 'do_not_allow',
			),
		)
	);

	register_taxonomy(
		'orwo_enquiry_status',
		array( 'orwo_enquiry' ),
		array(
			'labels'       => array(
				'name'          => __( 'Statuses', 'orwo-core' ),
				'singular_name' => __( 'Status', 'orwo-core' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'hierarchical' => true,
			'rewrite'      => false,
		)
	);
}
add_action( 'init', 'orwo_register_post_types', 5 );

/**
 * Seeds the three enquiry statuses.
 */
function orwo_seed_enquiry_statuses() {
	foreach ( array(
		'new'      => __( 'New', 'orwo-core' ),
		'answered' => __( 'Answered', 'orwo-core' ),
		'closed'   => __( 'Closed', 'orwo-core' ),
	) as $slug => $name ) {
		if ( ! term_exists( $slug, 'orwo_enquiry_status' ) ) {
			wp_insert_term( $name, 'orwo_enquiry_status', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Trigger phrases of a chat answer.
 *
 * @param int $post_id Answer ID.
 * @return string[]
 */
function orwo_answer_triggers( $post_id ) {
	$raw = (string) get_post_meta( $post_id, '_orwo_triggers', true );

	return array_values( array_filter( array_map( 'trim', explode( "\n", str_replace( ',', "\n", $raw ) ) ) ) );
}

/**
 * The trigger phrases, button and chip settings on a chat answer.
 *
 * @param WP_Post $post Answer.
 */
function orwo_render_answer_panel( $post ) {
	wp_nonce_field( 'orwo_save_answer', 'orwo_answer_nonce' );
	$triggers    = (string) get_post_meta( $post->ID, '_orwo_triggers', true );
	$button_text = (string) get_post_meta( $post->ID, '_orwo_button_text', true );
	$button_url  = (string) get_post_meta( $post->ID, '_orwo_button_url', true );
	?>
	<p>
		<label for="orwo-triggers"><strong><?php esc_html_e( 'Trigger phrases', 'orwo-core' ); ?></strong></label><br>
		<textarea id="orwo-triggers" name="orwo_triggers" rows="4" class="widefat"><?php echo esc_textarea( $triggers ); ?></textarea>
		<span class="description"><?php esc_html_e( 'One phrase per line, or separated by commas. A visitor question matching any of these gets this answer.', 'orwo-core' ); ?></span>
	</p>
	<p>
		<label for="orwo-button-text"><strong><?php esc_html_e( 'Button text (optional)', 'orwo-core' ); ?></strong></label><br>
		<input type="text" id="orwo-button-text" name="orwo_button_text" class="widefat" value="<?php echo esc_attr( $button_text ); ?>">
	</p>
	<p>
		<label for="orwo-button-url"><strong><?php esc_html_e( 'Button link (optional)', 'orwo-core' ); ?></strong></label><br>
		<input type="text" id="orwo-button-url" name="orwo_button_url" class="widefat" value="<?php echo esc_attr( $button_url ); ?>">
		<span class="description"><?php esc_html_e( 'A path such as /shipping/ or a full URL.', 'orwo-core' ); ?></span>
	</p>
	<?php
}

/**
 * Adds the chat answer panel.
 */
function orwo_add_answer_panel() {
	add_meta_box( 'orwo-answer', __( 'Matching and button', 'orwo-core' ), 'orwo_render_answer_panel', 'orwo_answer', 'normal', 'high' );
}
add_action( 'add_meta_boxes_orwo_answer', 'orwo_add_answer_panel' );

/**
 * Saves the chat answer panel.
 *
 * @param int     $post_id Answer ID.
 * @param WP_Post $post    Answer.
 */
function orwo_save_answer_panel( $post_id, $post ) {
	if ( 'orwo_answer' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['orwo_answer_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['orwo_answer_nonce'] ) ), 'orwo_save_answer' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_orwo_triggers', sanitize_textarea_field( wp_unslash( $_POST['orwo_triggers'] ?? '' ) ) );
	update_post_meta( $post_id, '_orwo_button_text', sanitize_text_field( wp_unslash( $_POST['orwo_button_text'] ?? '' ) ) );

	$url = trim( (string) wp_unslash( $_POST['orwo_button_url'] ?? '' ) );
	$url = 0 === strpos( $url, '/' ) ? '/' . ltrim( sanitize_text_field( $url ), '/' ) : esc_url_raw( $url );
	update_post_meta( $post_id, '_orwo_button_url', $url );

	orwo_queue_chat_index_rebuild();
}
add_action( 'save_post', 'orwo_save_answer_panel', 20, 2 );

/**
 * Enquiry list columns: who asked, their email address and the status.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
function orwo_enquiry_columns( $columns ) {
	return array(
		'cb'        => $columns['cb'] ?? '',
		'title'     => __( 'Enquiry', 'orwo-core' ),
		'orwo_from' => __( 'From', 'orwo-core' ),
		'orwo_page' => __( 'Page', 'orwo-core' ),
		'date'      => __( 'Received', 'orwo-core' ),
	);
}
add_filter( 'manage_orwo_enquiry_posts_columns', 'orwo_enquiry_columns' );

/**
 * Fills the enquiry columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Enquiry ID.
 */
function orwo_enquiry_column( $column, $post_id ) {
	if ( 'orwo_from' === $column ) {
		$email = (string) get_post_meta( $post_id, '_orwo_email', true );
		echo esc_html( (string) get_post_meta( $post_id, '_orwo_name', true ) );
		if ( $email ) {
			echo '<br><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
		}
	}
	if ( 'orwo_page' === $column ) {
		$page = (string) get_post_meta( $post_id, '_orwo_page', true );
		if ( $page ) {
			echo '<a href="' . esc_url( $page ) . '">' . esc_html( wp_parse_url( $page, PHP_URL_PATH ) ?: $page ) . '</a>';
		}
	}
}
add_action( 'manage_orwo_enquiry_posts_custom_column', 'orwo_enquiry_column', 10, 2 );
