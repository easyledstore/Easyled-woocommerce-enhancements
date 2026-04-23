(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Keep checkout totals in sync when payment method changes.
	 */

	$( function() {
		var settings = window.easyledWooEnhancements || {};
		var codMethodId = settings.codPaymentMethod || 'cod';
		var codShippingKeywords = settings.codShippingKeywords || [ 'contrassegno_brt' ];

		function debugLog() {
			if ( ! settings.debug || ! window.console || ! window.console.log ) {
				return;
			}

			window.console.log.apply( window.console, arguments );
		}

		function getLabelTextForInput( $input ) {
			var id = $input.attr( 'id' );
			var labelText = '';

			if ( id ) {
				labelText = $( 'label[for="' + id.replace( /"/g, '\\"' ) + '"]' ).text();
			}

			if ( ! labelText ) {
				labelText = $input.closest( 'li, tr, .woocommerce-shipping-methods' ).text();
			}

			return labelText || '';
		}

		function getSelectedShippingText() {
			var selectedText = [];
			var $selectedInputs = $( 'input.shipping_method:checked, input[name^="shipping_method"]:checked' );
			var $selectedSelects = $( 'select.shipping_method, select[name^="shipping_method"]' );

			if ( ! $selectedInputs.length ) {
				$selectedInputs = $( 'input.shipping_method, input[name^="shipping_method"]' ).filter( function() {
					return $( 'input[name="' + this.name + '"]' ).length === 1;
				} );
			}

			$selectedInputs.each( function() {
				var $input = $( this );
				selectedText.push( $input.val() || '' );
				selectedText.push( getLabelTextForInput( $input ) );
			} );

			$selectedSelects.each( function() {
				var $select = $( this );
				selectedText.push( $select.val() || '' );
				selectedText.push( $select.find( 'option:selected' ).text() || '' );
			} );

			return selectedText.join( ' ' ).toLowerCase();
		}

		function isCodShippingSelected() {
			var selectedShippingText = getSelectedShippingText();
			var found = false;

			$.each( codShippingKeywords, function( index, keyword ) {
				keyword = String( keyword || '' ).toLowerCase();

				if ( keyword && selectedShippingText.indexOf( keyword ) !== -1 ) {
					found = true;
					return false;
				}
			} );

			debugLog( '[Easyled checkout]', {
				codShippingSelected: found,
				selectedShippingText: selectedShippingText
			} );

			return found;
		}

		function selectCodPaymentMethod() {
			var $codPayment = $( 'input[name="payment_method"][value="' + codMethodId + '"]' );

			if ( $codPayment.length && ! $codPayment.is( ':checked' ) ) {
				$codPayment.prop( 'checked', true );
			}
		}

		function showPaymentMethods( $paymentSection ) {
			$paymentSection.removeClass( 'easyled-cod-shipping-active' );
			$paymentSection.find( '.easyled-payment-method-hidden' ).removeClass( 'easyled-payment-method-hidden' ).show();
			$paymentSection.find( '.wc_payment_methods, .wc_payment_method' ).show();
		}

		function hidePaymentMethods( $paymentSection ) {
			$paymentSection.addClass( 'easyled-cod-shipping-active' );
			$paymentSection.find( '.wc_payment_methods, .wc_payment_method' ).hide();
		}

		function syncCodCheckoutUi() {
			var $paymentSection = $( '#payment' );
			if ( ! $paymentSection.length ) {
				return;
			}

			if ( isCodShippingSelected() ) {
				selectCodPaymentMethod();
				hidePaymentMethods( $paymentSection );
				$( '.easyled-cod-fee-notice' ).show();
				return;
			}

			showPaymentMethods( $paymentSection );
			$( '.easyled-cod-fee-notice' ).hide();
		}

		syncCodCheckoutUi();

		$( document.body ).on( 'change', 'input[name="payment_method"]', function() {
			syncCodCheckoutUi();
			$( document.body ).trigger( 'update_checkout' );
		} );

		$( document.body ).on( 'change', 'input.shipping_method, input[name^="shipping_method"], select.shipping_method, select[name^="shipping_method"]', function() {
			syncCodCheckoutUi();
			$( document.body ).trigger( 'update_checkout' );
		} );

		$( document.body ).on( 'updated_checkout', function() {
			syncCodCheckoutUi();
		} );

		setTimeout( function() {
			$( document.body ).trigger( 'update_checkout' );
		}, 500 );
	} );

})( jQuery );
