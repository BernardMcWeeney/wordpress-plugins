<?php
/**
 * Newsletter module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Newsletter module.
 */
class Module {
	const CRON_HOOK = 'greenberry_newsletter_run_automations';

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
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$repository = new Repository();

		if ( get_option( 'greenberry_newsletter_db_version' ) !== GREENBERRY_VERSION ) {
			$repository->create_tables();
		}

		$mailer = new Mailer( $repository );
		$module = new self( $repository, $mailer );
		$module->register_hooks();

		( new Rest( $repository ) )->init();
		( new Blocks() )->init();

		if ( is_admin() ) {
			( new Admin( $repository, $mailer ) )->init();
		}

		self::ensure_cron_scheduled();
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		( new Repository() )->create_tables();
		self::ensure_cron_scheduled();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( self::CRON_HOOK, array( $this->mailer, 'run_digest_automations' ) );
		add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'handle_unsubscribe' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );
	}

	/**
	 * Schedules digest processing.
	 *
	 * @return void
	 */
	private static function ensure_cron_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Sends post-publish automations when content is newly published.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function handle_post_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$this->mailer->send_post_publish_automations( absint( $post->ID ) );
	}

	/**
	 * Handles public unsubscribe links.
	 *
	 * @return void
	 */
	public function handle_unsubscribe() {
		if ( empty( $_GET['greenberry_newsletter_unsubscribe'] ) ) {
			return;
		}

		$contact_id = isset( $_GET['contact'] ) ? absint( $_GET['contact'] ) : 0;
		$token      = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$contact    = $this->repository->get_contact( $contact_id );

		if ( ! $contact || ! Email_Template::verify_unsubscribe_token( $contact, $token ) ) {
			status_header( 403 );
			$this->render_unsubscribe_page(
				__( 'Unsubscribe link invalid', 'greenberry' ),
				__( 'This unsubscribe link is invalid or expired.', 'greenberry' )
			);
			exit;
		}

		$this->repository->update_contact_status( $contact_id, 'unsubscribed' );

		status_header( 200 );
		$this->render_unsubscribe_page(
			__( 'You are unsubscribed', 'greenberry' ),
			__( 'You have been removed from future newsletter emails.', 'greenberry' )
		);
		exit;
	}

	/**
	 * Registers data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_privacy_exporter( $exporters ) {
		$exporters['greenberry-newsletter'] = array(
			'exporter_friendly_name' => __( 'Greenberry Newsletter', 'greenberry' ),
			'callback'               => array( $this, 'privacy_exporter' ),
		);

		return $exporters;
	}

	/**
	 * Registers data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_privacy_eraser( $erasers ) {
		$erasers['greenberry-newsletter'] = array(
			'eraser_friendly_name' => __( 'Greenberry Newsletter', 'greenberry' ),
			'callback'             => array( $this, 'privacy_eraser' ),
		);

		return $erasers;
	}

	/**
	 * Exports Newsletter contact data for WordPress privacy tools.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public function privacy_exporter( $email_address, $page = 1 ) {
		$contact = $this->repository->get_contact_by_email( $email_address );
		if ( ! $contact ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		return array(
			'data' => array(
				array(
					'group_id'    => 'greenberry-newsletter',
					'group_label' => __( 'Greenberry Newsletter', 'greenberry' ),
					'item_id'     => 'contact-' . absint( $contact->id ),
					'data'        => array(
						array(
							'name'  => __( 'Email', 'greenberry' ),
							'value' => $contact->email,
						),
						array(
							'name'  => __( 'First name', 'greenberry' ),
							'value' => $contact->first_name,
						),
						array(
							'name'  => __( 'Last name', 'greenberry' ),
							'value' => $contact->last_name,
						),
						array(
							'name'  => __( 'Status', 'greenberry' ),
							'value' => $contact->status,
						),
						array(
							'name'  => __( 'Tags', 'greenberry' ),
							'value' => implode( ', ', $this->repository->get_contact_tags( $contact->id ) ),
						),
						array(
							'name'  => __( 'Consent source', 'greenberry' ),
							'value' => $contact->consent_source,
						),
						array(
							'name'  => __( 'Consent date', 'greenberry' ),
							'value' => $contact->consent_at,
						),
					),
				),
			),
			'done' => true,
		);
	}

	/**
	 * Erases Newsletter contact data for WordPress privacy tools.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public function privacy_eraser( $email_address, $page = 1 ) {
		$contact = $this->repository->get_contact_by_email( $email_address );
		if ( ! $contact ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed = $this->repository->delete_contact( absint( $contact->id ) );

		return array(
			'items_removed'  => $removed,
			'items_retained' => ! $removed,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Renders a minimal public unsubscribe page.
	 *
	 * @param string $title Title.
	 * @param string $message Message.
	 * @return void
	 */
	private function render_unsubscribe_page( $title, $message ) {
		nocache_headers();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body <?php body_class( 'greenberry-newsletter-unsubscribe' ); ?>>
			<main style="box-sizing:border-box;margin:0 auto;max-width:680px;padding:12vh 24px;">
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to site', 'greenberry' ); ?></a></p>
			</main>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}
}
