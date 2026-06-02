<?php
/**
 * Social publishing service.
 *
 * @package Greenberry
 */

namespace Greenberry\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes eligible posts to configured social providers.
 */
class Publisher {
	const META_ENABLED   = 'greenberry_social_enabled';
	const META_CHANNELS  = 'greenberry_social_channels';
	const META_MESSAGE   = 'greenberry_social_message';
	const META_PUBLISHED = 'greenberry_social_published';

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Publishes when an eligible item is first published.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function handle_post_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post instanceof \WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->publish_post( absint( $post->ID ), 'automatic' );
	}

	/**
	 * Publishes a post to selected channels.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $source Source label.
	 * @return array<string,mixed>
	 */
	public function publish_post( $post_id, $source = 'automatic' ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}

		$settings = $this->settings->get();
		if ( empty( $settings['enabled'] ) ) {
			return array();
		}

		$publish_mode = $this->get_publish_mode( $post_id );
		if ( 'off' === $publish_mode ) {
			return array();
		}

		if ( 'on' !== $publish_mode && ! $this->post_matches_rules( $post, $settings ) ) {
			return array();
		}

		$channels = $this->get_selected_channels( $post_id, $settings );
		$results  = array();

		foreach ( $channels as $provider ) {
			if ( ! $this->provider_is_ready( $provider, $settings ) ) {
				continue;
			}

			if ( 'automatic' === $source && $this->was_already_published( $post_id, $provider ) ) {
				continue;
			}

			$message = $this->build_message( $post, $provider, $settings );
			$result  = $this->send_to_provider( $provider, $message, $post, $settings );

			$results[ $provider ] = $result;
			$this->record_result( $post, $provider, $source, $result );
		}

		return $results;
	}

	/**
	 * Builds post text using the selected template.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $provider Provider key.
	 * @param array    $settings Settings.
	 * @return string
	 */
	public function build_message( $post, $provider = 'bluesky', $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->settings->get();
		}

		$template = get_post_meta( $post->ID, self::META_MESSAGE, true );
		if ( '' === trim( (string) $template ) ) {
			$template = $settings['message_template'];
		}

		$message = $this->replace_tokens( $template, $post );
		$message = trim( preg_replace( "/[ \t]+\n/", "\n", $message ) );

		if ( '' === $message ) {
			$message = get_the_title( $post ) . "\n" . get_permalink( $post );
		}

		if ( 'bluesky' === $provider ) {
			return $this->limit_message( $message, 300 );
		}

		if ( 'linkedin' === $provider ) {
			return $this->limit_message( $message, 3000 );
		}

		return $message;
	}

	/**
	 * Gets per-post publish mode.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_publish_mode( $post_id ) {
		$mode = get_post_meta( $post_id, self::META_ENABLED, true );

		return in_array( $mode, array( 'inherit', 'on', 'off' ), true ) ? $mode : 'inherit';
	}

	/**
	 * Gets selected channels for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $settings Settings.
	 * @return array<int,string>
	 */
	private function get_selected_channels( $post_id, $settings ) {
		$channels = get_post_meta( $post_id, self::META_CHANNELS, true );
		if ( ! is_array( $channels ) || empty( $channels ) ) {
			$channels = $this->settings->get_enabled_default_channels( $settings );
		}

		$allowed = array_keys( $this->settings->providers() );
		$channels = array_values( array_intersect( array_map( 'sanitize_key', $channels ), $allowed ) );

		return array_unique( $channels );
	}

	/**
	 * Checks publishing rules.
	 *
	 * @param \WP_Post $post Post.
	 * @param array    $settings Settings.
	 * @return bool
	 */
	private function post_matches_rules( $post, $settings ) {
		$rules = $settings['rules'];

		if ( empty( $rules['post_types'] ) || ! in_array( $post->post_type, $rules['post_types'], true ) ) {
			return false;
		}

		if ( ! empty( $rules['categories'] ) ) {
			if ( ! taxonomy_exists( 'category' ) || ! has_term( $rules['categories'], 'category', $post ) ) {
				return false;
			}
		}

		if ( ! empty( $rules['tags'] ) ) {
			if ( ! taxonomy_exists( 'post_tag' ) || ! has_term( $rules['tags'], 'post_tag', $post ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether provider credentials are ready.
	 *
	 * @param string $provider Provider key.
	 * @param array  $settings Settings.
	 * @return bool
	 */
	private function provider_is_ready( $provider, $settings ) {
		$status = $this->settings->get_provider_status( $provider, $settings );

		return ! empty( $status['ready'] );
	}

	/**
	 * Checks whether an automatic post has already been sent.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $provider Provider key.
	 * @return bool
	 */
	private function was_already_published( $post_id, $provider ) {
		$published = get_post_meta( $post_id, self::META_PUBLISHED, true );

		return is_array( $published ) && ! empty( $published[ $provider ] );
	}

	/**
	 * Sends text to a provider.
	 *
	 * @param string   $provider Provider key.
	 * @param string   $message Message text.
	 * @param \WP_Post $post Post object.
	 * @param array    $settings Settings.
	 * @return array|\WP_Error
	 */
	private function send_to_provider( $provider, $message, $post, $settings ) {
		if ( 'bluesky' === $provider ) {
			return $this->send_bluesky( $message, $post, $settings['providers']['bluesky'] );
		}

		if ( 'linkedin' === $provider ) {
			return $this->send_linkedin( $message, $post, $settings['providers']['linkedin'] );
		}

		return new \WP_Error( 'unsupported_provider', __( 'Unsupported social provider.', 'greenberry' ) );
	}

	/**
	 * Sends a Bluesky post.
	 *
	 * @param string   $message Message text.
	 * @param \WP_Post $post Post object.
	 * @param array    $config Provider config.
	 * @return array|\WP_Error
	 */
	private function send_bluesky( $message, $post, $config ) {
		$pds_host = untrailingslashit( $config['pds_host'] );

		$session = wp_remote_post(
			$pds_host . '/xrpc/com.atproto.server.createSession',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'identifier' => $config['identifier'],
						'password'   => $config['token'],
					)
				),
			)
		);

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$session_code = wp_remote_retrieve_response_code( $session );
		$session_body = json_decode( wp_remote_retrieve_body( $session ), true );
		if ( 200 !== $session_code || empty( $session_body['accessJwt'] ) ) {
			return new \WP_Error( 'bluesky_session_failed', $this->remote_error_message( $session, __( 'Could not create a Bluesky session.', 'greenberry' ) ) );
		}

		$post_record = array(
			'$type'     => 'app.bsky.feed.post',
			'text'      => $message,
			'createdAt' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		$facets = $this->bluesky_link_facets( $message );
		if ( ! empty( $facets ) ) {
			$post_record['facets'] = $facets;
		}

		$embed = $this->bluesky_external_embed( $post );
		if ( ! empty( $embed ) ) {
			$post_record['embed'] = $embed;
		}

		$record = wp_remote_post(
			$pds_host . '/xrpc/com.atproto.repo.createRecord',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $session_body['accessJwt'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'repo'       => ! empty( $session_body['did'] ) ? $session_body['did'] : $config['identifier'],
						'collection' => 'app.bsky.feed.post',
						'record'     => $post_record,
					)
				),
			)
		);

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$record_code = wp_remote_retrieve_response_code( $record );
		$record_body = json_decode( wp_remote_retrieve_body( $record ), true );
		if ( 200 !== $record_code || empty( $record_body['uri'] ) ) {
			return new \WP_Error( 'bluesky_publish_failed', $this->remote_error_message( $record, __( 'Could not publish to Bluesky.', 'greenberry' ) ) );
		}

		return array(
			'id'  => $record_body['uri'],
			'url' => $this->bluesky_post_url( $record_body['uri'], $config['identifier'] ),
		);
	}

	/**
	 * Sends a LinkedIn post.
	 *
	 * @param string   $message Message text.
	 * @param \WP_Post $post Post object.
	 * @param array    $config Provider config.
	 * @return array|\WP_Error
	 */
	private function send_linkedin( $message, $post, $config ) {
		$response = wp_remote_post(
			'https://api.linkedin.com/rest/posts',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'             => 'Bearer ' . $config['token'],
					'Content-Type'              => 'application/json',
					'Linkedin-Version'          => $config['version'],
					'X-Restli-Protocol-Version' => '2.0.0',
				),
				'body'    => wp_json_encode(
					array(
						'author'                  => $config['author_urn'],
						'commentary'              => $message,
						'visibility'              => 'PUBLIC',
						'distribution'            => array(
							'feedDistribution'               => 'MAIN_FEED',
							'targetEntities'                 => array(),
							'thirdPartyDistributionChannels' => array(),
						),
						'lifecycleState'          => 'PUBLISHED',
						'isReshareDisabledByAuthor' => false,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 201 !== $code ) {
			return new \WP_Error( 'linkedin_publish_failed', $this->remote_error_message( $response, __( 'Could not publish to LinkedIn.', 'greenberry' ) ) );
		}

		$id = wp_remote_retrieve_header( $response, 'x-restli-id' );
		if ( '' === $id ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$id   = isset( $body['id'] ) ? $body['id'] : '';
		}

		return array(
			'id'  => $id,
			'url' => '',
		);
	}

	/**
	 * Replaces message template tokens.
	 *
	 * @param string   $template Template.
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function replace_tokens( $template, $post ) {
		$tokens = array(
			'{site_name}'  => get_bloginfo( 'name' ),
			'{post_title}' => get_the_title( $post ),
			'{post_url}'   => get_permalink( $post ),
			'{excerpt}'    => $this->get_post_excerpt( $post ),
			'{author}'     => get_the_author_meta( 'display_name', $post->post_author ),
			'{date}'       => get_the_date( '', $post ),
			'{hashtags}'   => implode( ' ', $this->get_post_hashtags( $post ) ),
		);

		return strtr( $template, $tokens );
	}

	/**
	 * Gets clean excerpt text for social cards and tokens.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_post_excerpt( $post ) {
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );

		return trim( wp_strip_all_tags( $excerpt ) );
	}

	/**
	 * Builds Bluesky link facets for URLs in the post text.
	 *
	 * @param string $message Message text.
	 * @return array<int,array>
	 */
	private function bluesky_link_facets( $message ) {
		if ( ! preg_match_all( '#https?://[^\s<>"\']+#u', $message, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$facets = array();
		foreach ( $matches[0] as $match ) {
			$url   = rtrim( $match[0], '.,;!?)' );
			$start = absint( $match[1] );
			$end   = $start + strlen( $url );

			if ( '' === $url ) {
				continue;
			}

			$facets[] = array(
				'index'    => array(
					'byteStart' => $start,
					'byteEnd'   => $end,
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#link',
						'uri'   => esc_url_raw( $url ),
					),
				),
			);
		}

		return $facets;
	}

	/**
	 * Builds a Bluesky external embed for the WordPress post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function bluesky_external_embed( $post ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			return array();
		}

		return array(
			'$type'    => 'app.bsky.embed.external',
			'external' => array(
				'uri'         => esc_url_raw( $url ),
				'title'       => $this->limit_message( wp_strip_all_tags( get_the_title( $post ) ), 120 ),
				'description' => $this->limit_message( $this->get_post_excerpt( $post ), 280 ),
			),
		);
	}

	/**
	 * Gets post tags as hashtags.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int,string>
	 */
	private function get_post_hashtags( $post ) {
		$terms = get_the_terms( $post, 'post_tag' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$hashtags = array();
		foreach ( $terms as $term ) {
			$tag = preg_replace( '/[^A-Za-z0-9_]/', '', $term->name );
			if ( '' !== $tag ) {
				$hashtags[] = '#' . $tag;
			}
		}

		return $hashtags;
	}

	/**
	 * Limits message length conservatively for provider APIs.
	 *
	 * @param string $message Message.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	private function limit_message( $message, $limit ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		if ( $length <= $limit ) {
			return $message;
		}

		$suffix = '...';
		$max    = max( 0, $limit - strlen( $suffix ) );

		if ( function_exists( 'mb_substr' ) ) {
			return rtrim( mb_substr( $message, 0, $max ) ) . $suffix;
		}

		return rtrim( substr( $message, 0, $max ) ) . $suffix;
	}

	/**
	 * Records a provider result.
	 *
	 * @param \WP_Post        $post Post object.
	 * @param string          $provider Provider key.
	 * @param string          $source Source label.
	 * @param array|\WP_Error $result Provider result.
	 * @return void
	 */
	private function record_result( $post, $provider, $source, $result ) {
		$providers = $this->settings->providers();
		$label     = isset( $providers[ $provider ] ) ? $providers[ $provider ]['label'] : $provider;
		$success   = ! is_wp_error( $result );

		if ( $success ) {
			$this->mark_published( $post->ID, $provider, $result );
		}

		$this->settings->add_log_entry(
			array(
				'post_id'     => absint( $post->ID ),
				'post_title'  => get_the_title( $post ),
				'provider'    => $label,
				'status'      => $success ? 'success' : 'failed',
				'external_id' => $success && ! empty( $result['id'] ) ? $result['id'] : '',
				'url'         => $success && ! empty( $result['url'] ) ? $result['url'] : '',
				'message'     => $success ? __( 'Published', 'greenberry' ) : $result->get_error_message(),
				'source'      => $source,
			)
		);
	}

	/**
	 * Marks a post as published to a provider.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $provider Provider key.
	 * @param array  $result Provider result.
	 * @return void
	 */
	private function mark_published( $post_id, $provider, $result ) {
		$published = get_post_meta( $post_id, self::META_PUBLISHED, true );
		if ( ! is_array( $published ) ) {
			$published = array();
		}

		$published[ $provider ] = array(
			'id'           => isset( $result['id'] ) ? sanitize_text_field( $result['id'] ) : '',
			'url'          => isset( $result['url'] ) ? esc_url_raw( $result['url'] ) : '',
			'published_at' => current_time( 'mysql' ),
		);

		update_post_meta( $post_id, self::META_PUBLISHED, $published );
	}

	/**
	 * Extracts readable API error messages.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $fallback Fallback message.
	 * @return string
	 */
	private function remote_error_message( $response, $fallback ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) ) {
			foreach ( array( 'message', 'error', 'detail' ) as $key ) {
				if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
					return sanitize_text_field( $body[ $key ] );
				}
			}
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code ) {
			return sprintf(
				/* translators: %d: HTTP status code. */
				__( '%1$s HTTP %2$d.', 'greenberry' ),
				$fallback,
				absint( $code )
			);
		}

		return $fallback;
	}

	/**
	 * Converts a Bluesky AT URI into a profile URL.
	 *
	 * @param string $uri AT URI.
	 * @param string $identifier Account handle or DID.
	 * @return string
	 */
	private function bluesky_post_url( $uri, $identifier ) {
		if ( preg_match( '#^at://[^/]+/app\.bsky\.feed\.post/([^/]+)$#', $uri, $matches ) ) {
			return 'https://bsky.app/profile/' . rawurlencode( $identifier ) . '/post/' . rawurlencode( $matches[1] );
		}

		return '';
	}
}
