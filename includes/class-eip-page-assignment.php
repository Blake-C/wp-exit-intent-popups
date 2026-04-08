<?php
/**
 * Adds a multi-select meta box to pages and posts to assign exit intent popups.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Page_Assignment
 */
class EIP_Page_Assignment {

	/**
	 * Post types that receive the assignment meta box.
	 */
	const SUPPORTED_POST_TYPES = array( 'post', 'page' );

	/**
	 * Prevents the bulk popup selector from being rendered twice (top + bottom of list table).
	 *
	 * @var bool
	 */
	private static $bulk_selector_rendered = false;

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );

		// Bulk assignment/unassignment hooks.
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'register_bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_assign' ), 10, 3 );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_unassign' ), 10, 3 );
		}
		add_action( 'restrict_manage_posts', array( $this, 'render_bulk_popup_selector' ) );
		add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );
	}

	/**
	 * Register post meta for REST API / Gutenberg access.
	 */
	public function register_meta() {
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			register_post_meta(
				$post_type,
				'_eip_assigned_popups',
				array(
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
					'single'        => true,
					'type'          => 'array',
					'default'       => array(),
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Add the popup assignment meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'eip_page_assignment',
			__( 'Exit Intent Popups', 'wp-exit-intent-popups' ),
			array( $this, 'render_meta_box' ),
			self::SUPPORTED_POST_TYPES,
			'side',
			'high'
		);
	}

	/**
	 * Render the assignment meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'eip_page_assignment', 'eip_page_assignment_nonce' );

		$assigned = get_post_meta( $post->ID, '_eip_assigned_popups', true );
		if ( ! is_array( $assigned ) ) {
			$assigned = array();
		}
		$assigned = array_map( 'intval', $assigned );

		$popups = get_posts(
			array(
				'post_type'      => 'exit_intent_popup',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $popups ) ) {
			echo '<p>' . esc_html__( 'No exit intent popups created yet.', 'wp-exit-intent-popups' ) . '</p>';
			return;
		}

		$output  = '<p class="description">' . esc_html__( 'Assign one or more popups to this page. Multiple popups will be A/B tested.', 'wp-exit-intent-popups' ) . '</p>';
		$output .= '<select name="eip_assigned_popups[]" id="eip_assigned_popups" multiple style="width:100%;min-height:120px;">';

		foreach ( $popups as $popup ) {
			$is_selected = in_array( (int) $popup->ID, $assigned, true );
			$output     .= '<option value="' . esc_attr( $popup->ID ) . '"' . ( $is_selected ? ' selected' : '' ) . '>' . esc_html( $popup->post_title ) . '</option>';
		}

		$output .= '</select>';
		$output .= '<p class="description">' . esc_html__( 'Hold Ctrl/Cmd to select multiple.', 'wp-exit-intent-popups' ) . '</p>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
		echo $output;
	}

	/**
	 * Add the "Assign Exit Intent Popup" option to the bulk actions dropdown.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['eip_assign_popup']   = __( 'Assign Exit Intent Popup', 'wp-exit-intent-popups' );
		$bulk_actions['eip_unassign_popup'] = __( 'Unassign Exit Intent Popup', 'wp-exit-intent-popups' );
		return $bulk_actions;
	}

	/**
	 * Render the popup selector that appears alongside the bulk actions form.
	 * Uses a static flag so it only outputs once despite being called for both
	 * the top and bottom table nav areas.
	 *
	 * @param string $post_type Current list-table post type.
	 */
	public function render_bulk_popup_selector( $post_type ) {
		if ( self::$bulk_selector_rendered ) {
			return;
		}
		if ( ! in_array( $post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return;
		}

		$popups = get_posts(
			array(
				'post_type'      => 'exit_intent_popup',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $popups ) ) {
			return;
		}

		self::$bulk_selector_rendered = true;

		$output  = '<select name="eip_bulk_popup_id" id="eip-bulk-popup-selector" style="display:none;">';
		$output .= '<option value="">' . esc_html__( '— Select Popup —', 'wp-exit-intent-popups' ) . '</option>';
		foreach ( $popups as $popup ) {
			$output .= '<option value="' . esc_attr( $popup->ID ) . '">' . esc_html( $popup->post_title ) . '</option>';
		}
		$output .= '</select>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
		echo $output;
	}

	/**
	 * Process the bulk "Assign Exit Intent Popup" action.
	 *
	 * @param string $redirect_url Redirect URL after bulk action.
	 * @param string $action       Current bulk action slug.
	 * @param int[]  $post_ids     Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_assign( $redirect_url, $action, $post_ids ) {
		if ( 'eip_assign_popup' !== $action ) {
			return $redirect_url;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress verifies the bulk action nonce before firing handle_bulk_actions; no additional nonce required here.
		$popup_id = isset( $_REQUEST['eip_bulk_popup_id'] ) ? absint( $_REQUEST['eip_bulk_popup_id'] ) : 0;
		if ( ! $popup_id ) {
			return add_query_arg( 'eip_bulk_error', 'no_popup', $redirect_url );
		}

		$popup = get_post( $popup_id );
		if ( ! $popup || 'exit_intent_popup' !== $popup->post_type ) {
			return add_query_arg( 'eip_bulk_error', 'invalid_popup', $redirect_url );
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$assigned = get_post_meta( $post_id, '_eip_assigned_popups', true );
			if ( ! is_array( $assigned ) ) {
				$assigned = array();
			}
			$assigned = array_map( 'intval', $assigned );
			if ( ! in_array( $popup_id, $assigned, true ) ) {
				$assigned[] = $popup_id;
				update_post_meta( $post_id, '_eip_assigned_popups', array_values( $assigned ) );
				++$count;
			}
		}

		return add_query_arg( 'eip_bulk_assigned', $count, $redirect_url );
	}

	/**
	 * Process the bulk "Unassign Exit Intent Popup" action.
	 *
	 * Removes all popup assignments from the selected pages/posts.
	 *
	 * @param string $redirect_url Redirect URL after bulk action.
	 * @param string $action       Current bulk action slug.
	 * @param int[]  $post_ids     Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_unassign( $redirect_url, $action, $post_ids ) {
		if ( 'eip_unassign_popup' !== $action ) {
			return $redirect_url;
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_eip_assigned_popups', array() );
			++$count;
		}

		return add_query_arg( 'eip_bulk_unassigned', $count, $redirect_url );
	}

	/**
	 * Show an admin notice after a bulk assignment action.
	 */
	public function bulk_action_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query params set by our own add_query_arg() redirect, not user-controlled form input.
		if ( isset( $_GET['eip_bulk_assigned'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above.
			$count = absint( $_GET['eip_bulk_assigned'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of pages updated */
						_n(
							'Exit intent popup assigned to %d page.',
							'Exit intent popup assigned to %d pages.',
							$count,
							'wp-exit-intent-popups'
						),
						$count
					)
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query params set by our own add_query_arg() redirect, not user-controlled form input.
		if ( isset( $_GET['eip_bulk_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above.
			$error = sanitize_key( $_GET['eip_bulk_error'] );
			$msg   = 'no_popup' === $error
				? __( 'Please select a popup before applying the bulk action.', 'wp-exit-intent-popups' )
				: __( 'Invalid popup selected.', 'wp-exit-intent-popups' );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $msg )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query params set by our own add_query_arg() redirect, not user-controlled form input.
		if ( isset( $_GET['eip_bulk_unassigned'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above.
			$count = absint( $_GET['eip_bulk_unassigned'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of pages updated */
						_n(
							'All exit intent popups removed from %d page.',
							'All exit intent popups removed from %d pages.',
							$count,
							'wp-exit-intent-popups'
						),
						$count
					)
				)
			);
		}
	}

	/**
	 * Save the assigned popup IDs.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['eip_page_assignment_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['eip_page_assignment_nonce'] ), 'eip_page_assignment' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$assigned = array();
		if ( isset( $_POST['eip_assigned_popups'] ) && is_array( $_POST['eip_assigned_popups'] ) ) {
			$assigned = array_values( array_filter( array_map( 'absint', $_POST['eip_assigned_popups'] ) ) );
		}

		update_post_meta( $post_id, '_eip_assigned_popups', $assigned );
	}
}
