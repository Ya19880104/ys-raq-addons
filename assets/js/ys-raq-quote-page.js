/**
 * YS RAQ Addons - 報價頁面前端腳本
 *
 * 返回商店按鈕和自訂表單欄位已由 PHP output buffering 直接注入 HTML，
 * 本檔案僅處理互動增強。
 *
 * @version 3.0.0
 */
(function ($) {
	'use strict';

	$(function () {
		// ── 更新清單按鈕：點擊後禁用並顯示載入文字 ──
		$('#yith-ywrq-form input[name="update_raq"]').on('click', function () {
			var $btn = $(this);
			if (typeof ys_raq_quote_params !== 'undefined' && ys_raq_quote_params.i18n.updating) {
				$btn.prop('disabled', true).val(ys_raq_quote_params.i18n.updating);
			}
		});
	});

})(jQuery);
