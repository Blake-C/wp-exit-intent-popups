/**
 * Exit Intent Popup - Frontend
 *
 * Handles exit intent detection, auto-appear timers, A/B popup selection,
 * frequency management via localStorage/sessionStorage, event tracking,
 * GA4 integration, and focus trapping within open modals.
 */
( function () {
	'use strict';

	const cfg         = window.eipConfig || {};
	const REST        = cfg.restUrl     || '';
	const NONCE       = cfg.nonce       || '';
	const PAGE        = cfg.pageId      || 0;
	const GA4_SHOWN   = cfg.ga4Shown    || 'eip_popup_shown';
	const GA4_CLOSED  = cfg.ga4Closed   || 'eip_popup_closed';
	const GA4_CTA     = cfg.ga4CtaClick || 'eip_cta_click';

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
	 * Focus trap
	 * --------------------------------------------------------------------- */

	const FOCUSABLE = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join( ',' );

	/**
	 * Return a handler that traps Tab / Shift+Tab within the given modal element.
	 *
	 * @param {HTMLElement} modal
	 * @return {Function}
	 */
	function buildFocusTrap( modal ) {
		return function trapFocus( e ) {
			if ( e.key !== 'Tab' ) return;

			const focusable = Array.from( modal.querySelectorAll( FOCUSABLE ) ).filter(
				function ( el ) { return ! el.closest( '[hidden]' ); }
			);

			if ( ! focusable.length ) {
				e.preventDefault();
				return;
			}

			const first = focusable[ 0 ];
			const last  = focusable[ focusable.length - 1 ];

			if ( e.shiftKey ) {
				if ( document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if ( document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		};
	}

	/* -----------------------------------------------------------------------
	 * Modal open / close
	 * --------------------------------------------------------------------- */

	let activeModal    = null;
	let activeTrapFn   = null;
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

		// Move focus into the modal — first focusable element or the panel itself.
		if ( modal ) {
			const firstFocusable = modal.querySelector( FOCUSABLE );
			if ( firstFocusable ) {
				firstFocusable.focus();
			} else {
				modal.focus();
			}

			// Attach and store the focus trap so we can remove it on close.
			activeTrapFn = buildFocusTrap( modal );
			modal.addEventListener( 'keydown', activeTrapFn );
		}

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
				ga4Event( GA4_CTA, { popup_id: popupId, page_id: PAGE } );
			} );
		} );

		markShown( wrapper );
		trackEvent( popupId, 'impression' );
		ga4Event( GA4_SHOWN, { popup_id: popupId, page_id: PAGE } );
	}

	function closeModal( wrapper ) {
		const popupId = parseInt( wrapper.dataset.popupId, 10 );
		const modal   = wrapper.querySelector( '.eip-modal' );

		// Remove focus trap.
		if ( modal && activeTrapFn ) {
			modal.removeEventListener( 'keydown', activeTrapFn );
			activeTrapFn = null;
		}

		wrapper.classList.remove( 'eip-is-active' );
		wrapper.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'eip-modal-open' );
		document.removeEventListener( 'keydown', handleEsc );
		activeModal = null;

		trackEvent( popupId, 'close' );
		ga4Event( GA4_CLOSED, { popup_id: popupId, page_id: PAGE } );
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

	let delayPassed    = delay === 0;
	let popupTriggered = false;

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
