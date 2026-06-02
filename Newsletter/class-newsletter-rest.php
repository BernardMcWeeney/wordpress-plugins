<?php
/**
 * Newsletter REST and public form handlers.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Handles public subscription requests.
 */
class Rest {
	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_nopriv_greenberry_newsletter_subscribe', array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_greenberry_newsletter_subscribe', array( $this, 'handle_form_post' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'greenberry/v1',
			'/newsletter/subscribe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_rest_subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'      => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
					'first_name' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tags'       => array(
						'required' => false,
					),
					'consent'    => array(
						'required' => true,
					),
					'website'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handles REST subscriptions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_rest_subscribe( \WP_REST_Request $request ) {
		$result = $this->subscribe_from_array( $request->get_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Thanks. Please check your inbox for future updates.', 'greenberry' ),
			)
		);
	}

	/**
	 * Handles non-JS form submissions.
	 *
	 * @return void
	 */
	public function handle_form_post() {
		$result   = $this->subscribe_from_array( wp_unslash( $_POST ) );
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		$redirect = remove_query_arg( array( 'greenberry_newsletter', 'greenberry_newsletter_error' ), $redirect );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'greenberry_newsletter_error' => rawurlencode( $result->get_error_message() ),
					),
					$redirect
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'greenberry_newsletter' => 'subscribed',
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Subscribes a contact from sanitized or raw input.
	 *
	 * @param array $data Request data.
	 * @return int|\WP_Error
	 */
	public function subscribe_from_array( $data ) {
		if ( ! empty( $data['website'] ) ) {
			return new \WP_Error( 'spam_check_failed', __( 'The subscription could not be accepted.', 'greenberry' ), array( 'status' => 400 ) );
		}

		if ( $this->is_rate_limited() ) {
			return new \WP_Error( 'rate_limited', __( 'Please wait before trying again.', 'greenberry' ), array( 'status' => 429 ) );
		}

		$email   = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$consent = isset( $data['consent'] ) ? $this->to_bool( $data['consent'] ) : false;

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'greenberry' ), array( 'status' => 400 ) );
		}

		if ( ! $consent ) {
			return new \WP_Error( 'missing_consent', __( 'Please confirm consent to receive email updates.', 'greenberry' ), array( 'status' => 400 ) );
		}

		$tags = isset( $data['tags'] ) ? $data['tags'] : 'newsletter';
		if ( is_array( $tags ) ) {
			$tags = array_map( 'sanitize_text_field', $tags );
		} else {
			$tags = sanitize_text_field( $tags );
		}

		$consent_text = isset( $data['consent_text'] )
			? sanitize_text_field( $data['consent_text'] )
			: __( 'I agree to receive email updates and understand I can unsubscribe at any time.', 'greenberry' );

		$result = $this->repository->upsert_contact(
			$email,
			array(
				'first_name'         => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
				'last_name'          => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
				'status'             => 'subscribed',
				'tags'               => $tags,
				'consent_source'     => 'newsletter_form',
				'consent_text'       => $consent_text,
				'consent_ip'         => $this->get_ip_address(),
				'consent_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->increment_rate_limit();

		return $result;
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
	 * Checks basic IP rate limiting.
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$count = absint( get_transient( $this->rate_limit_key() ) );

		return $count >= 10;
	}

	/**
	 * Increments rate limiting.
	 *
	 * @return void
	 */
	private function increment_rate_limit() {
		$key   = $this->rate_limit_key();
		$count = absint( get_transient( $key ) );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Gets a privacy-preserving rate-limit key.
	 *
	 * @return string
	 */
	private function rate_limit_key() {
		return 'greenberry_newsletter_' . hash( 'sha256', $this->get_ip_address() . wp_salt( 'nonce' ) );
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
