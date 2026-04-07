<?php
/**
 * Handles frontend asset enqueuing and modal HTML output.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Frontend
 */
class EIP_Frontend {

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_modals' ) );
	}

	/**
	 * Enqueue frontend CSS and JS when popups are assigned.
	 */
	public function enqueue_assets() {
		$popups = $this->get_assigned_popups();
		if ( empty( $popups ) ) {
			return;
		}

		wp_enqueue_style(
			'eip-modal',
			EIP_PLUGIN_URL . 'assets/css/modal.css',
			array(),
			EIP_VERSION
		);

		wp_enqueue_script(
			'eip-exit-intent',
			EIP_PLUGIN_URL . 'assets/js/exit-intent.js',
			array(),
			EIP_VERSION,
			true
		);

		wp_localize_script(
			'eip-exit-intent',
			'eipConfig',
			array(
				'restUrl' => esc_url_raw( rest_url( 'eip/v1/event' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'pageId'  => get_the_ID(),
			)
		);
	}

	/**
	 * Output modal HTML in the footer for each assigned popup.
	 */
	public function render_modals() {
		$popups = $this->get_assigned_popups();
		if ( empty( $popups ) ) {
			return;
		}

		foreach ( $popups as $popup ) {
			$this->render_modal( $popup );
		}
	}

	/**
	 * Render a single modal wrapper with all data attributes.
	 *
	 * @param WP_Post $popup The popup post object.
	 */
	private function render_modal( $popup ) {
		$post_id        = $popup->ID;
		$delay          = (int) get_post_meta( $post_id, '_eip_popup_delay', true );
		$auto_appear    = (int) get_post_meta( $post_id, '_eip_auto_appear', true );
		$frequency      = get_post_meta( $post_id, '_eip_frequency', true );
		$frequency      = $frequency ? $frequency : 'session';
		$frequency_days = (int) get_post_meta( $post_id, '_eip_frequency_days', true );
		$frequency_days = $frequency_days ? $frequency_days : 7;
		$position       = get_post_meta( $post_id, '_eip_position', true );
		$position       = $position ? $position : 'center';
		$size           = get_post_meta( $post_id, '_eip_size', true );
		$size           = $size ? $size : 'medium';
		$overlay_click  = (bool) get_post_meta( $post_id, '_eip_overlay_click', true );
		$theme          = get_post_meta( $post_id, '_eip_theme', true );
		$theme          = $theme ? $theme : 'light';

		// Run content through the_content to render Gutenberg blocks.
		$content = apply_filters( 'the_content', $popup->post_content );

		$modal_classes  = 'eip-modal';
		$modal_classes .= ' eip-modal--' . $theme;
		$modal_classes .= ' eip-modal--' . $size;
		$modal_classes .= ' eip-modal--' . $position;

		$output  = '<div class="eip-modal-wrapper"';
		$output .= ' data-popup-id="' . esc_attr( $post_id ) . '"';
		$output .= ' data-delay="' . esc_attr( $delay ) . '"';
		$output .= ' data-auto-appear="' . esc_attr( $auto_appear ) . '"';
		$output .= ' data-frequency="' . esc_attr( $frequency ) . '"';
		$output .= ' data-frequency-days="' . esc_attr( $frequency_days ) . '"';
		$output .= ' data-position="' . esc_attr( $position ) . '"';
		$output .= ' data-size="' . esc_attr( $size ) . '"';
		$output .= ' data-overlay-click="' . esc_attr( $overlay_click ? '1' : '0' ) . '"';
		$output .= ' data-theme="' . esc_attr( $theme ) . '"';
		$output .= ' aria-hidden="true"';
		$output .= ' role="dialog"';
		$output .= ' aria-modal="true"';
		$output .= ' aria-labelledby="eip-modal-title-' . esc_attr( $post_id ) . '"';
		$output .= '>';
		$output .= '<div class="eip-overlay"></div>';
		$output .= '<div class="' . esc_attr( $modal_classes ) . '" tabindex="-1">';
		$output .= '<button class="eip-modal__close" aria-label="' . esc_attr__( 'Close popup', 'wp-exit-intent-popups' ) . '">';
		$output .= '<span aria-hidden="true">&times;</span>';
		$output .= '</button>';
		$output .= '<div id="eip-modal-title-' . esc_attr( $post_id ) . '" class="eip-modal__content">';
		$output .= $content; // Content processed through the_content filter.
		$output .= '</div>';
		$output .= '</div>'; // .eip-modal
		$output .= '</div>'; // .eip-modal-wrapper

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped; content via the_content filter
		echo $output;
	}

	/**
	 * Get published popup posts assigned to the current page/post.
	 *
	 * @return WP_Post[]
	 */
	public function get_assigned_popups() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array();
		}

		$assigned = get_post_meta( $post_id, '_eip_assigned_popups', true );
		if ( ! is_array( $assigned ) || empty( $assigned ) ) {
			return array();
		}

		$assigned = array_values( array_filter( array_map( 'intval', $assigned ) ) );
		if ( empty( $assigned ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'exit_intent_popup',
				'post_status'    => 'publish',
				'post__in'       => $assigned,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
			)
		);
	}
}
