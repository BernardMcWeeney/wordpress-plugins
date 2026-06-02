<?php
/**
 * Forms data access.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Stores form definitions only. Submissions are never persisted.
 */
class Repository {
	const OPTION_NAME    = 'greenberry_forms';
	const NEXT_ID_OPTION = 'greenberry_forms_next_id';

	/**
	 * Ensures a starter form exists.
	 *
	 * @return void
	 */
	public function install_defaults() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$this->save_form( $this->default_form() );
	}

	/**
	 * Returns all saved forms.
	 *
	 * @return array<int,array>
	 */
	public function get_forms() {
		$forms = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $forms ) ) {
			return array();
		}

		$clean = array();
		foreach ( $forms as $id => $form ) {
			$form_id = absint( $id );
			if ( ! $form_id || ! is_array( $form ) ) {
				continue;
			}

			$clean[ $form_id ] = $this->normalize_form( $form, $form_id );
		}

		uasort(
			$clean,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		return $clean;
	}

	/**
	 * Returns one saved form.
	 *
	 * @param int $form_id Form ID.
	 * @return array|null
	 */
	public function get_form( $form_id ) {
		$forms   = $this->get_forms();
		$form_id = absint( $form_id );

		return isset( $forms[ $form_id ] ) ? $forms[ $form_id ] : null;
	}

	/**
	 * Gets the first saved form.
	 *
	 * @return array|null
	 */
	public function get_first_form() {
		$forms = $this->get_forms();
		if ( empty( $forms ) ) {
			return null;
		}

		return reset( $forms );
	}

	/**
	 * Saves a form definition.
	 *
	 * @param array $data Raw form data.
	 * @return int|\WP_Error
	 */
	public function save_form( $data ) {
		$forms = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $forms ) ) {
			$forms = array();
		}

		$form_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		if ( ! $form_id ) {
			$form_id = $this->next_id();
		}

		$existing = isset( $forms[ $form_id ] ) && is_array( $forms[ $form_id ] ) ? $forms[ $form_id ] : array();
		$form     = $this->sanitize_form_data( $data, $form_id, $existing );

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$forms[ $form_id ] = $form;
		update_option( self::OPTION_NAME, $forms, false );

		return $form_id;
	}

	/**
	 * Deletes a saved form definition.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function delete_form( $form_id ) {
		$forms   = get_option( self::OPTION_NAME, array() );
		$form_id = absint( $form_id );

		if ( ! $form_id || ! is_array( $forms ) || empty( $forms[ $form_id ] ) ) {
			return false;
		}

		unset( $forms[ $form_id ] );
		update_option( self::OPTION_NAME, $forms, false );

		return true;
	}

	/**
	 * Returns options suitable for an email field select.
	 *
	 * @param array $form Form definition.
	 * @return array<string,string>
	 */
	public function get_email_fields( $form ) {
		$options = array();

		foreach ( $form['fields'] as $field ) {
			if ( 'email' !== $field['type'] ) {
				continue;
			}

			$options[ $field['key'] ] = $field['label'];
		}

		return $options;
	}

	/**
	 * Sanitizes a form definition.
	 *
	 * @param array $data Raw form data.
	 * @param int   $form_id Form ID.
	 * @param array $existing Existing form.
	 * @return array|\WP_Error
	 */
	private function sanitize_form_data( $data, $form_id, $existing = array() ) {
		$title = isset( $data['title'] ) ? sanitize_text_field( wp_unslash( $data['title'] ) ) : '';
		if ( '' === $title ) {
			return new \WP_Error( 'missing_title', __( 'Please enter a form title.', 'greenberry' ) );
		}

		$fields = isset( $data['fields'] ) && is_array( $data['fields'] ) ? wp_unslash( $data['fields'] ) : array();
		$fields = $this->sanitize_fields( $fields );
		if ( empty( $fields ) ) {
			return new \WP_Error( 'missing_fields', __( 'Please add at least one form field.', 'greenberry' ) );
		}

		$recipient = isset( $data['recipient_email'] ) ? sanitize_email( wp_unslash( $data['recipient_email'] ) ) : '';
		if ( '' === $recipient ) {
			$recipient = sanitize_email( get_option( 'admin_email' ) );
		}

		if ( ! is_email( $recipient ) ) {
			return new \WP_Error( 'invalid_recipient', __( 'Please enter a valid recipient email address.', 'greenberry' ) );
		}

		$now = current_time( 'mysql' );

		return array(
			'id'                 => absint( $form_id ),
			'title'              => $title,
			'description'        => isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '',
			'recipient_email'    => $recipient,
			'subject'            => isset( $data['subject'] ) ? sanitize_text_field( wp_unslash( $data['subject'] ) ) : '[{site_name}] {form_title}',
			'reply_to_field'     => isset( $data['reply_to_field'] ) ? sanitize_key( wp_unslash( $data['reply_to_field'] ) ) : '',
			'copy_to_field'      => isset( $data['copy_to_field'] ) ? sanitize_key( wp_unslash( $data['copy_to_field'] ) ) : '',
			'copy_subject'       => isset( $data['copy_subject'] ) ? sanitize_text_field( wp_unslash( $data['copy_subject'] ) ) : __( 'We received your message', 'greenberry' ),
			'copy_message'       => isset( $data['copy_message'] ) ? sanitize_textarea_field( wp_unslash( $data['copy_message'] ) ) : __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
			'submit_label'       => isset( $data['submit_label'] ) ? sanitize_text_field( wp_unslash( $data['submit_label'] ) ) : __( 'Send', 'greenberry' ),
			'success_message'    => isset( $data['success_message'] ) ? sanitize_text_field( wp_unslash( $data['success_message'] ) ) : __( 'Thanks. Your message has been sent.', 'greenberry' ),
			'turnstile_required' => ! empty( $data['turnstile_required'] ),
			'fields'             => $fields,
			'created_at'         => isset( $existing['created_at'] ) ? sanitize_text_field( $existing['created_at'] ) : $now,
			'updated_at'         => $now,
		);
	}

	/**
	 * Sanitizes field definitions.
	 *
	 * @param array $fields Raw field rows.
	 * @return array<int,array>
	 */
	private function sanitize_fields( $fields ) {
		$clean       = array();
		$used_keys   = array();
		$field_types = array( 'text', 'email', 'textarea', 'address', 'checkbox', 'file' );

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
			if ( '' === $key ) {
				$key = sanitize_key( sanitize_title( $label ) );
			}
			if ( '' === $key ) {
				$key = 'field';
			}

			$base_key = $key;
			$suffix   = 2;
			while ( isset( $used_keys[ $key ] ) ) {
				$key = $base_key . '_' . $suffix;
				++$suffix;
			}
			$used_keys[ $key ] = true;

			$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			if ( ! in_array( $type, $field_types, true ) ) {
				$type = 'text';
			}

			$max_size = isset( $field['max_file_size'] ) ? absint( $field['max_file_size'] ) : 5;
			if ( $max_size < 1 ) {
				$max_size = 1;
			} elseif ( $max_size > 25 ) {
				$max_size = 25;
			}

			$clean[] = array(
				'key'           => $key,
				'label'         => $label,
				'type'          => $type,
				'required'      => ! empty( $field['required'] ),
				'placeholder'   => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
				'help_text'     => isset( $field['help_text'] ) ? sanitize_text_field( $field['help_text'] ) : '',
				'accept'        => isset( $field['accept'] ) ? sanitize_text_field( $field['accept'] ) : '',
				'max_file_size' => $max_size,
			);
		}

		return $clean;
	}

	/**
	 * Normalizes legacy/missing form values.
	 *
	 * @param array $form Form definition.
	 * @param int   $form_id Form ID.
	 * @return array
	 */
	private function normalize_form( $form, $form_id ) {
		$defaults = array(
			'id'                 => absint( $form_id ),
			'title'              => '',
			'description'        => '',
			'recipient_email'    => sanitize_email( get_option( 'admin_email' ) ),
			'subject'            => '[{site_name}] {form_title}',
			'reply_to_field'     => '',
			'copy_to_field'      => '',
			'copy_subject'       => __( 'We received your message', 'greenberry' ),
			'copy_message'       => __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
			'submit_label'       => __( 'Send', 'greenberry' ),
			'success_message'    => __( 'Thanks. Your message has been sent.', 'greenberry' ),
			'turnstile_required' => true,
			'fields'             => array(),
			'created_at'         => '',
			'updated_at'         => '',
		);

		$form = wp_parse_args( $form, $defaults );
		if ( ! is_array( $form['fields'] ) ) {
			$form['fields'] = array();
		}

		return $form;
	}

	/**
	 * Creates a starter contact form.
	 *
	 * @return array
	 */
	private function default_form() {
		return array(
			'title'              => __( 'Contact form', 'greenberry' ),
			'description'        => __( 'Send a message to the site owner.', 'greenberry' ),
			'recipient_email'    => sanitize_email( get_option( 'admin_email' ) ),
			'subject'            => '[{site_name}] {form_title}',
			'reply_to_field'     => 'email',
			'copy_to_field'      => 'email',
			'copy_subject'       => __( 'We received your message', 'greenberry' ),
			'copy_message'       => __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
			'submit_label'       => __( 'Send message', 'greenberry' ),
			'success_message'    => __( 'Thanks. Your message has been sent.', 'greenberry' ),
			'turnstile_required' => true,
			'fields'             => array(
				array(
					'key'         => 'name',
					'label'       => __( 'Name', 'greenberry' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '',
					'help_text'   => '',
				),
				array(
					'key'         => 'email',
					'label'       => __( 'Email address', 'greenberry' ),
					'type'        => 'email',
					'required'    => true,
					'placeholder' => '',
					'help_text'   => __( 'Used only to reply to this enquiry.', 'greenberry' ),
				),
				array(
					'key'         => 'message',
					'label'       => __( 'Message', 'greenberry' ),
					'type'        => 'textarea',
					'required'    => true,
					'placeholder' => '',
					'help_text'   => '',
				),
				array(
					'key'       => 'privacy_consent',
					'label'     => __( 'I consent to this information being emailed to the site owner for the purpose of responding to my enquiry.', 'greenberry' ),
					'type'      => 'checkbox',
					'required'  => true,
					'help_text' => '',
				),
				array(
					'key'           => 'attachment',
					'label'         => __( 'Attachment', 'greenberry' ),
					'type'          => 'file',
					'required'      => false,
					'help_text'     => __( 'Optional PDF, document, or image.', 'greenberry' ),
					'accept'        => '.pdf,.doc,.docx,.jpg,.jpeg,.png',
					'max_file_size' => 5,
				),
			),
		);
	}

	/**
	 * Gets and increments the next form ID.
	 *
	 * @return int
	 */
	private function next_id() {
		$next = absint( get_option( self::NEXT_ID_OPTION, 1 ) );
		if ( $next < 1 ) {
			$next = 1;
		}

		update_option( self::NEXT_ID_OPTION, $next + 1, false );

		return $next;
	}
}
