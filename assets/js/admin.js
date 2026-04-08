/**
 * Exit Intent Popup - Admin
 *
 * Handles:
 *  - Frequency days field toggle in the CPT sidebar.
 *  - WP colour picker initialisation on the Settings page.
 *  - Clear All Data button on the A/B Results page.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.eipAdmin || {};

	/* ------------------------------------------------------------------
	   CPT sidebar — show/hide "frequency days" based on frequency select
	   ------------------------------------------------------------------ */

	var $frequency     = $( '#eip_frequency' );
	var $frequencyDays = $( '.eip-field--frequency-days' );

	if ( $frequency.length ) {
		$frequency.on( 'change', function () {
			if ( $( this ).val() === 'time' ) {
				$frequencyDays.show();
			} else {
				$frequencyDays.hide();
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Settings page — initialise WP colour pickers
	   ------------------------------------------------------------------ */

	if ( $.fn.wpColorPicker ) {
		$( '.eip-color-picker' ).wpColorPicker();
	}

	/* ------------------------------------------------------------------
	   A/B Results page — Clear All Data
	   ------------------------------------------------------------------ */

	var $clearBtn = $( '#eip-clear-data' );

	if ( $clearBtn.length && cfg.deleteUrl ) {
		$clearBtn.on( 'click', function () {
			// Native confirm dialog — straightforward for an admin-only action.
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( cfg.confirmClear ) ) {
				return;
			}

			$clearBtn.prop( 'disabled', true ).text( cfg.clearingLabel );

			$.ajax( {
				url: cfg.deleteUrl,
				method: 'DELETE',
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', cfg.nonce );
				},
				success: function () {
					// Reload to reflect the now-empty table.
					window.location.reload();
				},
				error: function () {
					// eslint-disable-next-line no-alert
					window.alert( cfg.clearErrorMsg );
					$clearBtn.prop( 'disabled', false ).text( cfg.clearLabel );
				},
			} );
		} );
	}

} )( jQuery );
