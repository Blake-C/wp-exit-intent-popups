<?php
/**
 * Global plugin settings page and WP Settings API registration.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Settings
 */
class EIP_Settings {

	const OPTION_NAME = 'eip_settings';

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Default setting values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'light_bg_color'   => '#ffffff',
			'light_text_color' => '#333333',
			'dark_bg_color'    => '#1e1e1e',
			'dark_text_color'  => '#f0f0f0',
			'overlay_color'    => '#000000',
			'overlay_opacity'  => 60,
			'border_radius'    => 4,
			'size_small'       => 360,
			'size_medium'      => 560,
			'size_large'       => 820,
			'ga4_shown'        => 'eip_popup_shown',
			'ga4_closed'       => 'eip_popup_closed',
			'ga4_cta_click'    => 'eip_cta_click',
		);
	}

	/**
	 * Retrieve settings merged with defaults.
	 *
	 * @param string|null $key Optional key to return a single value.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$saved    = get_option( self::OPTION_NAME, array() );
		$settings = wp_parse_args( $saved, self::defaults() );

		if ( null !== $key ) {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}

		return $settings;
	}

	/**
	 * Convert a hex colour + opacity percentage into an rgba() string.
	 *
	 * @param string $hex     Hex colour including leading #.
	 * @param int    $opacity Opacity as a percentage 0–100.
	 * @return string
	 */
	public static function hex_to_rgba( $hex, $opacity ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$a = round( (int) $opacity / 100, 2 );

		return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $a . ')';
	}

	/**
	 * Add the Settings submenu page under the CPT.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=exit_intent_popup',
			__( 'Plugin Settings', 'wp-exit-intent-popups' ),
			__( 'Settings', 'wp-exit-intent-popups' ),
			'manage_options',
			'eip-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the WP colour picker on the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'exit_intent_popup_page_eip-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Register the settings, sections, and fields via the WP Settings API.
	 */
	public function register_settings() {
		register_setting(
			'eip_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Section: Colours.
		add_settings_section(
			'eip_section_colors',
			__( 'Colours', 'wp-exit-intent-popups' ),
			'__return_false',
			'eip-settings'
		);

		$color_fields = array(
			'light_bg_color'   => __( 'Light theme background', 'wp-exit-intent-popups' ),
			'light_text_color' => __( 'Light theme text', 'wp-exit-intent-popups' ),
			'dark_bg_color'    => __( 'Dark theme background', 'wp-exit-intent-popups' ),
			'dark_text_color'  => __( 'Dark theme text', 'wp-exit-intent-popups' ),
			'overlay_color'    => __( 'Overlay colour', 'wp-exit-intent-popups' ),
		);
		foreach ( $color_fields as $id => $label ) {
			add_settings_field(
				$id,
				$label,
				array( $this, 'render_color_field' ),
				'eip-settings',
				'eip_section_colors',
				array(
					'id'        => $id,
					'label_for' => $id,
				)
			);
		}

		add_settings_field(
			'overlay_opacity',
			__( 'Overlay opacity (%)', 'wp-exit-intent-popups' ),
			array( $this, 'render_number_field' ),
			'eip-settings',
			'eip_section_colors',
			array(
				'id'        => 'overlay_opacity',
				'label_for' => 'overlay_opacity',
				'min'       => 0,
				'max'       => 100,
				'desc'      => __( '0 = fully transparent, 100 = fully opaque.', 'wp-exit-intent-popups' ),
			)
		);

		// Section: Border radius.
		add_settings_section(
			'eip_section_shape',
			__( 'Shape', 'wp-exit-intent-popups' ),
			'__return_false',
			'eip-settings'
		);

		add_settings_field(
			'border_radius',
			__( 'Border radius (px)', 'wp-exit-intent-popups' ),
			array( $this, 'render_number_field' ),
			'eip-settings',
			'eip_section_shape',
			array(
				'id'        => 'border_radius',
				'label_for' => 'border_radius',
				'min'       => 0,
				'max'       => 100,
				'desc'      => __( 'Applied to all modal panels. 0 = square corners.', 'wp-exit-intent-popups' ),
			)
		);

		// Section: Sizes.
		add_settings_section(
			'eip_section_sizes',
			__( 'Modal Sizes', 'wp-exit-intent-popups' ),
			array( $this, 'render_sizes_intro' ),
			'eip-settings'
		);

		$size_fields = array(
			'size_small'  => __( 'Small width (px)', 'wp-exit-intent-popups' ),
			'size_medium' => __( 'Medium width (px)', 'wp-exit-intent-popups' ),
			'size_large'  => __( 'Large width (px)', 'wp-exit-intent-popups' ),
		);
		foreach ( $size_fields as $id => $label ) {
			add_settings_field(
				$id,
				$label,
				array( $this, 'render_number_field' ),
				'eip-settings',
				'eip_section_sizes',
				array(
					'id'        => $id,
					'label_for' => $id,
					'min'       => 100,
					'max'       => 2000,
				)
			);
		}

		// Section: GA4 event names.
		add_settings_section(
			'eip_section_ga4',
			__( 'Google Analytics 4 Event Names', 'wp-exit-intent-popups' ),
			array( $this, 'render_ga4_intro' ),
			'eip-settings'
		);

		$ga4_fields = array(
			'ga4_shown'     => __( 'Popup shown event', 'wp-exit-intent-popups' ),
			'ga4_closed'    => __( 'Popup closed event', 'wp-exit-intent-popups' ),
			'ga4_cta_click' => __( 'CTA click event', 'wp-exit-intent-popups' ),
		);
		foreach ( $ga4_fields as $id => $label ) {
			add_settings_field(
				$id,
				$label,
				array( $this, 'render_text_field' ),
				'eip-settings',
				'eip_section_ga4',
				array(
					'id'        => $id,
					'label_for' => $id,
					'desc'      => __( 'Letters, numbers, and underscores only.', 'wp-exit-intent-popups' ),
				)
			);
		}
	}

	/**
	 * Sanitize and validate the settings array on save.
	 *
	 * @param array $input Raw POST values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$out      = array();
		$defaults = self::defaults();

		// Hex colour fields.
		$hex_fields = array( 'light_bg_color', 'light_text_color', 'dark_bg_color', 'dark_text_color', 'overlay_color' );
		foreach ( $hex_fields as $field ) {
			$raw           = isset( $input[ $field ] ) ? sanitize_hex_color( $input[ $field ] ) : '';
			$out[ $field ] = $raw ? $raw : $defaults[ $field ];
		}

		// Integer fields.
		$int_fields = array(
			'overlay_opacity' => array( 0, 100 ),
			'border_radius'   => array( 0, 100 ),
			'size_small'      => array( 100, 2000 ),
			'size_medium'     => array( 100, 2000 ),
			'size_large'      => array( 100, 2000 ),
		);
		foreach ( $int_fields as $field => $range ) {
			$val           = isset( $input[ $field ] ) ? (int) $input[ $field ] : $defaults[ $field ];
			$out[ $field ] = max( $range[0], min( $range[1], $val ) );
		}

		// GA4 event name fields: letters, numbers, underscores only.
		$ga4_fields = array( 'ga4_shown', 'ga4_closed', 'ga4_cta_click' );
		foreach ( $ga4_fields as $field ) {
			$raw           = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
			$sanitized     = preg_replace( '/[^a-zA-Z0-9_]/', '_', $raw );
			$out[ $field ] = $sanitized ? $sanitized : $defaults[ $field ];
		}

		return $out;
	}

	/**
	 * Render a colour-picker input field.
	 *
	 * @param array $args Field arguments including 'id'.
	 */
	public function render_color_field( $args ) {
		$id    = $args['id'];
		$value = self::get( $id );

		$output  = '<input type="text" id="' . esc_attr( $id ) . '"';
		$output .= ' name="' . esc_attr( self::OPTION_NAME . '[' . $id . ']' ) . '"';
		$output .= ' value="' . esc_attr( $value ) . '"';
		$output .= ' class="eip-color-picker" data-default-color="' . esc_attr( self::defaults()[ $id ] ) . '" />';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		echo $output;
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments including 'id', 'min', 'max', optional 'desc'.
	 */
	public function render_number_field( $args ) {
		$id    = $args['id'];
		$value = self::get( $id );
		$min   = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max   = isset( $args['max'] ) ? (int) $args['max'] : 9999;
		$desc  = isset( $args['desc'] ) ? $args['desc'] : '';

		$output  = '<input type="number" id="' . esc_attr( $id ) . '"';
		$output .= ' name="' . esc_attr( self::OPTION_NAME . '[' . $id . ']' ) . '"';
		$output .= ' value="' . esc_attr( $value ) . '"';
		$output .= ' min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" style="width:80px;" />';
		if ( $desc ) {
			$output .= '<p class="description">' . esc_html( $desc ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		echo $output;
	}

	/**
	 * Render a plain text input field.
	 *
	 * @param array $args Field arguments including 'id', optional 'desc'.
	 */
	public function render_text_field( $args ) {
		$id    = $args['id'];
		$value = self::get( $id );
		$desc  = isset( $args['desc'] ) ? $args['desc'] : '';

		$output  = '<input type="text" id="' . esc_attr( $id ) . '"';
		$output .= ' name="' . esc_attr( self::OPTION_NAME . '[' . $id . ']' ) . '"';
		$output .= ' value="' . esc_attr( $value ) . '"';
		$output .= ' class="regular-text" />';
		if ( $desc ) {
			$output .= '<p class="description">' . esc_html( $desc ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		echo $output;
	}

	/**
	 * Intro text for the Sizes section.
	 */
	public function render_sizes_intro() {
		echo '<p class="description">' . esc_html__( 'Width applied when the popup position is Center or Mouse Exit Position. Top, Bottom, Left, and Right positions span the full edge and are not affected.', 'wp-exit-intent-popups' ) . '</p>';
	}

	/**
	 * Intro text for the GA4 section.
	 */
	public function render_ga4_intro() {
		echo '<p class="description">' . esc_html__( 'Customise the gtag() event names fired by the plugin. Changes take effect immediately once saved.', 'wp-exit-intent-popups' ) . '</p>';
	}

	/**
	 * Render the settings admin page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-exit-intent-popups' ) );
		}

		$output  = '<div class="wrap eip-settings-wrap">';
		$output .= '<h1 class="wp-heading-inline">' . esc_html__( 'Exit Intent Popup — Settings', 'wp-exit-intent-popups' ) . '</h1>';
		$output .= '<hr class="wp-header-end">';
		$output .= '<form method="post" action="options.php">';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP core functions
		echo $output;

		settings_fields( 'eip_settings_group' );
		do_settings_sections( 'eip-settings' );
		submit_button( __( 'Save Settings', 'wp-exit-intent-popups' ) );

		echo '</form></div>';

		// Inline script to initialise colour pickers.
		echo '<script>jQuery(function($){$(".eip-color-picker").wpColorPicker();});</script>';
	}
}
