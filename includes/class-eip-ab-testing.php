<?php
/**
 * A/B testing: custom events table, REST tracking endpoint, and results query.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_AB_Testing
 */
class EIP_AB_Testing {

	const TABLE_SUFFIX = 'eip_events';

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Create the events DB table on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_SUFFIX;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			popup_id bigint(20) UNSIGNED NOT NULL,
			page_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(20) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY popup_id (popup_id),
			KEY page_id (page_id),
			KEY event_type (event_type),
			KEY popup_page_event (popup_id, page_id, event_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'eip_db_version', EIP_DB_VERSION );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'eip/v1',
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_event' ),
				// Verify the WP REST nonce sent by wp_localize_script. This blocks bots
				// and scrapers that have not loaded the page (and therefore have no nonce),
				// while allowing both logged-in and anonymous front-end visitors who have.
				'permission_callback' => array( $this, 'verify_event_nonce' ),
				'args'                => array(
					'popup_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page_id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'event_type' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'impression', 'conversion', 'close' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'eip/v1',
			'/events',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'clear_events' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'eip/v1',
			'/results',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_results' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'page_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST callback: truncate the events table.
	 *
	 * Requires manage_options capability (enforced in permission_callback).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_events() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_SUFFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- TRUNCATE on plugin-owned table; no user input.
		$result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );

		if ( false === $result ) {
			return new WP_Error(
				'db_error',
				__( 'Could not clear event data.', 'wp-exit-intent-popups' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Permission callback: verify the WP REST nonce from the X-WP-Nonce header.
	 *
	 * Uses wp_create_nonce( 'wp_rest' ), which works for both logged-in and
	 * anonymous users (WordPress 4.7+). Bots that do not execute the page JS
	 * will not have a valid nonce and will receive a 403.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function verify_event_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'A valid nonce is required.', 'wp-exit-intent-popups' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * REST callback: record a single event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function track_event( $request ) {
		global $wpdb;

		$popup_id   = $request->get_param( 'popup_id' );
		$page_id    = $request->get_param( 'page_id' );
		$event_type = $request->get_param( 'event_type' );

		// Verify the popup exists and is the correct post type.
		$popup = get_post( $popup_id );
		if ( ! $popup || 'exit_intent_popup' !== $popup->post_type ) {
			return new WP_Error(
				'invalid_popup',
				__( 'Invalid popup ID.', 'wp-exit-intent-popups' ),
				array( 'status' => 400 )
			);
		}

		// Verify the page exists (page_id 0 is acceptable for non-singular contexts).
		if ( $page_id > 0 && ! get_post( $page_id ) ) {
			return new WP_Error(
				'invalid_page',
				__( 'Invalid page ID.', 'wp-exit-intent-popups' ),
				array( 'status' => 400 )
			);
		}

		$table_name = $wpdb->prefix . self::TABLE_SUFFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- event tracking insert into plugin-owned table.
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'popup_id'   => $popup_id,
				'page_id'    => $page_id,
				'event_type' => $event_type,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'db_error',
				__( 'Could not record event.', 'wp-exit-intent-popups' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * REST callback: return aggregated A/B results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_results( $request ) {
		$page_id = $request->get_param( 'page_id' );
		return rest_ensure_response( self::get_results_raw( $page_id ? (int) $page_id : 0 ) );
	}

	/**
	 * Query aggregated event counts, optionally filtered by page.
	 *
	 * @param int $page_id Optional page ID to filter by.
	 * @return array
	 */
	public static function get_results_raw( $page_id = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_SUFFIX;

		if ( $page_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate query on plugin-owned table; caching not appropriate for real-time A/B data.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted $wpdb->prefix.
					"SELECT popup_id, page_id, event_type, COUNT(*) AS count FROM {$table_name} WHERE page_id = %d GROUP BY popup_id, page_id, event_type ORDER BY popup_id, page_id, event_type",
					$page_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- aggregate query on plugin-owned table; no user input; caching not appropriate for real-time A/B data.
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted $wpdb->prefix.
				"SELECT popup_id, page_id, event_type, COUNT(*) AS count FROM {$table_name} GROUP BY popup_id, page_id, event_type ORDER BY popup_id, page_id, event_type"
			);
		}

		$data = array();

		// Deduplicate IDs and prime a local cache using get_post() — which has no
		// post_type/status restrictions — to avoid N+1 queries in the loop below.
		$popup_ids = array_unique( array_map( 'intval', wp_list_pluck( $rows, 'popup_id' ) ) );
		$page_ids  = array_unique( array_filter( array_map( 'intval', wp_list_pluck( $rows, 'page_id' ) ) ) );
		$all_ids   = array_unique( array_merge( $popup_ids, $page_ids ) );

		$posts_cache = array();
		foreach ( $all_ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$posts_cache[ $id ] = $post;
			}
		}

		foreach ( $rows as $row ) {
			$key = $row->popup_id . '_' . $row->page_id;

			if ( ! isset( $data[ $key ] ) ) {
				$popup        = $posts_cache[ (int) $row->popup_id ] ?? null;
				$page         = $posts_cache[ (int) $row->page_id ] ?? null;
				$data[ $key ] = array(
					'popup_id'    => (int) $row->popup_id,
					'popup_title' => $popup ? $popup->post_title : '',
					'page_id'     => (int) $row->page_id,
					'page_title'  => $page ? $page->post_title : '',
					'impressions' => 0,
					'conversions' => 0,
					'closes'      => 0,
					'rate'        => 0,
				);
			}

			$count = (int) $row->count;
			if ( 'impression' === $row->event_type ) {
				$data[ $key ]['impressions'] = $count;
			} elseif ( 'conversion' === $row->event_type ) {
				$data[ $key ]['conversions'] = $count;
			} elseif ( 'close' === $row->event_type ) {
				$data[ $key ]['closes'] = $count;
			}
		}

		foreach ( $data as &$item ) {
			if ( $item['impressions'] > 0 ) {
				$item['rate'] = round( ( $item['conversions'] / $item['impressions'] ) * 100, 2 );
			}
		}

		return array_values( $data );
	}
}
