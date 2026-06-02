<?php
/**
 * Newsletter email template post type.
 *
 * Reusable email designs built in the block editor. Drop a {posts} token where
 * automation content should appear; the mailer renders the blocks and swaps the
 * token for the latest posts at send time.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the reusable Email Template post type.
 */
class Email_Template_Post_Type {
	const POST_TYPE = 'gb_email_template';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers the email template post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Email Templates', 'greenberry' ),
					'singular_name' => __( 'Email Template', 'greenberry' ),
					'add_new'       => __( 'Add Template', 'greenberry' ),
					'add_new_item'  => __( 'Add Email Template', 'greenberry' ),
					'edit_item'     => __( 'Edit Email Template', 'greenberry' ),
					'new_item'      => __( 'New Email Template', 'greenberry' ),
					'not_found'     => __( 'No templates yet.', 'greenberry' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'menu_icon'           => 'dashicons-layout',
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'template'            => array(
					array( 'core/heading', array( 'level' => 2, 'content' => __( 'Hello from {site_name}', 'greenberry' ) ) ),
					array( 'core/paragraph', array( 'content' => __( 'Here are our latest updates.', 'greenberry' ) ) ),
					array( 'core/paragraph', array( 'content' => '{posts}' ) ),
				),
			)
		);
	}

	/**
	 * Returns templates for an automation select.
	 *
	 * @return array<int,string> Template ID => title.
	 */
	public static function options() {
		$options = array();

		foreach ( get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'private' ),
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		) as $post ) {
			$options[ absint( $post->ID ) ] = '' !== $post->post_title ? $post->post_title : __( '(untitled template)', 'greenberry' );
		}

		return $options;
	}

	/**
	 * Renders a template's block content with tokens replaced.
	 *
	 * @param int                  $template_id Template post ID.
	 * @param array<string,string> $tokens      Token => HTML/text replacements.
	 * @return string|\WP_Error|null Rendered HTML, error when rendering fails, or null when the template is missing.
	 */
	public static function render_content( $template_id, $tokens ) {
		$post = get_post( absint( $template_id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		try {
			$html = do_blocks( $post->post_content );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'template_render_failed', __( 'The email template could not be rendered.', 'greenberry' ) );
		}

		// Replace a token sitting alone in its own paragraph without leaving an
		// empty <p> wrapper around block-level post markup.
		foreach ( $tokens as $token => $replacement ) {
			$html = preg_replace(
				'#<p[^>]*>\s*' . preg_quote( $token, '#' ) . '\s*</p>#',
				$replacement,
				$html
			);
		}

		return strtr( $html, $tokens );
	}
}
