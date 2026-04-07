/**
 * Exit Intent Popup - Admin
 *
 * Handles dynamic UI behaviour in the CPT meta box sidebar.
 */
( function ( $ ) {
	'use strict';

	// Show/hide "frequency days" field based on the frequency select value.
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

} )( jQuery );
