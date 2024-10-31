(function($) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source should reside in
	 * this file.
	 * 
	 * Note: It has been assumed you will write jQuery code here, so the $
	 * function reference has been prepared for usage within the scope of this
	 * function.
	 * 
	 * This enables you to define handlers, for when the DOM is ready:
	 * 
	 * $(function() {
	 * 
	 * });
	 * 
	 * When the window is loaded: $( window ).load(function() {
	 * 
	 * });
	 * 
	 * ...and/or other possibilities.
	 * 
	 * Ideally, it is not considered best practise to attach more than a single
	 * DOM-ready or window-load handler for a particular page. Although scripts
	 * in the WordPress core, Plugins and Themes may be practising this, we
	 * should strive to set a better example in our own work.
	 */

	$(function() {

		// enable price recal when country changed

		function updateVATCells(item) {
			console.log("== updateVATCells ==");
			console.log(item);

			var value = null;
			if (item && typeof item !== 'undefined') {
				value = item.val();
			} else {
				console.err("no input item");
			}

			console.log('Country changed to ' + value);
			var level = $('#rcp_subscription_levels input:checked');
			var levelVal = level.val();
			console.log(levelVal);
			var rcp_company_vat_number = $('#rcp_company_vat_number').val();
			var data = {
				action : 'rcp_vat_get_vat_rate',
				'country_code' : value,
				'level' : levelVal,
				'rcp_company_vat_number' : rcp_company_vat_number
			};

			// data = $.extend({}, data0, atts);

			console.log(data);
			/*
			 * console.log(maxicharts_ajax_object);
			 * console.log(maxicharts_ajax_object.ajax_url);
			 */
			$.post(rcp_vat_ajax_object.ajax_url, data, function(response) {
				console.log("RCP VAT response from WP site ");

				var vat_label = response.vat_label;
				var country = response.country_name;
				var country_rate = response.country_vat_rate;
				var vat_amount = response.vat_amount;
				var total_price = response.total_price;
				var ht_amount = response.level_price;

				console.log(response);
				console.log(vat_label);

				$("#rcp_registration_form > div.rcp_registration_total > table > tbody > tr:nth-child(1) > td:nth-child(2)").text(ht_amount);
				//$("#rcp_registration_form > div.rcp_registration_total > table > tfoot > tr.rcp-vat > th").text(vat_label);
				$("#rcp_registration_form > div.rcp_registration_total > table > tbody > tr.rcp-vat > td:nth-child(1)").text(vat_label);

				//$("#rcp_registration_form > div.rcp_registration_total > table > tfoot > tr.rcp-vat > td").text(vat_amount);
				$("#rcp_registration_form > div.rcp_registration_total > table > tbody > tr.rcp-vat > td:nth-child(2)").text(vat_amount);
				//$("#rcp_registration_form > div.rcp_registration_total > table > tbody > tr.rcp-vat > td:nth-child(2)")

				//*[@id="rcp_registration_form"]/div[2]/table/tfoot/tr[2]/td
				// #rcp_registration_form > div.rcp_registration_total > table > tfoot > tr.rcp-recurring-total > td
				$("#rcp_registration_form > div.rcp_registration_total > table > tfoot > tr.rcp-total > td").text(total_price);
				$("#rcp_registration_form > div.rcp_registration_total > table > tfoot > tr.rcp-recurring-total > td").text(total_price);
			});
		}

		$('body').on('change', '#billing_country', function() {
			//updateVATCells($(this));
			rcp_vat_calc_total();
		});

		$('.rcp_level').change(function() {

			// $('.rcp_level').delay( 1800 );
			rcp_vat_calc_total();
		});

		$(document.getElementById('rcp_auto_renew')).on('change', rcp_vat_calc_total);
		$('body').on('rcp_discount_change rcp_level_change rcp_gateway_change', rcp_vat_calc_total);

		function rcp_vat_calc_total() {

			setTimeout(function() {
				console.log("== timeout ==");
				$('.rcp_level').ready(function() {
					// Handler for .ready() called.
					updateVATCells($('#billing_country'));
				});

			}, 1000);
		}
		
		
		rcp_vat_calc_total();
	});

})(jQuery);