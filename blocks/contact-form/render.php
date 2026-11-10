<?php
/**
 * Contact form block markup.
 *
 * @package dejafe-forms
 *
 * @var array $attributes Block attributes.
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

$dejafe_fields = array(
	'first_name' => array( __( 'First Name', 'dejafe-forms' ), 'text', 'given-name', true ),
	'last_name'  => array( __( 'Last Name', 'dejafe-forms' ), 'text', 'family-name', false ),
	'email'      => array( __( 'Email', 'dejafe-forms' ), 'email', 'email', true ),
	'phone'      => array( __( 'Phone Number', 'dejafe-forms' ), 'tel', 'tel', false ),
	'message'    => array( __( 'Message', 'dejafe-forms' ), 'textarea', 'off', true ),
);

$dejafe_field = function ( $name ) use ( $dejafe_fields, $dejafe_uid ) {
	list( $label, $type, $autocomplete, $required ) = $dejafe_fields[ $name ];
	$id   = $dejafe_uid . '-' . $name;
	$attr = sprintf(
		'id="%1$s" name="%2$s" class="dejafe-form__input" autocomplete="%3$s"%4$s aria-describedby="%1$s-error"',
		esc_attr( $id ),
		esc_attr( $name ),
		esc_attr( $autocomplete ),
		$required ? ' required aria-required="true"' : ''
	);
	$html  = '<div class="dejafe-form__field" data-field="' . esc_attr( $name ) . '">';
	$html .= '<div class="dejafe-form__label-row">';
	$html .= '<label class="dejafe-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? '' : ' <span class="dejafe-form__optional">' . esc_html__( '(optional)', 'dejafe-forms' ) . '</span>' ) . '</label>';
	$html .= '<span class="dejafe-form__error" id="' . esc_attr( $id ) . '-error" hidden></span>';
	$html .= '</div>';
	if ( 'textarea' === $type ) {
		$html .= '<textarea ' . $attr . ' rows="5" maxlength="5000"></textarea>';
	} else {
		$html .= '<input type="' . esc_attr( $type ) . '" ' . $attr . ( 'tel' === $type ? '' : ' maxlength="100"' ) . '>';
	}
	$html .= '</div>';
	return $html;
};
?>
<form <?php echo get_block_wrapper_attributes( array( 'class' => 'dejafe-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> method="post" action="<?php echo esc_url( rest_url( 'dejafe-forms/v1/submit' ) ); ?>" data-dejafe-form novalidate>
	<input type="hidden" name="post_id" value="<?php echo esc_attr( $dejafe_post_id ); ?>">
	<input type="hidden" name="form_id" value="<?php echo esc_attr( $attributes['formId'] ); ?>">
	<div class="dejafe-form__fields">
		<div class="dejafe-form__row">
			<?php echo $dejafe_field( 'first_name' ) . $dejafe_field( 'last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		echo $dejafe_field( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! empty( $attributes['showPhone'] ) ) {
			echo $dejafe_field( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo $dejafe_field( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput
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
