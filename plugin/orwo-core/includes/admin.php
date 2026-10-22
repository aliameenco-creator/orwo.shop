<?php
/**
 * Admin screens: the chatbot menu, its settings, the unanswered questions log and the sign-up export.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Ask ORWO menu.
 */
function orwo_admin_menu() {
	add_menu_page(
		__( 'Ask ORWO', 'orwo-core' ),
		__( 'Ask ORWO', 'orwo-core' ),
		'edit_posts',
		'orwo-chat',
		'orwo_render_unanswered_screen',
		'dashicons-format-chat',
		26
	);

	add_submenu_page(
		'orwo-chat',
		__( 'Unanswered questions', 'orwo-core' ),
		__( 'Unanswered questions', 'orwo-core' ),
		'edit_posts',
		'orwo-chat',
		'orwo_render_unanswered_screen'
	);

	add_submenu_page(
		'orwo-chat',
		__( 'Chatbot settings', 'orwo-core' ),
		__( 'Settings', 'orwo-core' ),
		'manage_options',
		'orwo-chat-settings',
		'orwo_render_settings_screen'
	);

	add_submenu_page(
		'edit.php?post_type=product',
		__( 'ORWO import and export', 'orwo-core' ),
		__( 'Import and export', 'orwo-core' ),
		'manage_options',
		'orwo-import',
		'orwo_render_import_screen'
	);
}
add_action( 'admin_menu', 'orwo_admin_menu' );

/**
 * The unanswered questions screen, most frequent first.
 */
function orwo_render_unanswered_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You cannot view this screen.', 'orwo-core' ) );
	}

	if ( isset( $_POST['orwo_clear_unanswered'] ) && check_admin_referer( 'orwo_clear_unanswered' ) ) {
		update_option( 'orwo_unanswered', array(), false );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'The log has been cleared.', 'orwo-core' ) . '</p></div>';
	}

	$log = get_option( 'orwo_unanswered', array() );
	uasort(
		$log,
		static function ( $a, $b ) {
			return array( (int) $b['count'], (int) $b['last'] ) <=> array( (int) $a['count'], (int) $a['last'] );
		}
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Unanswered questions', 'orwo-core' ); ?></h1>
		<p><?php esc_html_e( 'What visitors asked Ask ORWO that it could not match. Text only, no personal data. Add a chat answer where visitors keep asking.', 'orwo-core' ); ?></p>
		<?php if ( empty( $log ) ) : ?>
			<p><strong><?php esc_html_e( 'Nothing here yet.', 'orwo-core' ); ?></strong></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Question', 'orwo-core' ); ?></th>
						<th><?php esc_html_e( 'Times asked', 'orwo-core' ); ?></th>
						<th><?php esc_html_e( 'Last asked', 'orwo-core' ); ?></th>
						<th><?php esc_html_e( 'Action', 'orwo-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['question'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $entry['count'] ) ); ?></td>
							<td><?php echo esc_html( date_i18n( (string) get_option( 'date_format' ), (int) $entry['last'] ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=orwo_answer&orwo_trigger=' . rawurlencode( $entry['question'] ) ) ); ?>">
									<?php esc_html_e( 'Write an answer', 'orwo-core' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" style="margin-top:1em">
				<?php wp_nonce_field( 'orwo_clear_unanswered' ); ?>
				<button class="button" name="orwo_clear_unanswered" value="1"><?php esc_html_e( 'Clear the log', 'orwo-core' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Pre-fills a new chat answer's trigger phrase from the unanswered log.
 *
 * @param string  $content Default content.
 * @param WP_Post $post    Post being created.
 * @return string
 */
function orwo_prefill_answer_trigger( $content, $post ) {
	if ( 'orwo_answer' === $post->post_type && ! empty( $_GET['orwo_trigger'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill.
		$trigger = sanitize_text_field( wp_unslash( $_GET['orwo_trigger'] ) );
		update_post_meta( $post->ID, '_orwo_triggers', $trigger );
	}

	return $content;
}
add_filter( 'default_content', 'orwo_prefill_answer_trigger', 10, 2 );

/**
 * Registers the chatbot settings.
 */
function orwo_register_settings() {
	$text = array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	);

	register_setting( 'orwo_chat', 'orwo_enquiry_email', array_merge( $text, array( 'sanitize_callback' => 'sanitize_email' ) ) );
	register_setting( 'orwo_chat', 'orwo_chat_greeting', array_merge( $text, array( 'default' => __( 'Hello. Tell me what you are after and I will find it.', 'orwo-core' ) ) ) );
	register_setting(
		'orwo_chat',
		'orwo_budget_bands',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => "0-15\n15-30\n30-60\n60-120\n120-",
		)
	);
}
add_action( 'admin_init', 'orwo_register_settings' );

/**
 * The chatbot settings screen.
 */
function orwo_render_settings_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot view this screen.', 'orwo-core' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Chatbot settings', 'orwo-core' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'orwo_chat' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="orwo_enquiry_email"><?php esc_html_e( 'Send enquiries to', 'orwo-core' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="orwo_enquiry_email" name="orwo_enquiry_email" value="<?php echo esc_attr( (string) get_option( 'orwo_enquiry_email', '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Left empty, enquiries go to the site administrator address. Every enquiry is also saved under Ask ORWO, so nothing is lost if email fails.', 'orwo-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="orwo_chat_greeting"><?php esc_html_e( 'Greeting', 'orwo-core' ); ?></label></th>
					<td><input type="text" class="large-text" id="orwo_chat_greeting" name="orwo_chat_greeting" value="<?php echo esc_attr( (string) get_option( 'orwo_chat_greeting', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="orwo_budget_bands"><?php esc_html_e( 'Budget bands', 'orwo-core' ); ?></label></th>
					<td>
						<textarea id="orwo_budget_bands" name="orwo_budget_bands" rows="5" class="regular-text"><?php echo esc_textarea( (string) get_option( 'orwo_budget_bands', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One band per line as min-max, for example 15-30. Leave the maximum off the last line for "and over".', 'orwo-core' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Newsletter sign-ups', 'orwo-core' ); ?></h2>
		<p>
			<?php
			$count = (int) wp_count_posts( 'orwo_signup' )->publish;
			echo esc_html( sprintf( /* translators: %s: number of sign-ups. */ _n( '%s address on the list.', '%s addresses on the list.', $count, 'orwo-core' ), number_format_i18n( $count ) ) );
			?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwo_export_signups' ), 'orwo_export_signups' ) ); ?>">
				<?php esc_html_e( 'Export the list as CSV', 'orwo-core' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Streams the newsletter list as CSV.
 */
function orwo_export_signups() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'orwo_export_signups' ) ) {
		wp_die( esc_html__( 'You cannot export the list.', 'orwo-core' ) );
	}

	$rows = get_posts(
		array(
			'post_type'      => 'orwo_signup',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);

	orwo_send_csv_headers( 'orwo-newsletter-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'email', 'signed_up' ) );
	foreach ( $rows as $row ) {
		fputcsv( $out, array( $row->post_title, $row->post_date_gmt ) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_orwo_export_signups', 'orwo_export_signups' );

/**
 * Sends the headers for a CSV download.
 *
 * @param string $filename File name.
 */
function orwo_send_csv_headers( $filename ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
}

/**
 * A shop manager role that can run the catalogue and orders but not settings or code.
 */
function orwo_add_shop_manager_notice() {
	if ( orwo_woocommerce_active() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( (string) $screen->id, 'orwo' ) && 'plugins' !== $screen->id && 'dashboard' !== $screen->id ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'ORWO Core: WooCommerce is not active.', 'orwo-core' ); ?></strong>
			<?php esc_html_e( 'The catalogue, filters and chatbot need WooCommerce as the order engine. Prices, stock, cart and checkout stay switched off until it is activated.', 'orwo-core' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'orwo_add_shop_manager_notice' );
