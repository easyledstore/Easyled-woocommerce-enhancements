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
		var codMethodId = 'cod';

		function syncCodCheckoutUi() {
			var $paymentSection = $( '#payment' );
			if ( ! $paymentSection.length ) {
				return;
			}

			var selectedMethod = $( 'input[name="payment_method"]:checked' ).val();
			var isCodSelected = selectedMethod === codMethodId;

			$paymentSection.find( '.easyled-payment-method-hidden' ).removeClass( 'easyled-payment-method-hidden' ).show();

			if ( isCodSelected ) {
				$paymentSection.find( '.wc_payment_method' ).not( '.payment_method_' + codMethodId ).each( function() {
					$( this ).addClass( 'easyled-payment-method-hidden' ).hide();
				} );
			}

			$( '.easyled-cod-fee-notice' ).toggle( isCodSelected );
		}

		syncCodCheckoutUi();

		$( document.body ).on( 'change', 'input[name="payment_method"]', function() {
			syncCodCheckoutUi();
			$( document.body ).trigger( 'update_checkout' );
		} );

		$( document.body ).on( 'updated_checkout', function() {
			syncCodCheckoutUi();
		} );
	} );

})( jQuery );
