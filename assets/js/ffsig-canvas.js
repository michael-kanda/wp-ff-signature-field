/**
 * FF Signature Field – Canvas drawing logic.
 *
 * Initialises every .ffsig-wrapper element on the page,
 * sets up the canvas at the correct device-pixel-ratio,
 * and wires up pointer / mouse / touch events for drawing.
 *
 * @package FF_Signature_Field
 * @since   2.0.0
 */

( function () {
	'use strict';

	/**
	 * Initialise a single signature wrapper element.
	 *
	 * @param {HTMLElement} wrapper The .ffsig-wrapper container.
	 */
	function initSignature( wrapper ) {
		var canvas = wrapper.querySelector( '.ffsig-canvas' );
		var input  = wrapper.querySelector( '.ffsig-input' );
		var clear  = wrapper.querySelector( '.ffsig-clear' );

		if ( ! canvas || ! input ) {
			return;
		}

		// Guard against double-initialisation.
		if ( wrapper.dataset.ffsigReady ) {
			return;
		}
		wrapper.dataset.ffsigReady = '1';

		var ctx;
		var isDrawing = false;
		var hasSigned = false;

		/**
		 * (Re-)configure the canvas dimensions to match the wrapper width
		 * while accounting for the device pixel ratio.
		 */
		function setup() {
			var rect = wrapper.getBoundingClientRect();
			var dpr  = window.devicePixelRatio || 1;
			var w    = rect.width > 0 ? rect.width : 600;

			canvas.width  = w * dpr;
			canvas.height = 200 * dpr;
			canvas.style.width  = w + 'px';
			canvas.style.height = '200px';

			ctx = canvas.getContext( '2d' );
			ctx.scale( dpr, dpr );
			ctx.fillStyle   = '#ffffff';
			ctx.fillRect( 0, 0, w, 200 );
			ctx.strokeStyle = '#000000';
			ctx.lineWidth   = 2;
			ctx.lineCap     = 'round';
			ctx.lineJoin    = 'round';
		}

		/**
		 * Extract the pointer coordinates relative to the canvas.
		 *
		 * @param {Event} event A pointer, mouse, or touch event.
		 * @return {Object} An object with x and y properties.
		 */
		function getPoint( event ) {
			var rect = canvas.getBoundingClientRect();
			var clientX, clientY;

			if ( event.touches && event.touches.length > 0 ) {
				clientX = event.touches[0].clientX;
				clientY = event.touches[0].clientY;
			} else {
				clientX = event.clientX;
				clientY = event.clientY;
			}

			return {
				x: clientX - rect.left,
				y: clientY - rect.top
			};
		}

		/**
		 * Handle the start of a stroke.
		 *
		 * @param {Event} event
		 */
		function onStart( event ) {
			event.preventDefault();
			isDrawing = true;
			var point = getPoint( event );
			ctx.beginPath();
			ctx.moveTo( point.x, point.y );
		}

		/**
		 * Handle movement during a stroke.
		 *
		 * @param {Event} event
		 */
		function onMove( event ) {
			if ( ! isDrawing ) {
				return;
			}
			event.preventDefault();
			var point = getPoint( event );
			ctx.lineTo( point.x, point.y );
			ctx.stroke();
			ctx.beginPath();
			ctx.moveTo( point.x, point.y );
			hasSigned = true;
		}

		/**
		 * Handle the end of a stroke and serialise the canvas to the hidden input.
		 */
		function onEnd() {
			if ( ! isDrawing ) {
				return;
			}
			isDrawing = false;
			ctx.beginPath();

			if ( hasSigned ) {
				input.value = canvas.toDataURL( 'image/png' );
				if ( window.jQuery ) {
					window.jQuery( input ).trigger( 'change' );
				}
			}
		}

		// Bind the appropriate pointer events.
		if ( window.PointerEvent ) {
			canvas.addEventListener( 'pointerdown',  onStart );
			canvas.addEventListener( 'pointermove',  onMove );
			canvas.addEventListener( 'pointerup',    onEnd );
			canvas.addEventListener( 'pointerleave', onEnd );
		} else {
			canvas.addEventListener( 'mousedown',  onStart );
			canvas.addEventListener( 'mousemove',  onMove );
			canvas.addEventListener( 'mouseup',    onEnd );
			canvas.addEventListener( 'mouseleave', onEnd );
			canvas.addEventListener( 'touchstart', onStart, { passive: false } );
			canvas.addEventListener( 'touchmove',  onMove,  { passive: false } );
			canvas.addEventListener( 'touchend',   onEnd,   { passive: false } );
		}

		// Clear button.
		if ( clear ) {
			clear.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				hasSigned   = false;
				input.value = '';
				setup();
				if ( window.jQuery ) {
					window.jQuery( input ).trigger( 'change' );
				}
			} );
		}

		// Initial setup.
		setup();

		// Reconfigure on resize (debounced).
		var resizeTimer;
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () {
				hasSigned   = false;
				input.value = '';
				setup();
			}, 300 );
		} );
	}

	/**
	 * Discover and initialise all signature wrappers on the page.
	 */
	function initAll() {
		var wrappers = document.querySelectorAll( '.ffsig-wrapper' );
		for ( var i = 0; i < wrappers.length; i++ ) {
			initSignature( wrappers[ i ] );
		}
	}

	// Run once the DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	// Also observe the DOM for dynamically injected fields (multi-step forms).
	if ( typeof MutationObserver !== 'undefined' ) {
		var observer = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				var nodes = mutations[ i ].addedNodes;
				for ( var j = 0; j < nodes.length; j++ ) {
					if ( nodes[ j ].nodeType !== 1 ) {
						continue;
					}
					var inner = nodes[ j ].querySelectorAll
						? nodes[ j ].querySelectorAll( '.ffsig-wrapper' )
						: [];
					for ( var k = 0; k < inner.length; k++ ) {
						initSignature( inner[ k ] );
					}
					if ( nodes[ j ].classList && nodes[ j ].classList.contains( 'ffsig-wrapper' ) ) {
						initSignature( nodes[ j ] );
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}
} )();
