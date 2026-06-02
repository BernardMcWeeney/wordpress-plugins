<?php
/**
 * Category Featured Image settings.
 *
 * @package Greenberry
 */

namespace Greenberry\CategoryFeaturedImage;

defined( 'ABSPATH' ) || exit;

/**
 * Stores featured image fallback rules.
 */
class Settings {
	const OPTION_NAME = 'greenberry_category_featured_image_settings';

	/**
	 * Ensures the settings option exists.
	 *
	 * @return void
	 */
	public function ensure_defaults() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		add_option( self::OPTION_NAME, $this->defaults(), '', false );
	}

	/**
	 * Gets normalized settings.
	 *
	 * @return array
	 */
	public function get() {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $this->defaults() );

		return array(
			'enabled'            => ! empty( $settings['enabled'] ),
			'global_image_id'    => $this->sanitize_image_id( isset( $settings['global_image_id'] ) ? $settings['global_image_id'] : 0 ),
			'post_type_defaults' => $this->sanitize_post_type_defaults( isset( $settings['post_type_defaults'] ) ? $settings['post_type_defaults'] : array() ),
			'term_defaults'      => $this->sanitize_term_defaults( isset( $settings['term_defaults'] ) ? $settings['term_defaults'] : array(), false ),
		);
	}

	/**
	 * Saves settings from admin form data.
	 *
	 * @param array $data Raw form data.
	 * @return void
	 */
	public function save( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$clean = array(
			'enabled'            => ! empty( $data['enabled'] ),
			'global_image_id'    => $this->sanitize_image_id( isset( $data['global_image_id'] ) ? $data['global_image_id'] : 0 ),
			'post_type_defaults' => $this->sanitize_post_type_defaults( isset( $data['post_type_defaults'] ) ? $data['post_type_defaults'] : array() ),
			'term_defaults'      => $this->sanitize_term_defaults( isset( $data['term_defaults'] ) ? $data['term_defaults'] : array(), true ),
		);

		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Gets the image ID that should be assigned to a post.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return int
	 */
	public function get_default_image_id_for_post( $post ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post );
		if ( ! $post ) {
			return 0;
		}

		$settings = $this->get();
		if ( empty( $settings['enabled'] ) ) {
			return 0;
		}

		$term_image_id = $this->get_term_default_image_id( $post, $settings );
		if ( $term_image_id ) {
			return $term_image_id;
		}

		$post_type = sanitize_key( $post->post_type );
		if ( ! empty( $settings['post_type_defaults'][ $post_type ] ) ) {
			return absint( $settings['post_type_defaults'][ $post_type ] );
		}

		return absint( $settings['global_image_id'] );
	}

	/**
	 * Gets public post types that can receive featured images.
	 *
	 * @return array<string,\WP_Post_Type>
	 */
	public function get_assignable_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'] );

		foreach ( array_keys( $post_types ) as $post_type ) {
			if ( ! post_type_supports( $post_type, 'thumbnail' ) ) {
				unset( $post_types[ $post_type ] );
			}
		}

		return $post_types;
	}

	/**
	 * Gets public taxonomies attached to assignable post types.
	 *
	 * @return array<string,\WP_Taxonomy>
	 */
	public function get_assignable_taxonomies() {
		$post_types = array_keys( $this->get_assignable_post_types() );
		if ( empty( $post_types ) ) {
			return array();
		}

		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		foreach ( $taxonomies as $taxonomy => $object ) {
			$object_types = isset( $object->object_type ) && is_array( $object->object_type ) ? $object->object_type : array();
			if ( empty( array_intersect( $post_types, $object_types ) ) ) {
				unset( $taxonomies[ $taxonomy ] );
			}
		}

		return $taxonomies;
	}

	/**
	 * Gets terms for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return array<int,\WP_Term>
	 */
	public function get_terms_for_taxonomy( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Gets default settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'enabled'            => true,
			'global_image_id'    => 0,
			'post_type_defaults' => array(),
			'term_defaults'      => array(),
		);
	}

	/**
	 * Finds the first matching taxonomy term default.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $settings Normalized settings.
	 * @return int
	 */
	private function get_term_default_image_id( \WP_Post $post, $settings ) {
		if ( empty( $settings['term_defaults'] ) || ! is_array( $settings['term_defaults'] ) ) {
			return 0;
		}

		foreach ( $settings['term_defaults'] as $taxonomy => $term_defaults ) {
			if ( ! is_array( $term_defaults ) || empty( $term_defaults ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
				continue;
			}

			$post_term_ids = wp_get_object_terms(
				$post->ID,
				$taxonomy,
				array(
					'fields' => 'ids',
				)
			);

			if ( is_wp_error( $post_term_ids ) || empty( $post_term_ids ) ) {
				continue;
			}

			$post_term_ids = array_map( 'absint', $post_term_ids );

			foreach ( $term_defaults as $term_id => $image_id ) {
				if ( in_array( absint( $term_id ), $post_term_ids, true ) ) {
					return absint( $image_id );
				}
			}
		}

		return 0;
	}

	/**
	 * Sanitizes post type image defaults.
	 *
	 * @param mixed $values Raw values.
	 * @return array<string,int>
	 */
	private function sanitize_post_type_defaults( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$clean      = array();
		$post_types = $this->get_assignable_post_types();

		foreach ( array_keys( $post_types ) as $post_type ) {
			$image_id = isset( $values[ $post_type ] ) ? $this->sanitize_image_id( $values[ $post_type ] ) : 0;
			if ( $image_id ) {
				$clean[ $post_type ] = $image_id;
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes taxonomy term image defaults.
	 *
	 * @param mixed $values         Raw values.
	 * @param bool  $validate_terms Whether term IDs should be checked against the database.
	 * @return array<string,array<int,int>>
	 */
	private function sanitize_term_defaults( $values, $validate_terms ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$clean      = array();
		$taxonomies = array_keys( $this->get_assignable_taxonomies() );

		foreach ( $values as $taxonomy => $term_values ) {
			$taxonomy = sanitize_key( $taxonomy );

			if ( ! in_array( $taxonomy, $taxonomies, true ) || ! is_array( $term_values ) ) {
				continue;
			}

			foreach ( $term_values as $term_id => $image_id ) {
				$term_id  = absint( $term_id );
				$image_id = $this->sanitize_image_id( $image_id );

				if ( ! $term_id || ! $image_id ) {
					continue;
				}

				if ( $validate_terms && ! term_exists( $term_id, $taxonomy ) ) {
					continue;
				}

				if ( ! isset( $clean[ $taxonomy ] ) ) {
					$clean[ $taxonomy ] = array();
				}

				$clean[ $taxonomy ][ $term_id ] = $image_id;
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes an attachment image ID.
	 *
	 * @param mixed $image_id Raw image ID.
	 * @return int
	 */
	private function sanitize_image_id( $image_id ) {
		$image_id = absint( $image_id );

		if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) {
			return 0;
		}

		return $image_id;
	}
}
