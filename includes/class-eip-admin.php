<?php
/**
 * Admin menu and A/B test results page.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EIP_Admin
 */
class EIP_Admin {

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add a submenu page under the CPT.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=exit_intent_popup',
			__( 'A/B Test Results', 'wp-exit-intent-popups' ),
			__( 'A/B Results', 'wp-exit-intent-popups' ),
			'manage_options',
			'eip-ab-results',
			array( $this, 'render_results_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on CPT screens and the results page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_cpt_screen   = 'exit_intent_popup' === $screen->post_type;
		$is_results_page = 'exit_intent_popup_page_eip-ab-results' === $hook;

		if ( ! $is_cpt_screen && ! $is_results_page ) {
			return;
		}

		wp_enqueue_style(
			'eip-admin',
			EIP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			EIP_VERSION
		);

		wp_enqueue_script(
			'eip-admin',
			EIP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EIP_VERSION,
			true
		);

		wp_localize_script(
			'eip-admin',
			'eipAdmin',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'eip/v1/results' ) ),
				'deleteUrl'     => esc_url_raw( rest_url( 'eip/v1/events' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'confirmClear'  => __( 'Are you sure you want to delete all A/B test data? This cannot be undone.', 'wp-exit-intent-popups' ),
				'clearingLabel' => __( 'Clearing\u2026', 'wp-exit-intent-popups' ),
				'clearErrorMsg' => __( 'An error occurred. Please try again.', 'wp-exit-intent-popups' ),
			)
		);
	}

	/**
	 * Render the A/B test results admin page.
	 */
	public function render_results_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-exit-intent-popups' ) );
		}

		// Build a list of pages that have any recorded events for the filter dropdown.
		$all_results = EIP_AB_Testing::get_results_raw();

		$pages_with_data = array();
		foreach ( $all_results as $row ) {
			if ( $row['page_id'] && ! isset( $pages_with_data[ $row['page_id'] ] ) ) {
				$pages_with_data[ $row['page_id'] ] = $row['page_title'];
			}
		}

		$filter_page_id = 0;
		if ( isset( $_GET['filter_page_id'], $_GET['eip_filter_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_GET['eip_filter_nonce'] ), 'eip_ab_filter' ) ) {
			$filter_page_id = absint( $_GET['filter_page_id'] );
		}
		$results = $filter_page_id ? EIP_AB_Testing::get_results_raw( $filter_page_id ) : $all_results;

		$output  = '<div class="wrap eip-results-wrap">';
		$output .= '<h1 class="wp-heading-inline">' . esc_html__( 'A/B Test Results', 'wp-exit-intent-popups' ) . '</h1>';
		$output .= '<button id="eip-clear-data" class="page-title-action eip-clear-btn"' . ( empty( $all_results ) ? ' disabled' : '' ) . '>';
		$output .= esc_html__( 'Clear All Data', 'wp-exit-intent-popups' );
		$output .= '</button>';
		$output .= '<hr class="wp-header-end">';

		// Filter form.
		$output .= '<form method="get" class="eip-filter-form">';
		$output .= '<input type="hidden" name="post_type" value="exit_intent_popup" />';
		$output .= '<input type="hidden" name="page" value="eip-ab-results" />';
		$output .= wp_nonce_field( 'eip_ab_filter', 'eip_filter_nonce', true, false );
		$output .= '<label for="filter_page_id">' . esc_html__( 'Filter by Page / Post:', 'wp-exit-intent-popups' ) . '</label> ';
		$output .= '<select name="filter_page_id" id="filter_page_id">';
		$output .= '<option value="">' . esc_html__( '— All Pages —', 'wp-exit-intent-popups' ) . '</option>';
		foreach ( $pages_with_data as $pid => $title ) {
			$output .= '<option value="' . esc_attr( $pid ) . '"' . selected( $filter_page_id, $pid, false ) . '>' . esc_html( $title ) . '</option>';
		}
		$output .= '</select> ';
		$output .= '<button type="submit" class="button">' . esc_html__( 'Filter', 'wp-exit-intent-popups' ) . '</button>';
		if ( $filter_page_id ) {
			$reset_url = admin_url( 'edit.php?post_type=exit_intent_popup&page=eip-ab-results' );
			$output   .= ' <a href="' . esc_url( $reset_url ) . '" class="button button-secondary">' . esc_html__( 'Reset', 'wp-exit-intent-popups' ) . '</a>';
		}
		$output .= '</form>';

		if ( empty( $results ) ) {
			$output .= '<p>' . esc_html__( 'No results yet. Assign popups to pages and wait for visitors.', 'wp-exit-intent-popups' ) . '</p>';
		} else {
			$output .= '<table class="wp-list-table widefat fixed striped eip-results-table">';
			$output .= '<thead><tr>';
			$output .= '<th>' . esc_html__( 'Popup', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '<th>' . esc_html__( 'Page / Post', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '<th class="eip-num">' . esc_html__( 'Impressions', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '<th class="eip-num">' . esc_html__( 'Conversions', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '<th class="eip-num">' . esc_html__( 'Closes', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '<th class="eip-num">' . esc_html__( 'Conv. Rate', 'wp-exit-intent-popups' ) . '</th>';
			$output .= '</tr></thead><tbody>';

			foreach ( $results as $row ) {
				$edit_url   = get_edit_post_link( $row['popup_id'] );
				$page_url   = get_permalink( $row['page_id'] );
				$rate       = $row['rate'];
				$rate_class = $rate >= 10 ? ' eip-rate--good' : ( $rate >= 5 ? ' eip-rate--ok' : '' );

				$output .= '<tr>';
				$output .= '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html( $row['popup_title'] ) . '</a></td>';
				$output .= '<td><a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $row['page_title'] ) . '</a></td>';
				$output .= '<td class="eip-num">' . esc_html( number_format_i18n( $row['impressions'] ) ) . '</td>';
				$output .= '<td class="eip-num">' . esc_html( number_format_i18n( $row['conversions'] ) ) . '</td>';
				$output .= '<td class="eip-num">' . esc_html( number_format_i18n( $row['closes'] ) ) . '</td>';
				$output .= '<td class="eip-num eip-rate' . esc_attr( $rate_class ) . '">' . esc_html( $rate ) . '%</td>';
				$output .= '</tr>';
			}

			$output .= '</tbody></table>';
		}

		$output .= '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
		echo $output;
	}
}
