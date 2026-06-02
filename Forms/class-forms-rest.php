<?php
/**
 * Forms REST and public form handlers.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Handles public form submissions.
 */
class Rest {
	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Mailer.
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 * @param Mailer     $mailer Mailer.
	 */
	public function __construct( Repository $repository, Mailer $mailer ) {
		$this->repository = $repository;
		$this->mailer     = $mailer;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_nopriv_greenberry_forms_submit', array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_greenberry_forms_submit', array( $this, 'handle_form_post' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'greenberry/v1',
			'/forms/submit/(?P<form_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_rest_submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handles REST submissions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_rest_submit( \WP_REST_Request $request ) {
		$form_id = absint( $request['form_id'] );
		$result  = $this->submit_form(
			$form_id,
			$request->get_params(),
			$request->get_file_params()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => $result['message'],
			)
		);
	}

	/**
	 * Handles non-JS form submissions.
	 *
	 * @return void
	 */
	public function handle_form_post() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$result  = $this->submit_form( $form_id, wp_unslash( $_POST ), $_FILES );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		$redirect = remove_query_arg(
			array( 'greenberry_form_sent', 'greenberry_form_error', 'greenberry_form_id' ),
			$redirect
		);

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'greenberry_form_id'    => $form_id,
						'greenberry_form_error' => rawurlencode( $result->get_error_message() ),
					),
					$redirect
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'greenberry_form_id'   => $form_id,
					'greenberry_form_sent' => '1',
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Validates and emails a form submission.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data Request data.
	 * @param array $files Uploaded files.
	 * @return array|\WP_Error
	 */
	private function submit_form( $form_id, $data, $files ) {
		$form = $this->repository->get_form( $form_id );
		if ( ! $form ) {
			return new \WP_Error( 'form_not_found', __( 'This form is not available.', 'greenberry' ), array( 'status' => 404 ) );
		}

		if ( empty( $data['greenberry_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $data['greenberry_form_nonce'] ), 'greenberry_form_submit_' . absint( $form['id'] ) ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'The form expired. Please refresh the page and try again.', 'greenberry' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $data['website'] ) ) {
			return new \WP_Error( 'spam_check_failed', __( 'The form could not be accepted.', 'greenberry' ), array( 'status' => 400 ) );
		}

		if ( $this->is_rate_limited( $form['id'] ) ) {
			return new \WP_Error( 'rate_limited', __( 'Please wait before trying again.', 'greenberry' ), array( 'status' => 429 ) );
		}

		$turnstile = $this->verify_turnstile( $form );
		if ( is_wp_error( $turnstile ) ) {
			return $turnstile;
		}

		$values = isset( $data['greenberry_fields'] ) && is_array( $data['greenberry_fields'] ) ? $data['greenberry_fields'] : array();
		$parsed = $this->parse_submission_fields( $form, $values, $files );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$result = $this->mailer->send_submission(
			$form,
			$parsed['submission'],
			$parsed['attachments']
		);

		$this->delete_temporary_attachments( $parsed['attachments'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->increment_rate_limit( $form['id'] );

		return array(
			'message' => $form['success_message'],
		);
	}

	/**
	 * Parses and validates submitted fields.
	 *
	 * @param array $form Form definition.
	 * @param array $values Submitted scalar values.
	 * @param array $files Uploaded files.
	 * @return array|\WP_Error
	 */
	private function parse_submission_fields( $form, $values, $files ) {
		$submission  = array();
		$attachments = array();

		foreach ( $form['fields'] as $field ) {
			if ( 'file' === $field['type'] ) {
				$file_result = $this->handle_file_field( $field, $files );
				if ( is_wp_error( $file_result ) ) {
					$this->delete_temporary_attachments( $attachments );
					return $file_result;
				}

				if ( ! empty( $file_result ) ) {
					$attachments[] = $file_result;
					$display       = $file_result['name'];
				} else {
					$display = '';
				}

				if ( $field['required'] && '' === $display ) {
					$this->delete_temporary_attachments( $attachments );
					return new \WP_Error(
						'missing_required_file',
						sprintf(
							/* translators: %s: field label. */
							__( 'Please upload a file for %s.', 'greenberry' ),
							$field['label']
						),
						array( 'status' => 400 )
					);
				}

				$submission[] = $this->submission_row( $field, $display, $display );
				continue;
			}

			$value = isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : '';
			$row   = $this->sanitize_field_value( $field, $value );

			if ( is_wp_error( $row ) ) {
				$this->delete_temporary_attachments( $attachments );
				return $row;
			}

			$submission[] = $row;
		}

		return array(
			'submission'  => $submission,
			'attachments' => $attachments,
		);
	}

	/**
	 * Sanitizes a scalar field value.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return array|\WP_Error
	 */
	private function sanitize_field_value( $field, $value ) {
		if ( 'checkbox' === $field['type'] ) {
			$checked = $this->to_bool( $value );
			if ( $field['required'] && ! $checked ) {
				return new \WP_Error(
					'missing_required_checkbox',
					sprintf(
						/* translators: %s: field label. */
						__( 'Please confirm %s.', 'greenberry' ),
						$field['label']
					),
					array( 'status' => 400 )
				);
			}

			return $this->submission_row( $field, $checked ? '1' : '', $checked ? __( 'Yes', 'greenberry' ) : __( 'No', 'greenberry' ) );
		}

		if ( is_array( $value ) ) {
			$value = '';
		}

		$value = trim( (string) $value );
		if ( $field['required'] && '' === $value ) {
			return new \WP_Error(
				'missing_required_field',
				sprintf(
					/* translators: %s: field label. */
					__( 'Please complete %s.', 'greenberry' ),
					$field['label']
				),
				array( 'status' => 400 )
			);
		}

		if ( 'email' === $field['type'] ) {
			$email = sanitize_email( $value );
			if ( '' !== $value && ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'greenberry' ), array( 'status' => 400 ) );
			}

			return $this->submission_row( $field, $email, $email );
		}

		if ( in_array( $field['type'], array( 'textarea', 'address' ), true ) ) {
			$clean = sanitize_textarea_field( $value );
		} else {
			$clean = sanitize_text_field( $value );
		}

		return $this->submission_row( $field, $clean, $clean );
	}

	/**
	 * Handles one uploaded file field.
	 *
	 * @param array $field Field definition.
	 * @param array $files Uploaded files.
	 * @return array|\WP_Error
	 */
	private function handle_file_field( $field, $files ) {
		$file = $this->get_uploaded_file( $files, $field['key'] );
		if ( empty( $file ) || UPLOAD_ERR_NO_FILE === absint( $file['error'] ) ) {
			return array();
		}

		if ( UPLOAD_ERR_OK !== absint( $file['error'] ) ) {
			return new \WP_Error( 'upload_error', __( 'One of the uploaded files could not be read.', 'greenberry' ), array( 'status' => 400 ) );
		}

		$max_size = max( 1, absint( $field['max_file_size'] ) ) * MB_IN_BYTES;
		if ( ! empty( $file['size'] ) && $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: 1: field label, 2: max size in MB. */
					__( '%1$s must be %2$d MB or smaller.', 'greenberry' ),
					$field['label'],
					absint( $field['max_file_size'] )
				),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);

		if ( ! empty( $uploaded['error'] ) ) {
			return new \WP_Error( 'upload_failed', $uploaded['error'], array( 'status' => 400 ) );
		}

		return array(
			'name' => sanitize_file_name( $file['name'] ),
			'path' => $uploaded['file'],
			'type' => isset( $uploaded['type'] ) ? sanitize_text_field( $uploaded['type'] ) : '',
		);
	}

	/**
	 * Pulls a nested file upload from $_FILES or REST file params.
	 *
	 * @param array  $files Uploaded files.
	 * @param string $key Field key.
	 * @return array|null
	 */
	private function get_uploaded_file( $files, $key ) {
		if ( isset( $files['greenberry_files']['name'][ $key ] ) ) {
			return array(
				'name'     => $files['greenberry_files']['name'][ $key ],
				'type'     => isset( $files['greenberry_files']['type'][ $key ] ) ? $files['greenberry_files']['type'][ $key ] : '',
				'tmp_name' => isset( $files['greenberry_files']['tmp_name'][ $key ] ) ? $files['greenberry_files']['tmp_name'][ $key ] : '',
				'error'    => isset( $files['greenberry_files']['error'][ $key ] ) ? $files['greenberry_files']['error'][ $key ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $files['greenberry_files']['size'][ $key ] ) ? $files['greenberry_files']['size'][ $key ] : 0,
			);
		}

		if ( isset( $files[ 'greenberry_files[' . $key . ']' ] ) ) {
			return $files[ 'greenberry_files[' . $key . ']' ];
		}

		return null;
	}

	/**
	 * Creates a normalized submission row.
	 *
	 * @param array  $field Field definition.
	 * @param string $value Raw sanitized value.
	 * @param string $display Display value.
	 * @return array
	 */
	private function submission_row( $field, $value, $display ) {
		return array(
			'key'     => $field['key'],
			'label'   => $field['label'],
			'type'    => $field['type'],
			'value'   => $value,
			'display' => $display,
		);
	}

	/**
	 * Verifies Cloudflare Turnstile via Simple Cloudflare Turnstile.
	 *
	 * @param array $form Form definition.
	 * @return true|\WP_Error
	 */
	private function verify_turnstile( $form ) {
		if ( empty( $form['turnstile_required'] ) ) {
			return true;
		}

		if ( ! function_exists( 'cfturnstile_check' ) ) {
			return new \WP_Error( 'turnstile_unavailable', __( 'Form protection is not configured. Please contact the site owner.', 'greenberry' ), array( 'status' => 503 ) );
		}

		$result = cfturnstile_check();
		if ( true === $result || ( is_array( $result ) && ! empty( $result['success'] ) ) ) {
			return true;
		}

		if ( function_exists( 'cfturnstile_failed_text' ) ) {
			$message = cfturnstile_failed_text();
		} elseif ( function_exists( 'cfturnstile_error_message' ) ) {
			$message = cfturnstile_error_message();
		} else {
			$message = __( 'Please complete the security check and try again.', 'greenberry' );
		}

		return new \WP_Error( 'turnstile_failed', wp_strip_all_tags( $message ), array( 'status' => 400 ) );
	}

	/**
	 * Deletes uploaded files after email delivery.
	 *
	 * @param array $attachments Uploaded attachment records.
	 * @return void
	 */
	private function delete_temporary_attachments( $attachments ) {
		foreach ( $attachments as $attachment ) {
			if ( empty( $attachment['path'] ) || ! file_exists( $attachment['path'] ) ) {
				continue;
			}

			wp_delete_file( $attachment['path'] );
		}
	}

	/**
	 * Converts common form booleans.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Checks basic IP/form rate limiting.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	private function is_rate_limited( $form_id ) {
		$count = absint( get_transient( $this->rate_limit_key( $form_id ) ) );

		return $count >= 10;
	}

	/**
	 * Increments rate limiting.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private function increment_rate_limit( $form_id ) {
		$key   = $this->rate_limit_key( $form_id );
		$count = absint( get_transient( $key ) );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Gets a privacy-preserving rate-limit key.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	private function rate_limit_key( $form_id ) {
		return 'greenberry_form_' . absint( $form_id ) . '_' . hash( 'sha256', $this->get_ip_address() . wp_salt( 'nonce' ) );
	}

	/**
	 * Gets the request IP address.
	 *
	 * @return string
	 */
	private function get_ip_address() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}
}
