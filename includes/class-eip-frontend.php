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

		// Output CSS custom properties derived from the global settings.
		wp_add_inline_style( 'eip-modal', $this->build_css_vars() );

		wp_enqueue_script(
			'eip-exit-intent',
			EIP_PLUGIN_URL . 'assets/js/exit-intent.js',
			array(),
			EIP_VERSION,
			true
		);

		$settings = EIP_Settings::get();

		wp_localize_script(
			'eip-exit-intent',
			'eipConfig',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'eip/v1/event' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'pageId'      => get_the_ID(),
				'ga4Shown'    => sanitize_text_field( $settings['ga4_shown'] ),
				'ga4Closed'   => sanitize_text_field( $settings['ga4_closed'] ),
				'ga4CtaClick' => sanitize_text_field( $settings['ga4_cta_click'] ),
			)
		);
	}

	/**
	 * Build a :root { } CSS block containing custom properties from settings.
	 *
	 * @return string
	 */
	private function build_css_vars() {
		$s = EIP_Settings::get();

		$overlay_rgba = EIP_Settings::hex_to_rgba(
			$s['overlay_color'],
			$s['overlay_opacity']
		);

		$vars  = ':root{';
		$vars .= '--eip-light-bg:' . sanitize_hex_color( $s['light_bg_color'] ) . ';';
		$vars .= '--eip-light-color:' . sanitize_hex_color( $s['light_text_color'] ) . ';';
		$vars .= '--eip-dark-bg:' . sanitize_hex_color( $s['dark_bg_color'] ) . ';';
		$vars .= '--eip-dark-color:' . sanitize_hex_color( $s['dark_text_color'] ) . ';';
		$vars .= '--eip-overlay-bg:' . $overlay_rgba . ';';
		$vars .= '--eip-radius:' . absint( $s['border_radius'] ) . 'px;';
		$vars .= '--eip-size-small:' . absint( $s['size_small'] ) . 'px;';
		$vars .= '--eip-size-medium:' . absint( $s['size_medium'] ) . 'px;';
		$vars .= '--eip-size-large:' . absint( $s['size_large'] ) . 'px;';
		$vars .= '}';

		return $vars;
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
		$position_raw   = get_post_meta( $post_id, '_eip_position', true );
		$position       = in_array( $position_raw, array( 'center', 'top', 'bottom', 'left', 'right', 'cursor' ), true ) ? $position_raw : 'center';
		$size_raw       = get_post_meta( $post_id, '_eip_size', true );
		$size           = in_array( $size_raw, array( 'small', 'medium', 'large' ), true ) ? $size_raw : 'medium';
		$overlay_click  = (bool) get_post_meta( $post_id, '_eip_overlay_click', true );
		$theme_raw      = get_post_meta( $post_id, '_eip_theme', true );
		$theme          = in_array( $theme_raw, array( 'light', 'dark' ), true ) ? $theme_raw : 'light';

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
	 * Results are cached in the object cache for the duration of the request so
	 * the DB query runs at most once per page load (called from both
	 * enqueue_assets() and render_modals()).
	 *
	 * @return WP_Post[]
	 */
	public function get_assigned_popups() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array();
		}

		$cache_key = 'eip_assigned_popups_' . $post_id;
		$cached    = wp_cache_get( $cache_key, 'eip' );
		if ( false !== $cached ) {
			return $cached;
		}

		$assigned = get_post_meta( $post_id, '_eip_assigned_popups', true );
		if ( ! is_array( $assigned ) || empty( $assigned ) ) {
			wp_cache_set( $cache_key, array(), 'eip' );
			return array();
		}

		$assigned = array_values( array_filter( array_map( 'intval', $assigned ) ) );
		if ( empty( $assigned ) ) {
			wp_cache_set( $cache_key, array(), 'eip' );
			return array();
		}

		$popups = get_posts(
			array(
				'post_type'      => 'exit_intent_popup',
				'post_status'    => 'publish',
				'post__in'       => $assigned,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
			)
		);

		// Filter out popups outside their scheduled date range.
		$today  = wp_date( 'Y-m-d' );
		$popups = array_values(
			array_filter(
				$popups,
				function ( $popup ) use ( $today ) {
					$start = get_post_meta( $popup->ID, '_eip_start_date', true );
					$end   = get_post_meta( $popup->ID, '_eip_end_date', true );
					if ( $start && $today < $start ) {
						return false;
					}
					if ( $end && $today > $end ) {
						return false;
					}
					return true;
				}
			)
		);

		wp_cache_set( $cache_key, $popups, 'eip' );
		return $popups;
	}
}
