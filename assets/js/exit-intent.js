/**
 * Exit Intent Popup - Frontend
 *
 * Handles exit intent detection, auto-appear timers, A/B popup selection,
 * frequency management via localStorage/sessionStorage, event tracking,
 * and GA4 integration.
 */
( function () {
	'use strict';

	const cfg    = window.eipConfig || {};
	const REST   = cfg.restUrl || '';
	const NONCE  = cfg.nonce || '';
	const PAGE   = cfg.pageId || 0;

	/* -----------------------------------------------------------------------
	 * Storage helpers
	 * --------------------------------------------------------------------- */

	const store = {
		get: function ( key ) {
			try { return localStorage.getItem( key ); } catch ( e ) { return null; }
		},
		set: function ( key, val ) {
			try { localStorage.setItem( key, val ); } catch ( e ) {}
		},
		sessionGet: function ( key ) {
			try { return sessionStorage.getItem( key ); } catch ( e ) { return null; }
		},
		sessionSet: function ( key, val ) {
			try { sessionStorage.setItem( key, val ); } catch ( e ) {}
		},
	};

	/* -----------------------------------------------------------------------
	 * Tracking
	 * --------------------------------------------------------------------- */

	function trackEvent( popupId, eventType ) {
		if ( ! REST ) return;
		fetch( REST, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': NONCE,
			},
			body: JSON.stringify( {
				popup_id: popupId,
				page_id: PAGE,
				event_type: eventType,
			} ),
			keepalive: true,
		} ).catch( function () {} );
	}

	function ga4Event( name, params ) {
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', name, params );
		}
	}

	/* -----------------------------------------------------------------------
	 * Frequency checks
	 * --------------------------------------------------------------------- */

	function isConverted( popupId ) {
		return store.get( 'eip_converted_' + popupId ) === '1';
	}

	function hasBeenShown( wrapper ) {
		const popupId   = wrapper.dataset.popupId;
		const frequency = wrapper.dataset.frequency || 'session';

		if ( frequency === 'always' ) return false;

		if ( frequency === 'session' ) {
			return store.sessionGet( 'eip_shown_' + popupId ) === '1';
		}

		if ( frequency === 'time' ) {
			const days      = parseInt( wrapper.dataset.frequencyDays, 10 ) || 7;
			const lastShown = store.get( 'eip_last_shown_' + popupId );
			if ( ! lastShown ) return false;
			const elapsed = ( Date.now() - parseInt( lastShown, 10 ) ) / ( 1000 * 60 * 60 * 24 );
			return elapsed < days;
		}

		return false;
	}

	function shouldShow( wrapper ) {
		return ! isConverted( wrapper.dataset.popupId ) && ! hasBeenShown( wrapper );
	}

	function markShown( wrapper ) {
		const popupId   = wrapper.dataset.popupId;
		const frequency = wrapper.dataset.frequency || 'session';

		if ( frequency === 'session' ) {
			store.sessionSet( 'eip_shown_' + popupId, '1' );
		} else if ( frequency === 'time' ) {
			store.set( 'eip_last_shown_' + popupId, Date.now().toString() );
		}
	}

	/* -----------------------------------------------------------------------
	 * Modal open / close
	 * --------------------------------------------------------------------- */

	let activeModal    = null;
	let lastCursorX    = null;
	let lastCursorY    = null;

	function openModal( wrapper ) {
		if ( ! shouldShow( wrapper ) ) return;

		const popupId      = parseInt( wrapper.dataset.popupId, 10 );
		const position     = wrapper.dataset.position || 'center';
		const overlayClick = wrapper.dataset.overlayClick === '1';
		const modal        = wrapper.querySelector( '.eip-modal' );

		// Position near last known cursor position for 'cursor' mode.
		if ( position === 'cursor' && modal && lastCursorX !== null ) {
			const mw = modal.offsetWidth  || 400;
			const mh = modal.offsetHeight || 200;
			const vw = window.innerWidth;
			const vh = window.innerHeight;
			const x  = Math.max( 10, Math.min( lastCursorX, vw - mw - 10 ) );
			const y  = Math.max( 10, Math.min( lastCursorY, vh - mh - 10 ) );
			modal.style.left = x + 'px';
			modal.style.top  = y + 'px';
		}

		wrapper.classList.add( 'eip-is-active' );
		wrapper.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'eip-modal-open' );
		activeModal = wrapper;

		// Focus the modal for accessibility.
		if ( modal ) modal.focus();

		// Close button.
		const closeBtn = wrapper.querySelector( '.eip-modal__close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				closeModal( wrapper );
			}, { once: true } );
		}

		// Overlay click.
		if ( overlayClick ) {
			const overlay = wrapper.querySelector( '.eip-overlay' );
			if ( overlay ) {
				overlay.addEventListener( 'click', function () {
					closeModal( wrapper );
				}, { once: true } );
			}
		}

		// ESC key.
		document.addEventListener( 'keydown', handleEsc );

		// Track CTA link clicks as conversions.
		const links = wrapper.querySelectorAll( '.eip-modal__content a[href]' );
		links.forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				store.set( 'eip_converted_' + popupId, '1' );
				trackEvent( popupId, 'conversion' );
				ga4Event( 'eip_cta_click', { popup_id: popupId, page_id: PAGE } );
			} );
		} );

		markShown( wrapper );
		trackEvent( popupId, 'impression' );
		ga4Event( 'eip_popup_shown', { popup_id: popupId, page_id: PAGE } );
	}

	function closeModal( wrapper ) {
		const popupId = parseInt( wrapper.dataset.popupId, 10 );
		wrapper.classList.remove( 'eip-is-active' );
		wrapper.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'eip-modal-open' );
		document.removeEventListener( 'keydown', handleEsc );
		activeModal = null;

		trackEvent( popupId, 'close' );
		ga4Event( 'eip_popup_closed', { popup_id: popupId, page_id: PAGE } );
	}

	function handleEsc( e ) {
		if ( e.key === 'Escape' && activeModal ) {
			closeModal( activeModal );
		}
	}

	/* -----------------------------------------------------------------------
	 * Bootstrap
	 * --------------------------------------------------------------------- */

	const allWrappers = Array.from( document.querySelectorAll( '.eip-modal-wrapper' ) );
	if ( ! allWrappers.length ) return;

	// Filter to only those eligible to show (not converted, not suppressed by frequency).
	const eligible = allWrappers.filter( shouldShow );
	if ( ! eligible.length ) return;

	// A/B: pick one popup at random from the eligible set.
	const selected = eligible[ Math.floor( Math.random() * eligible.length ) ];

	const delay      = parseInt( selected.dataset.delay, 10 ) || 0;
	const autoAppear = parseInt( selected.dataset.autoAppear, 10 ) || 0;

	let delayPassed      = delay === 0;
	let popupTriggered   = false;

	// Track cursor position for 'cursor' position mode.
	document.addEventListener( 'mousemove', function ( e ) {
		lastCursorX = e.clientX;
		lastCursorY = e.clientY;
	} );

	// Enable exit intent after the configured delay.
	if ( delay > 0 ) {
		setTimeout( function () {
			delayPassed = true;
		}, delay * 1000 );
	}

	// Exit intent: mouse leaves the top of the viewport.
	document.addEventListener( 'mouseleave', function ( e ) {
		if ( popupTriggered || ! delayPassed || activeModal ) return;
		// Trigger only when leaving from the top edge.
		if ( e.clientY <= 5 ) {
			popupTriggered = true;
			openModal( selected );
		}
	} );

	// Auto-appear timer (independent of exit intent delay).
	if ( autoAppear > 0 ) {
		setTimeout( function () {
			if ( ! popupTriggered && ! activeModal ) {
				popupTriggered = true;
				openModal( selected );
			}
		}, autoAppear * 1000 );
	}

} )();
