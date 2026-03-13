/**
 * YS RAQ Addons - 後台管理腳本
 *
 * @version 2.0.0
 */
(function ($) {
	'use strict';

	// ── Tab 切換 ──────────────────────────────────
	$('.ys-tab-link').on('click', function (e) {
		e.preventDefault();
		var tab = $(this).data('tab');

		$('.ys-tab-link').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$('.ys-tab-content').hide();
		$('#ys-tab-' + tab).show();

		// 表單欄位 tab 不需要 form 和儲存按鈕
		if (tab === 'fields') {
			$('.ys-settings-form').hide();
		} else {
			$('.ys-settings-form').show();
		}

		// 更新 URL
		if (history.pushState) {
			var url = new URL(window.location);
			url.searchParams.set('tab', tab);
			history.pushState({}, '', url);
		}
	});

	// ── CC/BCC 條件顯示 ──────────────────────────
	$('input[name="ys_raq_email_cc_enabled"]').on('change', function () {
		$('.ys-raq-cc-row').toggle(this.checked);
	});

	$('input[name="ys_raq_email_bcc_enabled"]').on('change', function () {
		$('.ys-raq-bcc-row').toggle(this.checked);
	});

	// ── 表單欄位管理 ─────────────────────────────

	// 欄位類型切換
	function toggleFieldTypeOptions(type) {
		$('#ys-raq-field-options-wrap').toggle(type === 'select' || type === 'radio');
		$('#ys-raq-field-checkbox-label-wrap').toggle(type === 'checkbox');
	}

	$('#ys-raq-field-type').on('change', function () {
		toggleFieldTypeOptions($(this).val());
	});

	// 開啟新增欄位 Modal
	$('.ys-raq-add-field-btn').on('click', function () {
		$('#ys-raq-modal-title').text(ys_raq_admin.i18n.add_field || '新增欄位');
		$('#ys-raq-field-editing-id').val('');
		$('#ys-raq-field-label').val('');
		$('#ys-raq-field-id').val('').prop('disabled', false);
		$('#ys-raq-field-type').val('text');
		$('#ys-raq-field-options').val('');
		$('#ys-raq-field-checkbox-label').val('');
		$('#ys-raq-field-required').prop('checked', false);
		toggleFieldTypeOptions('text');
		$('#ys-raq-field-modal').show();
	});

	// 編輯欄位
	$(document).on('click', '.ys-raq-edit-field', function () {
		var fieldId = $(this).data('field-id');
		var field = $(this).data('field');

		$('#ys-raq-modal-title').text('編輯欄位');
		$('#ys-raq-field-editing-id').val(fieldId);
		$('#ys-raq-field-label').val(field.label || '');
		$('#ys-raq-field-id').val(fieldId).prop('disabled', true);
		$('#ys-raq-field-type').val(field.type || 'text');
		$('#ys-raq-field-required').prop('checked', field.required === 'yes');
		$('#ys-raq-field-checkbox-label').val(field.checkbox_label || '');

		// 選項
		if (field.options && typeof field.options === 'object') {
			var lines = [];
			for (var key in field.options) {
				if (field.options.hasOwnProperty(key)) {
					lines.push(key + '|' + field.options[key]);
				}
			}
			$('#ys-raq-field-options').val(lines.join('\n'));
		} else {
			$('#ys-raq-field-options').val('');
		}

		toggleFieldTypeOptions(field.type || 'text');
		$('#ys-raq-field-modal').show();
	});

	// 關閉 Modal
	$('.ys-raq-modal-close, .ys-raq-modal-cancel, .ys-raq-modal-overlay').on('click', function () {
		$('#ys-raq-field-modal').hide();
	});

	// 儲存欄位
	$('.ys-raq-modal-save').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		var editingId = $('#ys-raq-field-editing-id').val();

		$.ajax({
			url: ys_raq_admin.ajaxurl,
			type: 'POST',
			data: {
				action: 'ys_raq_save_field',
				nonce: ys_raq_admin.nonce,
				editing_id: editingId,
				field_id: editingId || $('#ys-raq-field-id').val(),
				label: $('#ys-raq-field-label').val(),
				type: $('#ys-raq-field-type').val(),
				required: $('#ys-raq-field-required').is(':checked') ? 'true' : 'false',
				options: $('#ys-raq-field-options').val(),
				checkbox_label: $('#ys-raq-field-checkbox-label').val()
			},
			success: function (response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data?.message || ys_raq_admin.i18n.error);
				}
			},
			error: function () {
				alert(ys_raq_admin.i18n.error);
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// 刪除欄位
	$(document).on('click', '.ys-raq-delete-field', function () {
		if (!confirm(ys_raq_admin.i18n.confirm_delete)) {
			return;
		}

		var fieldId = $(this).data('field-id');
		var $row = $(this).closest('tr');

		$.ajax({
			url: ys_raq_admin.ajaxurl,
			type: 'POST',
			data: {
				action: 'ys_raq_delete_field',
				nonce: ys_raq_admin.nonce,
				field_id: fieldId
			},
			success: function (response) {
				if (response.success) {
					$row.fadeOut(300, function () {
						$(this).remove();
						if ($('#ys-raq-fields-body tr').length === 0) {
							$('#ys-raq-fields-body').html(
								'<tr class="ys-raq-no-fields"><td colspan="6" style="text-align:center;padding:20px;color:#999;">尚未新增自訂欄位。</td></tr>'
							);
						}
					});
				}
			}
		});
	});

	// 切換欄位啟用狀態
	$(document).on('change', '.ys-raq-toggle-field', function () {
		var fieldId = $(this).data('field-id');
		var enabled = $(this).is(':checked');

		$.ajax({
			url: ys_raq_admin.ajaxurl,
			type: 'POST',
			data: {
				action: 'ys_raq_toggle_field',
				nonce: ys_raq_admin.nonce,
				field_id: fieldId,
				enabled: enabled ? 'true' : 'false'
			}
		});
	});

	// 欄位拖曳排序
	if ($.fn.sortable) {
		$('#ys-raq-fields-body').sortable({
			handle: '.ys-raq-sort-handle',
			axis: 'y',
			update: function () {
				var order = [];
				$('#ys-raq-fields-body tr[data-field-id]').each(function () {
					order.push($(this).data('field-id'));
				});

				$.ajax({
					url: ys_raq_admin.ajaxurl,
					type: 'POST',
					data: {
						action: 'ys_raq_reorder_fields',
						nonce: ys_raq_admin.nonce,
						order: order
					}
				});
			}
		});
	}

	// ── 報價詳情：狀態變更 ─────────────────────────
	$('.ys-raq-status-select').on('change', function () {
		var quoteId = $(this).data('quote-id');
		var status = $(this).val();

		$.ajax({
			url: ys_raq_admin.ajaxurl,
			type: 'POST',
			data: {
				action: 'ys_raq_update_status',
				nonce: ys_raq_admin.nonce,
				quote_id: quoteId,
				status: status
			},
			success: function (response) {
				if (response.success) {
					// 狀態已更新
				}
			}
		});
	});

	// ── 報價列表：快速狀態變更 ─────────────────────
	var statusColors = {
		'new': '#2271b1',
		'pending': '#dba617',
		'replied': '#00a32a',
		'expired': '#996800',
		'closed': '#787c82'
	};

	$(document).on('change', '.ys-raq-list-status-select', function () {
		var $select = $(this);
		var quoteId = $select.data('quote-id');
		var status = $select.val();
		var color = statusColors[status] || '#787c82';

		$select.addClass('ys-status-saving');

		$.ajax({
			url: ys_raq_admin.ajaxurl,
			type: 'POST',
			data: {
				action: 'ys_raq_update_status',
				nonce: ys_raq_admin.nonce,
				quote_id: quoteId,
				status: status
			},
			success: function (response) {
				if (response.success) {
					$select.css({ 'border-color': color, 'color': color });
				}
			},
			complete: function () {
				$select.removeClass('ys-status-saving');
			}
		});
	});

})(jQuery);
