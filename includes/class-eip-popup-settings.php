<?php
/**
 * Registers and handles the popup settings meta box on the exit_intent_popup CPT.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Popup_Settings
 */
class EIP_Popup_Settings {

	/**
	 * Meta field definitions: key => type.
	 */
	const META_FIELDS = array(
		'_eip_popup_delay'    => 'integer',
		'_eip_auto_appear'    => 'integer',
		'_eip_frequency'      => 'string',
		'_eip_frequency_days' => 'integer',
		'_eip_position'       => 'string',
		'_eip_size'           => 'string',
		'_eip_overlay_click'  => 'boolean',
		'_eip_theme'          => 'string',
	);

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_exit_intent_popup', array( $this, 'save_meta' ) );
	}

	/**
	 * Register post meta for REST API / Gutenberg access.
	 */
	public function register_meta() {
		foreach ( self::META_FIELDS as $key => $type ) {
			$default = 'boolean' === $type ? false : ( 'integer' === $type ? 0 : '' );
			register_post_meta(
				'exit_intent_popup',
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $type,
					'default'       => $default,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Register the sidebar meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'eip_popup_settings',
			__( 'Popup Settings', 'wp-exit-intent-popups' ),
			array( $this, 'render_meta_box' ),
			'exit_intent_popup',
			'side',
			'high'
		);
	}

	/**
	 * Render the settings meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'eip_popup_settings', 'eip_popup_settings_nonce' );

		$delay          = (int) get_post_meta( $post->ID, '_eip_popup_delay', true );
		$auto_appear    = (int) get_post_meta( $post->ID, '_eip_auto_appear', true );
		$frequency      = get_post_meta( $post->ID, '_eip_frequency', true );
		$frequency      = $frequency ? $frequency : 'session';
		$frequency_days = (int) get_post_meta( $post->ID, '_eip_frequency_days', true );
		$frequency_days = $frequency_days ? $frequency_days : 7;
		$position       = get_post_meta( $post->ID, '_eip_position', true );
		$position       = $position ? $position : 'center';
		$size           = get_post_meta( $post->ID, '_eip_size', true );
		$size           = $size ? $size : 'medium';
		$overlay_click  = (bool) get_post_meta( $post->ID, '_eip_overlay_click', true );
		$theme          = get_post_meta( $post->ID, '_eip_theme', true );
		$theme          = $theme ? $theme : 'light';

		$output = '<div class="eip-meta-fields">';

		// Popup Delay.
		$output .= '<div class="eip-field">';
		$output .= '<label for="eip_popup_delay">' . esc_html__( 'Popup Delay (seconds)', 'wp-exit-intent-popups' ) . '</label>';
		$output .= '<input type="number" id="eip_popup_delay" name="eip_popup_delay" value="' . esc_attr( $delay ) . '" min="0" step="1" />';
		$output .= '<p class="description">' . esc_html__( 'Seconds after page load before exit intent is allowed to trigger. 0 = no delay.', 'wp-exit-intent-popups' ) . '</p>';
		$output .= '</div>';

		// Auto Appear.
		$output .= '<div class="eip-field">';
		$output .= '<label for="eip_auto_appear">' . esc_html__( 'Auto Appear (seconds)', 'wp-exit-intent-popups' ) . '</label>';
		$output .= '<input type="number" id="eip_auto_appear" name="eip_auto_appear" value="' . esc_attr( $auto_appear ) . '" min="0" step="1" />';
		$output .= '<p class="description">' . esc_html__( 'Automatically display popup after N seconds. Set to 0 to disable.', 'wp-exit-intent-popups' ) . '</p>';
		$output .= '</div>';

		// Frequency.
		$frequency_options = array(
			'always'  => __( 'Always', 'wp-exit-intent-popups' ),
			'session' => __( 'Session', 'wp-exit-intent-popups' ),
			'time'    => __( 'Time', 'wp-exit-intent-popups' ),
		);
		$output           .= '<div class="eip-field">';
		$output           .= '<label for="eip_frequency">' . esc_html__( 'Frequency', 'wp-exit-intent-popups' ) . '</label>';
		$output           .= '<select id="eip_frequency" name="eip_frequency">';
		foreach ( $frequency_options as $value => $label ) {
			$output .= '<option value="' . esc_attr( $value ) . '"' . selected( $frequency, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		// Frequency Days (shown when frequency = time).
		$days_style = 'time' === $frequency ? '' : 'display:none;';
		$output    .= '<div class="eip-field eip-field--frequency-days" style="' . esc_attr( $days_style ) . '">';
		$output    .= '<label for="eip_frequency_days">' . esc_html__( 'Show again after (days)', 'wp-exit-intent-popups' ) . '</label>';
		$output    .= '<input type="number" id="eip_frequency_days" name="eip_frequency_days" value="' . esc_attr( $frequency_days ) . '" min="1" step="1" />';
		$output    .= '</div>';

		// Position.
		$position_options = array(
			'top'    => __( 'Top', 'wp-exit-intent-popups' ),
			'bottom' => __( 'Bottom', 'wp-exit-intent-popups' ),
			'right'  => __( 'Right', 'wp-exit-intent-popups' ),
			'left'   => __( 'Left', 'wp-exit-intent-popups' ),
			'center' => __( 'Center', 'wp-exit-intent-popups' ),
			'cursor' => __( 'Mouse Exit Position', 'wp-exit-intent-popups' ),
		);
		$output          .= '<div class="eip-field">';
		$output          .= '<label for="eip_position">' . esc_html__( 'Popup Position', 'wp-exit-intent-popups' ) . '</label>';
		$output          .= '<select id="eip_position" name="eip_position">';
		foreach ( $position_options as $value => $label ) {
			$output .= '<option value="' . esc_attr( $value ) . '"' . selected( $position, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		// Size.
		$size_options = array(
			'small'  => __( 'Small', 'wp-exit-intent-popups' ),
			'medium' => __( 'Medium', 'wp-exit-intent-popups' ),
			'large'  => __( 'Large', 'wp-exit-intent-popups' ),
		);
		$output      .= '<div class="eip-field">';
		$output      .= '<label for="eip_size">' . esc_html__( 'Size', 'wp-exit-intent-popups' ) . '</label>';
		$output      .= '<select id="eip_size" name="eip_size">';
		foreach ( $size_options as $value => $label ) {
			$output .= '<option value="' . esc_attr( $value ) . '"' . selected( $size, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		// Allow Overlay Click.
		$output .= '<div class="eip-field eip-field--checkbox">';
		$output .= '<label>';
		$output .= '<input type="checkbox" name="eip_overlay_click" value="1"' . checked( $overlay_click, true, false ) . ' />';
		$output .= ' ' . esc_html__( 'Allow overlay click to close', 'wp-exit-intent-popups' );
		$output .= '</label>';
		$output .= '</div>';

		// Theme.
		$theme_options = array(
			'light' => __( 'Light', 'wp-exit-intent-popups' ),
			'dark'  => __( 'Dark', 'wp-exit-intent-popups' ),
		);
		$output       .= '<div class="eip-field">';
		$output       .= '<label for="eip_theme">' . esc_html__( 'Theme', 'wp-exit-intent-popups' ) . '</label>';
		$output       .= '<select id="eip_theme" name="eip_theme">';
		foreach ( $theme_options as $value => $label ) {
			$output .= '<option value="' . esc_attr( $value ) . '"' . selected( $theme, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		$output .= '</div>'; // .eip-meta-fields

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
		echo $output;
	}

	/**
	 * Save meta box values.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['eip_popup_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['eip_popup_settings_nonce'] ), 'eip_popup_settings' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Integer fields.
		$int_fields = array(
			'_eip_popup_delay'    => 'eip_popup_delay',
			'_eip_auto_appear'    => 'eip_auto_appear',
			'_eip_frequency_days' => 'eip_frequency_days',
		);
		foreach ( $int_fields as $meta_key => $field_name ) {
			$value = isset( $_POST[ $field_name ] ) ? absint( $_POST[ $field_name ] ) : 0;
			update_post_meta( $post_id, $meta_key, $value );
		}

		// Frequency.
		$valid_frequencies = array( 'always', 'session', 'time' );
		$frequency         = isset( $_POST['eip_frequency'] ) ? sanitize_key( $_POST['eip_frequency'] ) : 'session';
		if ( ! in_array( $frequency, $valid_frequencies, true ) ) {
			$frequency = 'session';
		}
		update_post_meta( $post_id, '_eip_frequency', $frequency );

		// Position.
		$valid_positions = array( 'top', 'bottom', 'right', 'left', 'center', 'cursor' );
		$position        = isset( $_POST['eip_position'] ) ? sanitize_key( $_POST['eip_position'] ) : 'center';
		if ( ! in_array( $position, $valid_positions, true ) ) {
			$position = 'center';
		}
		update_post_meta( $post_id, '_eip_position', $position );

		// Size.
		$valid_sizes = array( 'small', 'medium', 'large' );
		$size        = isset( $_POST['eip_size'] ) ? sanitize_key( $_POST['eip_size'] ) : 'medium';
		if ( ! in_array( $size, $valid_sizes, true ) ) {
			$size = 'medium';
		}
		update_post_meta( $post_id, '_eip_size', $size );

		// Overlay click checkbox.
		$overlay_click = isset( $_POST['eip_overlay_click'] ) ? 1 : 0;
		update_post_meta( $post_id, '_eip_overlay_click', $overlay_click );

		// Theme.
		$valid_themes = array( 'light', 'dark' );
		$theme        = isset( $_POST['eip_theme'] ) ? sanitize_key( $_POST['eip_theme'] ) : 'light';
		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'light';
		}
		update_post_meta( $post_id, '_eip_theme', $theme );
	}
}
