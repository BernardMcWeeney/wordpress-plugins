<?php
/**
 * Newsletter email template.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Builds branded HTML email shells and unsubscribe URLs.
 */
class Email_Template {
	/**
	 * Renders a complete HTML email.
	 *
	 * @param string $subject Email subject.
	 * @param string $preheader Preview text.
	 * @param string $content Main HTML content.
	 * @param object $contact Contact row.
	 * @return string
	 */
	public function render( $subject, $preheader, $content, $contact ) {
		$site_name       = get_bloginfo( 'name' );
		$home_url        = home_url( '/' );
		$accent          = $this->get_accent_color();
		$logo_url        = $this->get_logo_url();
		$unsubscribe_url = ! empty( $contact->id ) ? self::unsubscribe_url( $contact ) : '';
		$preheader       = sanitize_text_field( $preheader );

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $subject ); ?></title>
		</head>
		<body style="margin:0;padding:0;background:#f6f7f7;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
			<?php if ( '' !== $preheader ) : ?>
				<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
					<?php echo esc_html( $preheader ); ?>
				</div>
			<?php endif; ?>

			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f6f7f7;">
				<tr>
					<td align="center" style="padding:28px 16px;">
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;max-width:680px;background:#ffffff;border:1px solid #e2e4e7;border-radius:8px;overflow:hidden;">
							<tr>
								<td style="padding:24px 28px;border-top:5px solid <?php echo esc_attr( $accent ); ?>;">
									<a href="<?php echo esc_url( $home_url ); ?>" style="color:#1d2327;text-decoration:none;">
										<?php if ( $logo_url ) : ?>
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;max-width:180px;max-height:64px;width:auto;height:auto;">
										<?php else : ?>
											<span style="font-size:22px;font-weight:700;letter-spacing:0;"><?php echo esc_html( $site_name ); ?></span>
										<?php endif; ?>
									</a>
								</td>
							</tr>
							<tr>
								<td style="padding:4px 28px 30px 28px;font-size:16px;line-height:1.6;">
									<?php echo wp_kses_post( $content ); ?>
								</td>
							</tr>
							<tr>
								<td style="padding:20px 28px;background:#f6f7f7;border-top:1px solid #e2e4e7;color:#646970;font-size:13px;line-height:1.5;">
									<p style="margin:0 0 8px 0;">
										<?php
										printf(
											/* translators: %s: site name. */
											esc_html__( 'You are receiving this email because you subscribed to updates from %s.', 'greenberry' ),
											esc_html( $site_name )
										);
										?>
									</p>
									<?php if ( $unsubscribe_url ) : ?>
										<p style="margin:0;">
											<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;">
												<?php esc_html_e( 'Unsubscribe', 'greenberry' ); ?>
											</a>
										</p>
									<?php else : ?>
										<p style="margin:0;"><?php esc_html_e( 'This is a Greenberry test email.', 'greenberry' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Builds an unsubscribe URL for a contact.
	 *
	 * @param object $contact Contact row.
	 * @return string
	 */
	public static function unsubscribe_url( $contact ) {
		return add_query_arg(
			array(
				'greenberry_newsletter_unsubscribe' => '1',
				'contact'                          => absint( $contact->id ),
				'token'                            => rawurlencode( self::unsubscribe_token( $contact ) ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Creates an unsubscribe token.
	 *
	 * @param object $contact Contact row.
	 * @return string
	 */
	public static function unsubscribe_token( $contact ) {
		return wp_hash( absint( $contact->id ) . '|' . strtolower( sanitize_email( $contact->email ) ) . '|greenberry-newsletter-unsubscribe' );
	}

	/**
	 * Verifies an unsubscribe token.
	 *
	 * @param object $contact Contact row.
	 * @param string $token Supplied token.
	 * @return bool
	 */
	public static function verify_unsubscribe_token( $contact, $token ) {
		return hash_equals( self::unsubscribe_token( $contact ), sanitize_text_field( $token ) );
	}

	/**
	 * Gets the site logo URL.
	 *
	 * @return string
	 */
	private function get_logo_url() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( ! $custom_logo_id ) {
			return '';
		}

		$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );

		return $logo_url ? $logo_url : '';
	}

	/**
	 * Gets a theme accent color when possible.
	 *
	 * @return string
	 */
	private function get_accent_color() {
		$accent = get_theme_mod( 'accent_color', '' );

		if ( ! $accent && function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );
			if ( is_array( $palette ) ) {
				foreach ( $palette as $group ) {
					if ( is_array( $group ) ) {
						foreach ( $group as $color ) {
							if ( is_array( $color ) && ! empty( $color['color'] ) ) {
								$accent = $color['color'];
								break 2;
							}
						}
					}
				}
			}
		}

		if ( ! is_string( $accent ) || ! preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $accent ) ) {
			$accent = '#2271b1';
		}

		return $accent;
	}
}
