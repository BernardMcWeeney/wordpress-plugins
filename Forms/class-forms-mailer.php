<?php
/**
 * Forms mail delivery.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends form submission emails.
 */
class Mailer {
	/**
	 * Sends a form submission.
	 *
	 * @param array $form Form definition.
	 * @param array $submission Sanitized submission values.
	 * @param array $attachments Uploaded attachment records.
	 * @return true|\WP_Error
	 */
	public function send_submission( $form, $submission, $attachments = array() ) {
		$recipient = sanitize_email( $form['recipient_email'] );
		if ( ! is_email( $recipient ) ) {
			return new \WP_Error( 'invalid_recipient', __( 'The form recipient is not configured correctly.', 'greenberry' ) );
		}

		$subject          = $this->replace_variables( $form['subject'], $form, $submission );
		$attachment_paths = $this->get_attachment_paths( $attachments );
		$headers          = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply_to         = $this->get_submission_value( $submission, $form['reply_to_field'] );

		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
		}

		$sent = wp_mail(
			$recipient,
			$subject,
			$this->render_submission_email( $form, $submission, $attachments, false ),
			$headers,
			$attachment_paths
		);

		if ( ! $sent ) {
			return new \WP_Error( 'mail_failed', __( 'The form could not be emailed. Please try again later.', 'greenberry' ) );
		}

		$this->send_submitter_copy( $form, $submission );

		return true;
	}

	/**
	 * Sends an optional confirmation copy to the submitter.
	 *
	 * @param array $form Form definition.
	 * @param array $submission Sanitized submission values.
	 * @return void
	 */
	private function send_submitter_copy( $form, $submission ) {
		if ( empty( $form['copy_to_field'] ) ) {
			return;
		}

		$email = $this->get_submission_value( $submission, $form['copy_to_field'] );
		if ( ! is_email( $email ) ) {
			return;
		}

		$subject = $this->replace_variables( $form['copy_subject'], $form, $submission );
		$message = wpautop( esc_html( $this->replace_variables( $form['copy_message'], $form, $submission ) ) );
		$content = $message . $this->render_submission_table( $submission, array(), true );

		wp_mail(
			sanitize_email( $email ),
			$subject,
			$this->render_email_shell( $form, $subject, $content ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Renders the main submission email.
	 *
	 * @param array $form Form definition.
	 * @param array $submission Sanitized submission values.
	 * @param array $attachments Uploaded attachment records.
	 * @param bool  $is_copy Whether this is a submitter copy.
	 * @return string
	 */
	private function render_submission_email( $form, $submission, $attachments, $is_copy ) {
		$title   = $is_copy ? $form['copy_subject'] : $form['subject'];
		$title   = $this->replace_variables( $title, $form, $submission );
		$content = $this->render_submission_table( $submission, $attachments, $is_copy );

		return $this->render_email_shell( $form, $title, $content );
	}

	/**
	 * Wraps email body content in a branded shell.
	 *
	 * @param array  $form Form definition.
	 * @param string $title Email title.
	 * @param string $content Email content.
	 * @return string
	 */
	private function render_email_shell( $form, $title, $content ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$logo      = $this->get_site_logo_url();

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title ); ?></title>
		</head>
		<body style="background:#f6f7f7;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:0;">
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7f7;border-collapse:collapse;margin:0;padding:0;width:100%;">
				<tr>
					<td align="center" style="padding:32px 16px;">
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;border-collapse:collapse;border-top:4px solid #2271b1;max-width:680px;width:100%;">
							<tr>
								<td style="padding:24px 28px 10px;">
									<a href="<?php echo esc_url( $site_url ); ?>" style="color:#1d2327;text-decoration:none;">
										<?php if ( $logo ) : ?>
											<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="display:block;height:auto;margin:0 0 14px;max-height:72px;max-width:220px;width:auto;">
										<?php else : ?>
											<strong style="display:block;font-size:18px;"><?php echo esc_html( $site_name ); ?></strong>
										<?php endif; ?>
									</a>
									<h1 style="font-size:24px;line-height:1.25;margin:12px 0 0;"><?php echo esc_html( $title ); ?></h1>
									<p style="color:#646970;font-size:14px;margin:8px 0 0;"><?php echo esc_html( $form['title'] ); ?></p>
								</td>
							</tr>
							<tr>
								<td style="padding:18px 28px 28px;">
									<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	 * Renders submission values as a table.
	 *
	 * @param array $submission Sanitized submission values.
	 * @param array $attachments Uploaded attachment records.
	 * @param bool  $is_copy Whether this is a submitter copy.
	 * @return string
	 */
	private function render_submission_table( $submission, $attachments, $is_copy ) {
		ob_start();
		?>
		<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;">
			<?php foreach ( $submission as $field ) : ?>
				<tr>
					<th align="left" style="border-top:1px solid #dcdcde;color:#50575e;font-size:13px;padding:12px 12px 12px 0;vertical-align:top;width:34%;">
						<?php echo esc_html( $field['label'] ); ?>
					</th>
					<td style="border-top:1px solid #dcdcde;font-size:15px;line-height:1.5;padding:12px 0;vertical-align:top;">
						<?php echo nl2br( esc_html( $field['display'] ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>

			<?php if ( ! $is_copy && ! empty( $attachments ) ) : ?>
				<tr>
					<th align="left" style="border-top:1px solid #dcdcde;color:#50575e;font-size:13px;padding:12px 12px 12px 0;vertical-align:top;width:34%;">
						<?php esc_html_e( 'Attachments', 'greenberry' ); ?>
					</th>
					<td style="border-top:1px solid #dcdcde;font-size:15px;line-height:1.5;padding:12px 0;vertical-align:top;">
						<?php foreach ( $attachments as $attachment ) : ?>
							<div><?php echo esc_html( $attachment['name'] ); ?></div>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Replaces variables in email strings.
	 *
	 * @param string $value Template string.
	 * @param array  $form Form definition.
	 * @param array  $submission Sanitized submission values.
	 * @return string
	 */
	private function replace_variables( $value, $form, $submission ) {
		$replacements = array(
			'{site_name}'  => get_bloginfo( 'name' ),
			'{site_url}'   => home_url( '/' ),
			'{form_title}' => $form['title'],
		);

		foreach ( $submission as $field ) {
			$replacements[ '{' . $field['key'] . '}' ] = $field['display'];
		}

		return strtr( (string) $value, $replacements );
	}

	/**
	 * Gets a submission value by key.
	 *
	 * @param array  $submission Sanitized submission values.
	 * @param string $key Field key.
	 * @return string
	 */
	private function get_submission_value( $submission, $key ) {
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			return '';
		}

		foreach ( $submission as $field ) {
			if ( $key === $field['key'] ) {
				return $field['value'];
			}
		}

		return '';
	}

	/**
	 * Gets attachment file paths.
	 *
	 * @param array $attachments Uploaded attachment records.
	 * @return array<int,string>
	 */
	private function get_attachment_paths( $attachments ) {
		$paths = array();
		foreach ( $attachments as $attachment ) {
			if ( ! empty( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
				$paths[] = $attachment['path'];
			}
		}

		return $paths;
	}

	/**
	 * Gets the site's custom logo URL.
	 *
	 * @return string
	 */
	private function get_site_logo_url() {
		$logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( ! $logo_id ) {
			return '';
		}

		$logo = wp_get_attachment_image_url( $logo_id, 'full' );

		return $logo ? $logo : '';
	}
}
