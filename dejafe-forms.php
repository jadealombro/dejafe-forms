<?php
/**
 * Plugin Name:       Dejafe Forms
 * Description:       Lightweight contact form block with custom fields, Cloudflare Turnstile spam protection, email notifications and confirmations, SMTP delivery (e.g. Mailgun) and an entries backup with automatic deletion.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Dejafe
 * Author URI:        https://dejafe.dev/
 * Update URI:        https://github.com/jadealombro/dejafe-forms
 * License:           GPL-2.0-or-later
 * Text Domain:       dejafe-forms
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

define( 'DEJAFE_FORMS_VERSION', '1.1.0' );
define( 'DEJAFE_FORMS_FILE', __FILE__ );
define( 'DEJAFE_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEJAFE_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once DEJAFE_FORMS_DIR . 'includes/settings.php';
require_once DEJAFE_FORMS_DIR . 'includes/fields.php';
require_once DEJAFE_FORMS_DIR . 'includes/entries.php';
require_once DEJAFE_FORMS_DIR . 'includes/mailer.php';
require_once DEJAFE_FORMS_DIR . 'includes/turnstile.php';
require_once DEJAFE_FORMS_DIR . 'includes/submit.php';
require_once DEJAFE_FORMS_DIR . 'includes/updater.php';

add_action(
	'init',
	function () {
		register_block_type( DEJAFE_FORMS_DIR . 'blocks/contact-form' );
		register_block_type( DEJAFE_FORMS_DIR . 'blocks/field' );
	}
);

register_activation_hook(
	__FILE__,
	function () {
		if ( ! wp_next_scheduled( 'dejafe_forms_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dejafe_forms_cleanup' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'dejafe_forms_cleanup' );
	}
);
