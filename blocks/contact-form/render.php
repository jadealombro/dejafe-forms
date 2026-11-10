<?php
/**
 * Contact form block markup.
 *
 * @package dejafe-forms
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$dejafe_uid      = wp_unique_id( 'dejafe-form-' );
$dejafe_post_id  = (int) get_the_ID();
$dejafe_site_key = dejafe_forms_turnstile_site_key();

if ( $dejafe_site_key ) {
	wp_enqueue_script(
		'cloudflare-turnstile',
		'https://challenges.cloudflare.com/turnstile/v0/api.js',
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

$dejafe_fields = dejafe_forms_form_fields( $block->parsed_block['innerBlocks'] ?? array(), $attributes );
?>
<form <?php echo get_block_wrapper_attributes( array( 'class' => 'dejafe-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> method="post" action="<?php echo esc_url( rest_url( 'dejafe-forms/v1/submit' ) ); ?>" data-dejafe-form novalidate>
	<input type="hidden" name="post_id" value="<?php echo esc_attr( $dejafe_post_id ); ?>">
	<input type="hidden" name="form_id" value="<?php echo esc_attr( $attributes['formId'] ); ?>">
	<div class="dejafe-form__fields">
		<?php
		foreach ( $dejafe_fields as $dejafe_f ) {
			echo dejafe_forms_render_field( $dejafe_f, $dejafe_uid ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		<div class="dejafe-form__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $dejafe_uid ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'dejafe-forms' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $dejafe_uid ); ?>-website" name="website" tabindex="-1" autocomplete="off">
		</div>
		<?php if ( $dejafe_site_key ) : ?>
			<div class="cf-turnstile dejafe-form__turnstile" data-sitekey="<?php echo esc_attr( $dejafe_site_key ); ?>" data-appearance="interaction-only" data-theme="auto" data-size="flexible"></div>
		<?php endif; ?>
		<p class="dejafe-form__status" role="alert" hidden></p>
		<div class="dejafe-form__actions">
			<button type="submit" class="dejafe-form__submit wp-element-button"><?php echo esc_html( $attributes['submitLabel'] ); ?></button>
		</div>
		<noscript><p class="dejafe-form__status"><?php esc_html_e( 'Please enable JavaScript to send this form.', 'dejafe-forms' ); ?></p></noscript>
	</div>
	<div class="dejafe-form__success" tabindex="-1" hidden></div>
</form>
