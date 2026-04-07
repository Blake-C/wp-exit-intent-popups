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
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
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
					'auth_callback' => function() {
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
