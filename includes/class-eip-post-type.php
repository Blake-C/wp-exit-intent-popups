<?php
/**
 * Registers the exit_intent_popup custom post type.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Post_Type
 */
class EIP_Post_Type {

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'post_row_actions', array( $this, 'add_clone_action' ), 10, 2 );
		add_action( 'admin_action_eip_clone_popup', array( $this, 'handle_clone' ) );
	}

	/**
	 * Static wrapper used during activation before 'init' fires.
	 */
	public static function register_post_type_static() {
		( new self() )->register_post_type();
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Exit Intent Popups', 'wp-exit-intent-popups' ),
			'singular_name'      => __( 'Exit Intent Popup', 'wp-exit-intent-popups' ),
			'add_new'            => __( 'Add New Popup', 'wp-exit-intent-popups' ),
			'add_new_item'       => __( 'Add New Exit Intent Popup', 'wp-exit-intent-popups' ),
			'edit_item'          => __( 'Edit Exit Intent Popup', 'wp-exit-intent-popups' ),
			'new_item'           => __( 'New Exit Intent Popup', 'wp-exit-intent-popups' ),
			'view_item'          => __( 'View Exit Intent Popup', 'wp-exit-intent-popups' ),
			'search_items'       => __( 'Search Exit Intent Popups', 'wp-exit-intent-popups' ),
			'not_found'          => __( 'No exit intent popups found.', 'wp-exit-intent-popups' ),
			'not_found_in_trash' => __( 'No exit intent popups found in trash.', 'wp-exit-intent-popups' ),
			'menu_name'          => __( 'Intent Popups', 'wp-exit-intent-popups' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-align-center',
			'supports'           => array( 'title', 'editor' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'exit_intent_popup', $args );
	}

	/**
	 * Add a Clone row action to the popup list table.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_clone_action( $actions, $post ) {
		if ( 'exit_intent_popup' !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$clone_url            = wp_nonce_url(
			admin_url( 'admin.php?action=eip_clone_popup&post=' . $post->ID ),
			'eip_clone_popup_' . $post->ID
		);
		$actions['eip_clone'] = '<a href="' . esc_url( $clone_url ) . '">' . esc_html__( 'Clone', 'wp-exit-intent-popups' ) . '</a>';

		return $actions;
	}

	/**
	 * Handle the clone action: duplicate the popup post and its settings meta.
	 */
	public function handle_clone() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid popup ID.', 'wp-exit-intent-popups' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eip_clone_popup_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-exit-intent-popups' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'exit_intent_popup' !== $post->post_type ) {
			wp_die( esc_html__( 'Invalid popup.', 'wp-exit-intent-popups' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to clone this popup.', 'wp-exit-intent-popups' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $post->post_title . ' ' . __( '(Copy)', 'wp-exit-intent-popups' ),
				'post_content' => $post->post_content,
				'post_status'  => 'draft',
				'post_type'    => 'exit_intent_popup',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		// Copy only plugin-owned meta keys (all prefixed with _eip_).
		$meta = get_post_meta( $post_id );
		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, '_eip_' ) ) {
				foreach ( $values as $value ) {
					update_post_meta( $new_id, $key, maybe_unserialize( $value ) );
				}
			}
		}

		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}
}
