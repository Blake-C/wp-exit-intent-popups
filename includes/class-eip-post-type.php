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
}
