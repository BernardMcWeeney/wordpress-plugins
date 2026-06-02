<?php
/**
 * Newsletter mail delivery.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Sends campaigns and automations via wp_mail().
 */
class Mailer {
	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Template builder.
	 *
	 * @var Email_Template
	 */
	private $template;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
		$this->template    = new Email_Template();
	}

	/**
	 * Sends a manual campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array{sent:int,total:int}
	 */
	public function send_campaign( $campaign_id ) {
		$campaign = $this->repository->get_campaign( $campaign_id );
		if ( ! $campaign || 'sent' === $campaign->status ) {
			return array(
				'sent'  => 0,
				'total' => 0,
			);
		}

		$result = $this->send_to_list(
			absint( $campaign->list_id ),
			$campaign->subject,
			$campaign->preheader,
			wpautop( $campaign->content )
		);

		if ( $result['sent'] > 0 ) {
			$this->repository->mark_campaign_sent( $campaign_id );
		}

		return $result;
	}

	/**
	 * Sends a campaign draft to one test recipient without saving or marking sent.
	 *
	 * @param array  $data Campaign data.
	 * @param string $recipient Test recipient email.
	 * @return true|\WP_Error
	 */
	public function send_test_campaign( $data, $recipient ) {
		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			return new \WP_Error( 'invalid_test_recipient', __( 'Please enter a valid test recipient email address.', 'greenberry' ) );
		}

		$subject = isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '';
		if ( '' === $subject ) {
			return new \WP_Error( 'missing_campaign_fields', __( 'Campaign name and subject are required.', 'greenberry' ) );
		}

		$contact = (object) array(
			'id'    => 0,
			'email' => $recipient,
		);

		$html = $this->template->render(
			$subject,
			isset( $data['preheader'] ) ? sanitize_text_field( $data['preheader'] ) : '',
			wpautop( isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '' ),
			$contact
		);

		$sent = wp_mail(
			$recipient,
			$subject,
			$html,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		return $sent ? true : new \WP_Error( 'test_send_failed', __( 'The test email could not be sent.', 'greenberry' ) );
	}

	/**
	 * Sends content to each subscribed contact in a list.
	 *
	 * @param int    $list_id List ID.
	 * @param string $subject Subject.
	 * @param string $preheader Preview text.
	 * @param string $content HTML content.
	 * @return array{sent:int,total:int}
	 */
	public function send_to_list( $list_id, $subject, $preheader, $content ) {
		$contacts = $this->repository->get_contacts_for_list( $list_id, 500 );
		$headers  = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent     = 0;

		foreach ( $contacts as $contact ) {
			$html = $this->template->render( $subject, $preheader, $content, $contact );

			if ( wp_mail( $contact->email, $subject, $html, $headers ) ) {
				++$sent;
			}
		}

		return array(
			'sent'  => $sent,
			'total' => count( $contacts ),
		);
	}

	/**
	 * Sends immediate post-publish automations.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function send_post_publish_automations( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		foreach ( $this->repository->get_automations( 'post_publish' ) as $automation ) {
			$post_types = $this->repository->get_automation_post_types( $automation );
			if ( ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}

			$subject = str_replace(
				array( '{post_title}', '{site_name}' ),
				array( get_the_title( $post ), get_bloginfo( 'name' ) ),
				$automation->subject
			);

			$content = $this->build_post_content( $post );
			$this->send_to_list( absint( $automation->list_id ), $subject, '', $content );
			$this->repository->mark_automation_sent( absint( $automation->id ) );
		}
	}

	/**
	 * Runs due daily and weekly digests.
	 *
	 * @return void
	 */
	public function run_digest_automations() {
		$automations = array_merge(
			$this->repository->get_automations( 'daily_digest' ),
			$this->repository->get_automations( 'weekly_digest' )
		);

		foreach ( $automations as $automation ) {
			if ( ! $this->is_digest_due( $automation ) ) {
				continue;
			}

			$posts = $this->get_digest_posts( $automation );
			if ( empty( $posts ) ) {
				$this->repository->mark_automation_sent( absint( $automation->id ) );
				continue;
			}

			$subject = str_replace(
				'{site_name}',
				get_bloginfo( 'name' ),
				$automation->subject
			);

			$this->send_to_list(
				absint( $automation->list_id ),
				$subject,
				'',
				$this->build_digest_content( $posts )
			);

			$this->repository->mark_automation_sent( absint( $automation->id ) );
		}
	}

	/**
	 * Checks whether a digest automation is due.
	 *
	 * @param object $automation Automation row.
	 * @return bool
	 */
	private function is_digest_due( $automation ) {
		$interval = 'weekly_digest' === $automation->trigger_type ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
		if ( empty( $automation->last_sent_at ) ) {
			return true;
		}

		return ( current_time( 'timestamp' ) - strtotime( $automation->last_sent_at ) ) >= $interval;
	}

	/**
	 * Gets posts for a digest.
	 *
	 * @param object $automation Automation row.
	 * @return array<int,\WP_Post>
	 */
	private function get_digest_posts( $automation ) {
		$interval = 'weekly_digest' === $automation->trigger_type ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
		$since    = ! empty( $automation->last_sent_at )
			? $automation->last_sent_at
			: gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $interval );

		return get_posts(
			array(
				'post_type'      => $this->repository->get_automation_post_types( $automation ),
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'date_query'     => array(
					array(
						'after'     => $since,
						'inclusive' => false,
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Builds an email section for one post.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private function build_post_content( $post ) {
		$excerpt = has_excerpt( $post )
			? get_the_excerpt( $post )
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 36 );

		return sprintf(
			'<h1 style="font-size:28px;line-height:1.2;margin:0 0 14px 0;">%1$s</h1><p style="margin:0 0 20px 0;">%2$s</p><p><a href="%3$s" style="background:#1d2327;border-radius:4px;color:#ffffff;display:inline-block;padding:11px 16px;text-decoration:none;">%4$s</a></p>',
			esc_html( get_the_title( $post ) ),
			esc_html( $excerpt ),
			esc_url( get_permalink( $post ) ),
			esc_html__( 'Read more', 'greenberry' )
		);
	}

	/**
	 * Builds digest content.
	 *
	 * @param array<int,\WP_Post> $posts Posts.
	 * @return string
	 */
	private function build_digest_content( $posts ) {
		$html  = '<h1 style="font-size:28px;line-height:1.2;margin:0 0 20px 0;">' . esc_html__( 'Latest updates', 'greenberry' ) . '</h1>';
		$html .= '<div>';

		foreach ( $posts as $post ) {
			$excerpt = has_excerpt( $post )
				? get_the_excerpt( $post )
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 );

			$html .= sprintf(
				'<article style="border-top:1px solid #e2e4e7;padding:18px 0;"><h2 style="font-size:20px;line-height:1.3;margin:0 0 8px 0;"><a href="%1$s" style="color:#1d2327;text-decoration:none;">%2$s</a></h2><p style="margin:0;color:#50575e;">%3$s</p></article>',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_html( $excerpt )
			);
		}

		$html .= '</div>';

		return $html;
	}
}
