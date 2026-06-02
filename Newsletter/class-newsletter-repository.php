<?php
/**
 * Newsletter data access.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Stores contacts, tags, lists, campaigns, and automations.
 */
class Repository {
	/**
	 * Returns the table name for a newsletter entity.
	 *
	 * @param string $name Entity key.
	 * @return string
	 */
	public function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'greenberry_newsletter_' . $name;
	}

	/**
	 * Creates or updates newsletter tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$contacts        = $this->table( 'contacts' );
		$tags            = $this->table( 'tags' );
		$contact_tags    = $this->table( 'contact_tags' );
		$lists           = $this->table( 'lists' );
		$campaigns       = $this->table( 'campaigns' );
		$automations     = $this->table( 'automations' );

		$sql = array();

		$sql[] = "CREATE TABLE {$contacts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'subscribed',
			consent_source varchar(100) NOT NULL DEFAULT '',
			consent_text text NULL,
			consent_ip_hash varchar(128) NOT NULL DEFAULT '',
			consent_user_agent varchar(255) NOT NULL DEFAULT '',
			consent_at datetime NULL,
			unsubscribed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tags} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(120) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$contact_tags} (
			contact_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (contact_id, tag_id),
			KEY tag_id (tag_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$lists} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			slug varchar(160) NOT NULL,
			description text NULL,
			match_mode varchar(10) NOT NULL DEFAULT 'any',
			tag_slugs longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$campaigns} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			type varchar(30) NOT NULL DEFAULT 'manual',
			subject varchar(190) NOT NULL,
			preheader varchar(255) NOT NULL DEFAULT '',
			content longtext NULL,
			list_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'draft',
			settings longtext NULL,
			scheduled_at datetime NULL,
			sent_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY status (status),
			KEY list_id (list_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$automations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			trigger_type varchar(40) NOT NULL,
			cadence varchar(20) NOT NULL DEFAULT '',
			post_types longtext NULL,
			list_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subject varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			settings longtext NULL,
			last_sent_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY trigger_type (trigger_type),
			KEY status (status),
			KEY list_id (list_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'greenberry_newsletter_db_version', GREENBERRY_VERSION );
	}

	/**
	 * Creates or updates a contact.
	 *
	 * @param string $email Email address.
	 * @param array  $data Contact data.
	 * @return int|\WP_Error
	 */
	public function upsert_contact( $email, $data = array() ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'greenberry' ) );
		}

		$now      = current_time( 'mysql' );
		$existing = $this->get_contact_by_email( $email );
		$payload  = array(
			'email'              => $email,
			'first_name'         => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
			'last_name'          => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
			'status'             => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'subscribed',
			'consent_source'     => isset( $data['consent_source'] ) ? sanitize_text_field( $data['consent_source'] ) : '',
			'consent_text'       => isset( $data['consent_text'] ) ? wp_kses_post( $data['consent_text'] ) : '',
			'consent_ip_hash'    => isset( $data['consent_ip'] ) ? hash( 'sha256', sanitize_text_field( $data['consent_ip'] ) . wp_salt( 'nonce' ) ) : '',
			'consent_user_agent' => isset( $data['consent_user_agent'] ) ? substr( sanitize_text_field( $data['consent_user_agent'] ), 0, 255 ) : '',
			'consent_at'         => isset( $data['consent_at'] ) ? sanitize_text_field( $data['consent_at'] ) : $now,
			'unsubscribed_at'    => null,
			'updated_at'         => $now,
		);

		$allowed_statuses = array( 'subscribed', 'pending', 'unsubscribed', 'bounced' );
		if ( ! in_array( $payload['status'], $allowed_statuses, true ) ) {
			$payload['status'] = 'subscribed';
		}

		if ( $existing ) {
			$contact_id = absint( $existing->id );

			if ( '' === $payload['first_name'] ) {
				unset( $payload['first_name'] );
			}
			if ( '' === $payload['last_name'] ) {
				unset( $payload['last_name'] );
			}
			if ( '' === $payload['consent_text'] ) {
				unset( $payload['consent_text'] );
			}
			if ( '' === $payload['consent_source'] ) {
				unset( $payload['consent_source'] );
			}
			if ( '' === $payload['consent_ip_hash'] ) {
				unset( $payload['consent_ip_hash'] );
			}
			if ( '' === $payload['consent_user_agent'] ) {
				unset( $payload['consent_user_agent'] );
			}

			$updated = $wpdb->update(
				$this->table( 'contacts' ),
				$payload,
				array( 'id' => $contact_id )
			);

			if ( false === $updated ) {
				return new \WP_Error( 'contact_update_failed', __( 'Could not update the contact.', 'greenberry' ) );
			}
		} else {
			$payload['created_at'] = $now;

			$inserted = $wpdb->insert( $this->table( 'contacts' ), $payload );
			if ( false === $inserted ) {
				return new \WP_Error( 'contact_insert_failed', __( 'Could not save the contact.', 'greenberry' ) );
			}

			$contact_id = absint( $wpdb->insert_id );
		}

		if ( ! empty( $data['tags'] ) ) {
			$this->add_tags_to_contact( $contact_id, $data['tags'] );
		}

		return $contact_id;
	}

	/**
	 * Gets a contact by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public function get_contact_by_email( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'contacts' ) . ' WHERE email = %s LIMIT 1',
				$email
			)
		);
	}

	/**
	 * Gets a contact by ID.
	 *
	 * @param int $contact_id Contact ID.
	 * @return object|null
	 */
	public function get_contact( $contact_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'contacts' ) . ' WHERE id = %d LIMIT 1',
				absint( $contact_id )
			)
		);
	}

	/**
	 * Updates a contact status.
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_contact_status( $contact_id, $status ) {
		global $wpdb;

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'subscribed', 'pending', 'unsubscribed', 'bounced' ), true ) ) {
			return false;
		}

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		if ( 'unsubscribed' === $status ) {
			$data['unsubscribed_at'] = current_time( 'mysql' );
		} elseif ( 'subscribed' === $status ) {
			$data['unsubscribed_at'] = null;
		}

		return false !== $wpdb->update(
			$this->table( 'contacts' ),
			$data,
			array( 'id' => absint( $contact_id ) )
		);
	}

	/**
	 * Updates an existing contact.
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $data Contact data.
	 * @return bool|\WP_Error
	 */
	public function update_contact( $contact_id, $data ) {
		global $wpdb;

		$contact_id = absint( $contact_id );
		$contact    = $this->get_contact( $contact_id );
		if ( ! $contact ) {
			return new \WP_Error( 'contact_not_found', __( 'That contact could not be found.', 'greenberry' ) );
		}

		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : $contact->email;
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'greenberry' ) );
		}

		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : $contact->status;
		if ( ! in_array( $status, array( 'subscribed', 'pending', 'unsubscribed', 'bounced' ), true ) ) {
			$status = 'subscribed';
		}

		$payload = array(
			'email'       => $email,
			'first_name'  => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
			'last_name'   => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
			'status'      => $status,
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( 'unsubscribed' === $status ) {
			$payload['unsubscribed_at'] = current_time( 'mysql' );
		} elseif ( 'subscribed' === $status ) {
			$payload['unsubscribed_at'] = null;
		}

		$updated = $wpdb->update(
			$this->table( 'contacts' ),
			$payload,
			array( 'id' => $contact_id )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'contact_update_failed', __( 'Could not update the contact.', 'greenberry' ) );
		}

		if ( array_key_exists( 'tags', $data ) ) {
			$this->set_contact_tags( $contact_id, $data['tags'] );
		}

		return true;
	}

	/**
	 * Deletes a contact and its tag relationships.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool
	 */
	public function delete_contact( $contact_id ) {
		global $wpdb;

		$contact_id = absint( $contact_id );
		if ( ! $contact_id ) {
			return false;
		}

		$wpdb->delete( $this->table( 'contact_tags' ), array( 'contact_id' => $contact_id ) );

		return false !== $wpdb->delete( $this->table( 'contacts' ), array( 'id' => $contact_id ) );
	}

	/**
	 * Replaces all tags on a contact.
	 *
	 * @param int          $contact_id Contact ID.
	 * @param string|array $tags Tags.
	 * @return void
	 */
	public function set_contact_tags( $contact_id, $tags ) {
		global $wpdb;

		$contact_id = absint( $contact_id );
		if ( ! $contact_id ) {
			return;
		}

		$wpdb->delete( $this->table( 'contact_tags' ), array( 'contact_id' => $contact_id ) );
		$this->add_tags_to_contact( $contact_id, $tags );
	}

	/**
	 * Returns contacts for admin display.
	 *
	 * @param array $args Query args.
	 * @return array<int,object>
	 */
	public function get_contacts( $args = array() ) {
		global $wpdb;

		$contacts = $this->table( 'contacts' );
		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
			'status' => '',
		);
		$args     = wp_parse_args( $args, $defaults );
		$where    = 'WHERE 1=1';
		$values   = array();

		if ( '' !== $args['status'] ) {
			$where    .= ' AND status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$contacts} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$values
			)
		);
	}

	/**
	 * Returns all contacts for export.
	 *
	 * @return array<int,object>
	 */
	public function get_all_contacts_for_export() {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . $this->table( 'contacts' ) . ' ORDER BY created_at DESC' );
	}

	/**
	 * Counts contacts.
	 *
	 * @return int
	 */
	public function count_contacts() {
		global $wpdb;

		return absint( $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'contacts' ) ) );
	}

	/**
	 * Normalizes tag input.
	 *
	 * @param string|array $tags Tags.
	 * @return array<int,array{name:string,slug:string}>
	 */
	public function normalize_tags( $tags ) {
		if ( is_string( $tags ) ) {
			$tags = preg_split( '/[,;]+/', $tags );
		}

		if ( ! is_array( $tags ) ) {
			return array();
		}

		$clean = array();
		foreach ( $tags as $tag ) {
			$name = sanitize_text_field( $tag );
			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}

			$slug = sanitize_title( $name );
			if ( '' === $slug ) {
				continue;
			}

			$clean[ $slug ] = array(
				'name' => $name,
				'slug' => $slug,
			);
		}

		return array_values( $clean );
	}

	/**
	 * Ensures tags exist and returns their IDs.
	 *
	 * @param string|array $tags Tags.
	 * @return array<int,int>
	 */
	public function ensure_tags( $tags ) {
		global $wpdb;

		$now = current_time( 'mysql' );
		$ids = array();

		foreach ( $this->normalize_tags( $tags ) as $tag ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . $this->table( 'tags' ) . ' WHERE slug = %s LIMIT 1',
					$tag['slug']
				)
			);

			if ( $existing_id ) {
				$ids[] = absint( $existing_id );
				continue;
			}

			$wpdb->insert(
				$this->table( 'tags' ),
				array(
					'name'       => $tag['name'],
					'slug'       => $tag['slug'],
					'created_at' => $now,
				)
			);

			if ( $wpdb->insert_id ) {
				$ids[] = absint( $wpdb->insert_id );
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Adds tags to a contact.
	 *
	 * @param int          $contact_id Contact ID.
	 * @param string|array $tags Tags.
	 * @return void
	 */
	public function add_tags_to_contact( $contact_id, $tags ) {
		global $wpdb;

		$contact_id = absint( $contact_id );
		if ( ! $contact_id ) {
			return;
		}

		$now = current_time( 'mysql' );

		foreach ( $this->ensure_tags( $tags ) as $tag_id ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . $this->table( 'contact_tags' ) . ' (contact_id, tag_id, created_at) VALUES (%d, %d, %s)',
					$contact_id,
					$tag_id,
					$now
				)
			);
		}
	}

	/**
	 * Returns tags for a contact.
	 *
	 * @param int $contact_id Contact ID.
	 * @return array<int,string>
	 */
	public function get_contact_tags( $contact_id ) {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT t.name FROM ' . $this->table( 'tags' ) . ' t INNER JOIN ' . $this->table( 'contact_tags' ) . ' ct ON ct.tag_id = t.id WHERE ct.contact_id = %d ORDER BY t.name ASC',
				absint( $contact_id )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns tags grouped by contact ID.
	 *
	 * @param array<int,int> $contact_ids Contact IDs.
	 * @return array<int,array<int,string>>
	 */
	public function get_contact_tags_map( $contact_ids ) {
		global $wpdb;

		$contact_ids = array_values( array_filter( array_map( 'absint', (array) $contact_ids ) ) );
		if ( empty( $contact_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $contact_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ct.contact_id, t.name FROM ' . $this->table( 'contact_tags' ) . ' ct INNER JOIN ' . $this->table( 'tags' ) . " t ON t.id = ct.tag_id WHERE ct.contact_id IN ({$placeholders}) ORDER BY t.name ASC",
				$contact_ids
			)
		);

		$map = array_fill_keys( $contact_ids, array() );
		foreach ( (array) $rows as $row ) {
			$contact_id = absint( $row->contact_id );
			if ( ! isset( $map[ $contact_id ] ) ) {
				$map[ $contact_id ] = array();
			}
			$map[ $contact_id ][] = $row->name;
		}

		return $map;
	}

	/**
	 * Creates a tag-powered list.
	 *
	 * @param array $data List data.
	 * @return int|\WP_Error
	 */
	public function create_list( $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'missing_list_name', __( 'List name is required.', 'greenberry' ) );
		}

		$raw_tags   = isset( $data['tags'] ) ? $data['tags'] : array();
		$tags       = $this->normalize_tags( $raw_tags );
		$tag_slugs  = wp_list_pluck( $tags, 'slug' );
		$match_mode = isset( $data['match_mode'] ) && 'all' === $data['match_mode'] ? 'all' : 'any';
		$now        = current_time( 'mysql' );
		$slug       = sanitize_title( $name );

		$inserted = $wpdb->insert(
			$this->table( 'lists' ),
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'match_mode'  => $match_mode,
				'tag_slugs'   => wp_json_encode( array_values( $tag_slugs ) ),
				'status'      => 'active',
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'list_insert_failed', __( 'Could not create the list. The name may already exist.', 'greenberry' ) );
		}

		$this->ensure_tags( $raw_tags );

		return absint( $wpdb->insert_id );
	}

	/**
	 * Updates a list.
	 *
	 * @param int   $list_id List ID.
	 * @param array $data List data.
	 * @return bool|\WP_Error
	 */
	public function update_list( $list_id, $data ) {
		global $wpdb;

		$list_id = absint( $list_id );
		$list    = $this->get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'list_not_found', __( 'That list could not be found.', 'greenberry' ) );
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'missing_list_name', __( 'List name is required.', 'greenberry' ) );
		}

		$raw_tags   = isset( $data['tags'] ) ? $data['tags'] : array();
		$tags       = $this->normalize_tags( $raw_tags );
		$tag_slugs  = wp_list_pluck( $tags, 'slug' );
		$match_mode = isset( $data['match_mode'] ) && 'all' === $data['match_mode'] ? 'all' : 'any';

		$updated = $wpdb->update(
			$this->table( 'lists' ),
			array(
				'name'        => $name,
				'slug'        => sanitize_title( $name ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'match_mode'  => $match_mode,
				'tag_slugs'   => wp_json_encode( array_values( $tag_slugs ) ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $list_id )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'list_update_failed', __( 'Could not update the list. The name may already exist.', 'greenberry' ) );
		}

		$this->ensure_tags( $raw_tags );

		return true;
	}

	/**
	 * Deletes a list.
	 *
	 * @param int $list_id List ID.
	 * @return bool
	 */
	public function delete_list( $list_id ) {
		global $wpdb;

		$list_id = absint( $list_id );
		if ( ! $list_id ) {
			return false;
		}

		return false !== $wpdb->delete( $this->table( 'lists' ), array( 'id' => $list_id ) );
	}

	/**
	 * Returns active lists.
	 *
	 * @return array<int,object>
	 */
	public function get_lists() {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . $this->table( 'lists' ) . " WHERE status = 'active' ORDER BY name ASC" );
	}

	/**
	 * Gets a list by ID.
	 *
	 * @param int $list_id List ID.
	 * @return object|null
	 */
	public function get_list( $list_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'lists' ) . ' WHERE id = %d LIMIT 1',
				absint( $list_id )
			)
		);
	}

	/**
	 * Decodes list tag slugs.
	 *
	 * @param object $list List object.
	 * @return array<int,string>
	 */
	public function get_list_tag_slugs( $list ) {
		$decoded = json_decode( (string) $list->tag_slugs, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_title', $decoded ) ) );
	}

	/**
	 * Gets subscribed contacts for a list.
	 *
	 * @param int $list_id List ID. Zero means all subscribed contacts.
	 * @param int $limit Max contacts.
	 * @return array<int,object>
	 */
	public function get_contacts_for_list( $list_id, $limit = 500 ) {
		global $wpdb;

		$list_id  = absint( $list_id );
		$contacts = $this->table( 'contacts' );

		if ( ! $list_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$contacts} WHERE status = 'subscribed' ORDER BY created_at DESC LIMIT %d",
					absint( $limit )
				)
			);
		}

		$list = $this->get_list( $list_id );
		if ( ! $list ) {
			return array();
		}

		$tag_slugs = $this->get_list_tag_slugs( $list );
		$contact_tags = $this->table( 'contact_tags' );
		$tags         = $this->table( 'tags' );

		if ( empty( $tag_slugs ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$contacts} WHERE status = 'subscribed' ORDER BY created_at DESC LIMIT %d",
					absint( $limit )
				)
			);
		}

		$placeholders = implode( ', ', array_fill( 0, count( $tag_slugs ), '%s' ) );
		$values       = $tag_slugs;
		$values[]     = absint( $limit );

		if ( 'all' === $list->match_mode ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.* FROM {$contacts} c
					INNER JOIN (
						SELECT ct.contact_id
						FROM {$contact_tags} ct
						INNER JOIN {$tags} t ON t.id = ct.tag_id
						WHERE t.slug IN ({$placeholders})
						GROUP BY ct.contact_id
						HAVING COUNT(DISTINCT t.slug) = %d
					) matched ON matched.contact_id = c.id
					WHERE c.status = 'subscribed'
					ORDER BY c.created_at DESC
					LIMIT %d",
					array_merge( $tag_slugs, array( count( $tag_slugs ), absint( $limit ) ) )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT c.* FROM {$contacts} c
				INNER JOIN {$contact_tags} ct ON ct.contact_id = c.id
				INNER JOIN {$tags} t ON t.id = ct.tag_id
				WHERE c.status = 'subscribed' AND t.slug IN ({$placeholders})
				ORDER BY c.created_at DESC
				LIMIT %d",
				$values
			)
		);
	}

	/**
	 * Counts subscribed contacts for a list.
	 *
	 * @param int $list_id List ID.
	 * @return int
	 */
	public function count_contacts_for_list( $list_id ) {
		global $wpdb;

		$list_id  = absint( $list_id );
		$contacts = $this->table( 'contacts' );

		if ( ! $list_id ) {
			return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts} WHERE status = 'subscribed'" ) );
		}

		$list = $this->get_list( $list_id );
		if ( ! $list ) {
			return 0;
		}

		$tag_slugs    = $this->get_list_tag_slugs( $list );
		$contact_tags = $this->table( 'contact_tags' );
		$tags         = $this->table( 'tags' );

		if ( empty( $tag_slugs ) ) {
			return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts} WHERE status = 'subscribed'" ) );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $tag_slugs ), '%s' ) );

		if ( 'all' === $list->match_mode ) {
			return absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM (
							SELECT c.id
							FROM {$contacts} c
							INNER JOIN {$contact_tags} ct ON ct.contact_id = c.id
							INNER JOIN {$tags} t ON t.id = ct.tag_id
							WHERE c.status = 'subscribed' AND t.slug IN ({$placeholders})
							GROUP BY c.id
							HAVING COUNT(DISTINCT t.slug) = %d
						) matched",
						array_merge( $tag_slugs, array( count( $tag_slugs ) ) )
					)
				)
			);
		}

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT c.id)
					FROM {$contacts} c
					INNER JOIN {$contact_tags} ct ON ct.contact_id = c.id
					INNER JOIN {$tags} t ON t.id = ct.tag_id
					WHERE c.status = 'subscribed' AND t.slug IN ({$placeholders})",
					$tag_slugs
				)
			)
		);
	}

	/**
	 * Creates a campaign.
	 *
	 * @param array $data Campaign data.
	 * @return int|\WP_Error
	 */
	public function create_campaign( $data ) {
		global $wpdb;

		$name    = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$subject = isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '';

		if ( '' === $name || '' === $subject ) {
			return new \WP_Error( 'missing_campaign_fields', __( 'Campaign name and subject are required.', 'greenberry' ) );
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->table( 'campaigns' ),
			array(
				'name'       => $name,
				'type'       => isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'manual',
				'subject'    => $subject,
				'preheader'  => isset( $data['preheader'] ) ? sanitize_text_field( $data['preheader'] ) : '',
				'content'    => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
				'list_id'    => isset( $data['list_id'] ) ? absint( $data['list_id'] ) : 0,
				'status'     => 'draft',
				'settings'   => wp_json_encode( isset( $data['settings'] ) ? $data['settings'] : array() ),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'campaign_insert_failed', __( 'Could not create the campaign.', 'greenberry' ) );
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Returns campaigns.
	 *
	 * @return array<int,object>
	 */
	public function get_campaigns() {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . $this->table( 'campaigns' ) . ' ORDER BY created_at DESC LIMIT 50' );
	}

	/**
	 * Gets a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return object|null
	 */
	public function get_campaign( $campaign_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'campaigns' ) . ' WHERE id = %d LIMIT 1',
				absint( $campaign_id )
			)
		);
	}

	/**
	 * Marks a campaign as sent.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	public function mark_campaign_sent( $campaign_id ) {
		global $wpdb;

		$wpdb->update(
			$this->table( 'campaigns' ),
			array(
				'status'     => 'sent',
				'sent_at'    => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $campaign_id ) )
		);
	}

	/**
	 * Creates an automation.
	 *
	 * @param array $data Automation data.
	 * @return int|\WP_Error
	 */
	public function create_automation( $data ) {
		global $wpdb;

		$prepared = $this->prepare_automation_data( $data );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->table( 'automations' ),
			array(
				'name'         => $prepared['name'],
				'trigger_type' => $prepared['trigger_type'],
				'cadence'      => $prepared['cadence'],
				'post_types'   => wp_json_encode( $prepared['post_types'] ),
				'list_id'      => $prepared['list_id'],
				'subject'      => $prepared['subject'],
				'status'       => 'active',
				'settings'     => wp_json_encode( $prepared['settings'] ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'automation_insert_failed', __( 'Could not create the automation.', 'greenberry' ) );
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Gets an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @return object|null
	 */
	public function get_automation( $automation_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'automations' ) . ' WHERE id = %d LIMIT 1',
				absint( $automation_id )
			)
		);
	}

	/**
	 * Updates an automation.
	 *
	 * @param int   $automation_id Automation ID.
	 * @param array $data Automation data.
	 * @return bool|\WP_Error
	 */
	public function update_automation( $automation_id, $data ) {
		global $wpdb;

		$automation_id = absint( $automation_id );
		if ( ! $this->get_automation( $automation_id ) ) {
			return new \WP_Error( 'automation_not_found', __( 'That automation could not be found.', 'greenberry' ) );
		}

		$prepared = $this->prepare_automation_data( $data );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$updated = $wpdb->update(
			$this->table( 'automations' ),
			array(
				'name'         => $prepared['name'],
				'trigger_type' => $prepared['trigger_type'],
				'cadence'      => $prepared['cadence'],
				'post_types'   => wp_json_encode( $prepared['post_types'] ),
				'list_id'      => $prepared['list_id'],
				'subject'      => $prepared['subject'],
				'settings'     => wp_json_encode( $prepared['settings'] ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $automation_id )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'automation_update_failed', __( 'Could not update the automation.', 'greenberry' ) );
		}

		return true;
	}

	/**
	 * Deletes an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @return bool
	 */
	public function delete_automation( $automation_id ) {
		global $wpdb;

		$automation_id = absint( $automation_id );
		if ( ! $automation_id ) {
			return false;
		}

		return false !== $wpdb->delete( $this->table( 'automations' ), array( 'id' => $automation_id ) );
	}

	/**
	 * Returns automations.
	 *
	 * @param string $trigger Optional trigger filter.
	 * @return array<int,object>
	 */
	public function get_automations( $trigger = '' ) {
		global $wpdb;

		$automations = $this->table( 'automations' );

		if ( '' !== $trigger ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$automations} WHERE status = 'active' AND trigger_type = %s ORDER BY created_at DESC",
					sanitize_key( $trigger )
				)
			);
		}

		return $wpdb->get_results( "SELECT * FROM {$automations} WHERE status = 'active' ORDER BY created_at DESC LIMIT 50" );
	}

	/**
	 * Prepares automation data for insert/update.
	 *
	 * @param array $data Raw data.
	 * @return array|\WP_Error
	 */
	private function prepare_automation_data( $data ) {
		$name         = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$trigger_type = isset( $data['trigger_type'] ) ? sanitize_key( $data['trigger_type'] ) : '';
		$subject      = isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '';
		$post_types   = isset( $data['post_types'] ) ? $data['post_types'] : array( 'post' );

		if ( is_string( $post_types ) ) {
			$post_types = preg_split( '/[,;]+/', $post_types );
		}

		$post_types = array_values(
			array_filter(
				array_map(
					static function ( $post_type ) {
						return sanitize_key( trim( $post_type ) );
					},
					(array) $post_types
				)
			)
		);

		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		if ( '' === $name || '' === $trigger_type || '' === $subject ) {
			return new \WP_Error( 'missing_automation_fields', __( 'Automation name, trigger, and subject are required.', 'greenberry' ) );
		}

		$allowed_triggers = array( 'daily_digest', 'weekly_digest', 'post_publish' );
		if ( ! in_array( $trigger_type, $allowed_triggers, true ) ) {
			return new \WP_Error( 'invalid_automation_trigger', __( 'Invalid automation trigger.', 'greenberry' ) );
		}

		return array(
			'name'         => $name,
			'trigger_type' => $trigger_type,
			'cadence'      => str_replace( '_digest', '', $trigger_type ),
			'post_types'   => $post_types,
			'list_id'      => isset( $data['list_id'] ) ? absint( $data['list_id'] ) : 0,
			'subject'      => $subject,
			'settings'     => isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array(),
		);
	}

	/**
	 * Updates last sent date for an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @return void
	 */
	public function mark_automation_sent( $automation_id ) {
		global $wpdb;

		$wpdb->update(
			$this->table( 'automations' ),
			array(
				'last_sent_at' => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => absint( $automation_id ) )
		);
	}

	/**
	 * Gets the email template ID configured for an automation.
	 *
	 * @param object $automation Automation object.
	 * @return int
	 */
	public function get_automation_template_id( $automation ) {
		$settings = json_decode( (string) $automation->settings, true );

		return is_array( $settings ) && isset( $settings['template_id'] ) ? absint( $settings['template_id'] ) : 0;
	}

	/**
	 * Decodes automation post types.
	 *
	 * @param object $automation Automation object.
	 * @return array<int,string>
	 */
	public function get_automation_post_types( $automation ) {
		$post_types = json_decode( (string) $automation->post_types, true );
		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			return array( 'post' );
		}

		return array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
	}
}
