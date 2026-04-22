/**
 * YS RAQ Addons - 報價頁面前端腳本
 *
 * 功能：
 * - 數量輸入框變更 → debounce 後 AJAX 送出 update_raq → 從回應 HTML 抽換商品列表
 * - 隱藏原生 Update List 按鈕（由自動更新取代）
 * - 顯示 loading 狀態與 toast 提示
 *
 * @version 3.1.0
 */
(function ($) {
	'use strict';

	$(function () {
		// YITH RAQ 表單 id 為 yith-ywraq-form（帶 a），但列表 table id 為 yith-ywrq-table-list（不帶 a）
		var $form = $('#yith-ywraq-form');
		if (!$form.length) {
			return;
		}

		var params   = window.ys_raq_quote_params || {};
		var debounce = parseInt(params.debounceMs, 10) > 0 ? parseInt(params.debounceMs, 10) : 500;
		var i18n     = params.i18n || {};
		var timer    = null;
		var inflight = null;

		// 隱藏原生 Update List 按鈕（由自動更新取代）
		$form.find('input[name="update_raq"]').hide();

		// 數量變更：debounce 後 AJAX 更新
		$form.on('input change', 'input.qty', function () {
			clearTimeout(timer);
			timer = setTimeout(submitUpdate, debounce);
		});

		function submitUpdate() {
			if (inflight && typeof inflight.abort === 'function') {
				inflight.abort();
			}

			var $wrapper = $('.ywraq-form-table-wrapper');
			var $table   = $('#yith-ywrq-table-list');
			if (!$table.length) {
				return;
			}

			$wrapper.addClass('ys-raq-loading');

			inflight = $.ajax({
				url: $form.attr('action') || window.location.href,
				method: 'POST',
				data: $form.serialize() + '&update_raq=1'
			}).done(function (html) {
				var $parsed   = $('<div>').html(html);
				var $newTable = $parsed.find('#yith-ywrq-table-list');
				if ($newTable.length) {
					$table.replaceWith($newTable);
					// 新 table 中的 Update List 按鈕也要隱藏
					$('#yith-ywraq-form').find('input[name="update_raq"]').hide();
					showToast(i18n.updated || '已更新', 'success');
				} else {
					// 清單已空或結構改變，reload 比較安全
					window.location.reload();
				}
			}).fail(function (jqXHR, status) {
				if (status !== 'abort') {
					showToast(i18n.failed || '更新失敗', 'error');
				}
			}).always(function () {
				$wrapper.removeClass('ys-raq-loading');
				inflight = null;
			});
		}

		function showToast(msg, type) {
			var $el = $('<div class="ys-raq-toast ys-raq-toast-' + type + '"></div>').text(msg);
			$('body').append($el);
			setTimeout(function () { $el.addClass('ys-raq-toast-in'); }, 10);
			setTimeout(function () {
				$el.removeClass('ys-raq-toast-in');
				setTimeout(function () { $el.remove(); }, 400);
			}, 1500);
		}
	});

})(jQuery);
