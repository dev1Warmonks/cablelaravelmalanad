			<link href="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
			<style>
			span.required, .required { color: red !important; font-weight: bold; }
			</style>
			<script src="{{ url('/') }}/vendor/cdn/cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
			<script>
			$(function() {
				function toggleAmcEndDate() {
					var val = $("#field-amc_status").val();
					var box = $("#field-amc_end_date").closest(".form-group, .form-display-as-box, tr, div[id$='_field_box']");
					if (val == "1" || val == "Active") {
						box.show();
					} else {
						box.hide();
					}
				}

				toggleAmcEndDate();
				$(document).on("change", "#field-amc_status", function() {
					toggleAmcEndDate();
				});

				if ($("#subscriber_dropdown").length) {
					$("#subscriber_dropdown").select2({
						placeholder: "Search or select a subscriber",
						allowClear: true,
						width: "100%"
					});
				}

	$(document).on("change", "#subscriber_dropdown", function() {
		var subscriberId = $(this).val();
		if (subscriberId != 0) {
			$.ajax({
				url: "{{ $subscriber_url }}",
				type: "POST",
				data: { id: subscriberId },
				dataType: "json",
				success: function(data) {
					if (!data) return;
					$("#field-customer_id").val(data.customer_id);
					$("#field-caf_number").val(data.caf_number);
					$("#field-customer_name").val(data.customer_name);
					$("#field-lco_customer_id").val(data.lco_customer_id);
					$("#field-mobile").val(data.mobile);
					$("#field-billing_address").val(data.billing_address);
					$("#field-opening_balance").val(data.opening_balance);
					$("#field-serial_number").val(data.serial_number);
					$("#field-combo_plan").val(data.combo_plan);
					// $("#field-amc_end_date").val(data.amc_end_date);
					if (data.amc_end_date) {
						// Convert if it's in DD/MM/YYYY format
						var parts = data.amc_end_date.split("-");
						if (parts.length === 3) {
							// convert 18/10/2025 → 2025-10-18
							var formatted = parts[2] + "/" + parts[1].padStart(2, '0') + "/" + parts[0].padStart(2, '0');
							$("#field-amc_end_date").val(formatted);
						} else {
							// assume already YYYY-MM-DD
							$("#field-amc_end_date").val(data.amc_end_date);
						}
					}
					$("#field-vc_number").val(data.vc_number);
					$("#field-newid").val(data.newid);
					$("input[name='sms'][value='" + data.sms + "']").prop("checked", true);
					$("#field-amc_status").val(data.amc_status).trigger("change").trigger("chosen:updated");
					$("#field-c_type").val(data.c_type).trigger("change").trigger("chosen:updated");
					$("#field-status").val(data.status).trigger("change").trigger("chosen:updated");
					$("#field-area_id").val(data.area_id).trigger("change").trigger("chosen:updated");
					function setSubarea() {
						var subarea = $("#field-subarea_id");
						if (subarea.length && subarea.find("option[value='" + data.subarea_id + "']").length) {
							subarea.val(data.subarea_id).trigger("change").trigger("chosen:updated");
							return true;
						}
						return false;
					}
					const parent = document.querySelector("#field-subarea_id")
						? document.querySelector("#field-subarea_id").parentNode
						: document.querySelector("#field-area_id").parentNode;
					const observer = new MutationObserver(function() {
						if (setSubarea()) observer.disconnect();
					});
					if (parent) observer.observe(parent, { childList: true, subtree: true });
					var tries = 0;
					var interval = setInterval(function() {
						tries++;
						if (setSubarea()) {
							clearInterval(interval);
							observer.disconnect();
						}
						if (tries > 10) {
							clearInterval(interval);
							observer.disconnect();
						}
					}, 500);
				}
			});
		}
	});
		var isSubmitting = false;

		function resetSaveButtons() {
			isSubmitting = false;
			var btnSave = $('#form-button-save');
			var btnGoBack = $('#save-and-go-back-button');

			if (btnSave.length && btnSave.data('orig-val')) {
				btnSave.prop('disabled', false);
				if (btnSave.is('input')) {
					btnSave.val(btnSave.data('orig-val'));
				} else {
					btnSave.text(btnSave.data('orig-val'));
				}
			}
			if (btnGoBack.length && btnGoBack.data('orig-val')) {
				btnGoBack.prop('disabled', false);
				if (btnGoBack.is('input')) {
					btnGoBack.val(btnGoBack.data('orig-val'));
				} else {
					btnGoBack.text(btnGoBack.data('orig-val'));
				}
			}
		}

		$(document).on('click', '#form-button-save, #save-and-go-back-button', function(e) {
			if (isSubmitting) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return false;
			}

			var confirmed = confirm("Are you sure you want to save?");
			if (!confirmed) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return false;
			}

			var btnSave = $('#form-button-save');
			var btnGoBack = $('#save-and-go-back-button');

			if (btnSave.length && !btnSave.data('orig-val')) {
				btnSave.data('orig-val', btnSave.is('input') ? btnSave.val() : btnSave.text());
			}
			if (btnGoBack.length && !btnGoBack.data('orig-val')) {
				btnGoBack.data('orig-val', btnGoBack.is('input') ? btnGoBack.val() : btnGoBack.text());
			}

			isSubmitting = true;

			setTimeout(function() {
				if (!isSubmitting) return;
				if (btnSave.length) {
					btnSave.prop('disabled', true);
					if (btnSave.is('input')) btnSave.val('Saving...'); else btnSave.text('Saving...');
				}
				if (btnGoBack.length) {
					btnGoBack.prop('disabled', true);
					if (btnGoBack.is('input')) btnGoBack.val('Saving...'); else btnGoBack.text('Saving...');
				}
			}, 10);
		});

		function parseResponseJson(responseText) {
			if (!responseText) return null;
			var cleaned = responseText.replace(/<\/?[^>]+(>|$)/g, "").trim();
			try {
				return JSON.parse(cleaned);
			} catch(e) {
				try {
					return JSON.parse(responseText);
				} catch(e2) {
					return null;
				}
			}
		}

		$(document).ajaxComplete(function(event, xhr, settings) {
			if (!isSubmitting) return;

			var data = parseResponseJson(xhr.responseText);
			if (data) {
				if (data.success === false || data.error_message || data.error_fields) {
					resetSaveButtons();
				} else if (data.success === true && !data.redirect && !data.success_list_url) {
					setTimeout(resetSaveButtons, 500);
				}
			} else {
				resetSaveButtons();
			}
		});

		$(document).ajaxError(function() {
			resetSaveButtons();
		});

		$(document).on('input change', 'form#crudForm :input, form#gcrud-form :input', function() {
			if ($('#form-button-save').prop('disabled') || $('#save-and-go-back-button').prop('disabled')) {
				resetSaveButtons();
			}
		});

		$(document).on('gcrud.afterInsert', function() {
			resetSaveButtons();
			// Clear all normal inputs
			$('form#gcrud-form input, form#gcrud-form textarea').not('[type=hidden]').val('');

			// Clear normal select
			$('form#gcrud-form select').not('.select2-hidden-accessible').val('');

			// Clear Select2 fields
			$('.select2').val(null).trigger('change'); // This resets Select2
		});

		$(document).on('gcrud.afterUpdate', function() {
			resetSaveButtons();
			// Clear all normal inputs
			$('form#gcrud-form input, form#gcrud-form textarea').not('[type=hidden]').val('');

			// Clear normal select
			$('form#gcrud-form select').not('.select2-hidden-accessible').val('');

			// Clear Select2 fields
			$('.select2').val(null).trigger('change'); // This resets Select2
		});
	});
	</script>
