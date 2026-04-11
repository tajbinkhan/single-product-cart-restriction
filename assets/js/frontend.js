(function ($, window) {
	'use strict';

	$(function () {
		if (
			'undefined' === typeof window.spcrFrontend ||
			'undefined' === typeof window.spcrFrontend.checkoutUrl ||
			'undefined' === typeof window.wc_add_to_cart_params
		) {
			return;
		}

		var checkoutUrl = window.spcrFrontend.checkoutUrl;
		var originalCartUrl = window.wc_add_to_cart_params.cart_url;

		function restoreCartUrl() {
			if ( 'undefined' === typeof window.wc_add_to_cart_params ) {
				return;
			}

			window.wc_add_to_cart_params.cart_url = originalCartUrl;
		}

		$( document.body ).on( 'click', '.add_to_cart_button.ajax_add_to_cart[data-spcr-bypass-cart="yes"]', function () {
			window.wc_add_to_cart_params.cart_url = checkoutUrl;
		} );

		$( document.body ).on( 'added_to_cart', function ( event, fragments, cartHash, $button ) {
			void fragments;
			void cartHash;

			if ( ! $button || ! $button.length || 'yes' !== $button.data( 'spcrBypassCart' ) ) {
				restoreCartUrl();
				return;
			}

			window.location = checkoutUrl;
		} );

		$( document.body ).on( 'ajax_request_not_sent.adding_to_cart', restoreCartUrl );
	} );
})( jQuery, window );
