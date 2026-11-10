<?php
/**
 * Settings: entry storage and retention, Turnstile keys, sender details, test email.
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings.
 *
 * @return array
 */
function dejafe_forms_defaults() {
	return array(
		'store_entries'        => 1,
		'retention_days'       => 90,
		'turnstile_site_key'   => '',
		'turnstile_secret_key' => '',
		'from_name'            => '',
		'from_email'           => '',
	);
}

/**
 * Read one setting. Constants in wp-config.php win over saved values.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function dejafe_forms_setting( $key ) {
	$constants = array(
		'turnstile_site_key'   => 'DEJAFE_TURNSTILE_SITE_KEY',
		'turnstile_secret_key' => 'DEJAFE_TURNSTILE_SECRET_KEY',
		'from_email'           => 'DEJAFE_MAIL_FROM',
		'from_name'            => 'DEJAFE_MAIL_FROM_NAME',
	);
	if ( isset( $constants[ $key ] ) && defined( $constants[ $key ] ) ) {
		return constant( $constants[ $key ] );
	}
	$settings = wp_parse_args( (array) get_option( 'dejafe_forms_settings', array() ), dejafe_forms_defaults() );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'dejafe_forms',
			'dejafe_forms_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'dejafe_forms_sanitize_settings',
				'default'           => dejafe_forms_defaults(),
			)
		);
	}
);

/**
 * Sanitize settings on save, then apply the retention period right away.
 *
 * @param array $input Raw input.
 * @return array
 */
function dejafe_forms_sanitize_settings( $input ) {
	$input = (array) $input;
	$clean = array(
		'store_entries'        => empty( $input['store_entries'] ) ? 0 : 1,
		'retention_days'       => max( 1, min( 3650, absint( $input['retention_days'] ?? 90 ) ) ),
		'turnstile_site_key'   => sanitize_text_field( $input['turnstile_site_key'] ?? '' ),
		'turnstile_secret_key' => sanitize_text_field( $input['turnstile_secret_key'] ?? '' ),
		'from_name'            => sanitize_text_field( $input['from_name'] ?? '' ),
		'from_email'           => sanitize_email( $input['from_email'] ?? '' ),
	);
	// Keep the stored secret when the field is left blank.
	if ( '' === $clean['turnstile_secret_key'] ) {
		$old                           = (array) get_option( 'dejafe_forms_settings', array() );
		$clean['turnstile_secret_key'] = $old['turnstile_secret_key'] ?? '';
	}
	wp_schedule_single_event( time() + 5, 'dejafe_forms_cleanup' );
	return $clean;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=dejafe_entry',
			__( 'Form settings', 'dejafe-forms' ),
			__( 'Settings', 'dejafe-forms' ),
			'manage_options',
			'dejafe-forms-settings',
			'dejafe_forms_settings_page'
		);
	}
);

/**
 * Settings screen.
 */
function dejafe_forms_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s          = wp_parse_args( (array) get_option( 'dejafe_forms_settings', array() ), dejafe_forms_defaults() );
	$smtp       = defined( 'DEJAFE_SMTP_HOST' ) ? DEJAFE_SMTP_HOST : '';
	$locked     = function ( $constant ) {
		return defined( $constant ) ? ' disabled' : '';
	};
	$test       = isset( $_GET['dejafe_test'] ) ? sanitize_key( $_GET['dejafe_test'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_secret = '' !== (string) dejafe_forms_setting( 'turnstile_secret_key' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Form settings', 'dejafe-forms' ); ?></h1>

		<?php if ( 'sent' === $test ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Test email sent. Check the inbox of the admin email address.', 'dejafe-forms' ); ?></p></div>
		<?php elseif ( 'failed' === $test ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The test email could not be sent. Check the SMTP settings in wp-config.php and the PHP error log.', 'dejafe-forms' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'dejafe_forms' ); ?>

			<h2><?php esc_html_e( 'Saved entries', 'dejafe-forms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Keep a backup', 'dejafe-forms' ); ?></th>
					<td><label><input type="checkbox" name="dejafe_forms_settings[store_entries]" value="1" <?php checked( $s['store_entries'], 1 ); ?>> <?php esc_html_e( 'Save a copy of every submission under Form entries', 'dejafe-forms' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="dejafe-retention"><?php esc_html_e( 'Delete entries after', 'dejafe-forms' ); ?></label></th>
					<td>
						<input id="dejafe-retention" type="number" min="1" max="3650" class="small-text" name="dejafe_forms_settings[retention_days]" value="<?php echo esc_attr( $s['retention_days'] ); ?>"> <?php esc_html_e( 'days', 'dejafe-forms' ); ?>
						<p class="description"><?php esc_html_e( 'Older entries are permanently deleted once a day. Submissions may include personal health information, so keep this as short as is practical.', 'dejafe-forms' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Spam protection (Cloudflare Turnstile)', 'dejafe-forms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dejafe-ts-site"><?php esc_html_e( 'Site key', 'dejafe-forms' ); ?></label></th>
					<td><input id="dejafe-ts-site" type="text" class="regular-text" name="dejafe_forms_settings[turnstile_site_key]" value="<?php echo esc_attr( (string) dejafe_forms_setting( 'turnstile_site_key' ) ); ?>"<?php echo esc_attr( $locked( 'DEJAFE_TURNSTILE_SITE_KEY' ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="dejafe-ts-secret"><?php esc_html_e( 'Secret key', 'dejafe-forms' ); ?></label></th>
					<td>
						<input id="dejafe-ts-secret" type="password" class="regular-text" autocomplete="new-password" name="dejafe_forms_settings[turnstile_secret_key]" value="" placeholder="<?php echo $has_secret ? esc_attr__( 'Saved (leave blank to keep)', 'dejafe-forms' ) : ''; ?>"<?php echo esc_attr( $locked( 'DEJAFE_TURNSTILE_SECRET_KEY' ) ); ?>>
						<p class="description"><?php esc_html_e( 'Create a widget in the Cloudflare dashboard under Turnstile. Leave both empty to turn Turnstile off (the honeypot, timing and rate limit checks still run).', 'dejafe-forms' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Sender', 'dejafe-forms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dejafe-from-name"><?php esc_html_e( 'From name', 'dejafe-forms' ); ?></label></th>
					<td><input id="dejafe-from-name" type="text" class="regular-text" name="dejafe_forms_settings[from_name]" value="<?php echo esc_attr( (string) dejafe_forms_setting( 'from_name' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"<?php echo esc_attr( $locked( 'DEJAFE_MAIL_FROM_NAME' ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="dejafe-from-email"><?php esc_html_e( 'From email', 'dejafe-forms' ); ?></label></th>
					<td>
						<input id="dejafe-from-email" type="email" class="regular-text" name="dejafe_forms_settings[from_email]" value="<?php echo esc_attr( (string) dejafe_forms_setting( 'from_email' ) ); ?>"<?php echo esc_attr( $locked( 'DEJAFE_MAIL_FROM' ) ); ?>>
						<p class="description"><?php esc_html_e( 'Use an address on a domain verified in your mail service (e.g. Mailgun), or emails may be marked as spam.', 'dejafe-forms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Delivery', 'dejafe-forms' ); ?></th>
					<td>
						<?php if ( $smtp ) : ?>
							<?php /* translators: %s: SMTP host. */ printf( esc_html__( 'Sending through SMTP: %s', 'dejafe-forms' ), '<code>' . esc_html( $smtp ) . '</code>' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Using the server\'s default mail. Add the DEJAFE_SMTP_* constants to wp-config.php to send through Mailgun.', 'dejafe-forms' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Test email', 'dejafe-forms' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dejafe_forms_test_email">
			<?php wp_nonce_field( 'dejafe_forms_test_email' ); ?>
			<p>
				<?php /* translators: %s: admin email. */ printf( esc_html__( 'Send a test email to %s.', 'dejafe-forms' ), '<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>' ); ?>
				<?php submit_button( __( 'Send test email', 'dejafe-forms' ), 'secondary', 'submit', false ); ?>
			</p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_dejafe_forms_test_email',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'dejafe-forms' ) );
		}
		check_admin_referer( 'dejafe_forms_test_email' );
		$sent = wp_mail(
			get_option( 'admin_email' ),
			/* translators: %s: site name. */
			sprintf( __( 'Test email from %s', 'dejafe-forms' ), get_bloginfo( 'name' ) ),
			__( 'If you can read this, the website can send email.', 'dejafe-forms' )
		);
		wp_safe_redirect( admin_url( 'edit.php?post_type=dejafe_entry&page=dejafe-forms-settings&dejafe_test=' . ( $sent ? 'sent' : 'failed' ) ) );
		exit;
	}
);
