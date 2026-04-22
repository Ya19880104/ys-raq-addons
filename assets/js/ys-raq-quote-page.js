/**
 * YS RAQ Addons - 報價頁面前端腳本
 *
 * 功能：
 * - 數量輸入框變更 → debounce 後 AJAX 送出 update_raq
 * - 回應 HTML 解析後 in-place 更新 subtotal / 移除已刪除的 row
 *   （刻意不 replaceWith 整個 <table>，否則主題（Blocksy / Flatsome / Astra…）
 *    綁在 +/- 按鈕的 JS handler 會一起被摧毀，導致第二次以後按 +/- 沒反應）
 * - 隱藏原生 Update List 按鈕（由自動更新取代）
 * - 顯示 loading 狀態與 toast 提示
 *
 * @version 3.2.0
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

		// 數量變更：用 document 級事件代理，即使之後 DOM 更新也能持續運作
		$(document).on('input change', '#yith-ywraq-form input.qty', function () {
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

				if (!$newTable.length) {
					// 整個 table 都消失（清單空了）→ reload 讓 YITH 顯示空清單 UI
					window.location.reload();
					return;
				}

				updateTableInPlace($table, $newTable);
				// 新 Update List 按鈕若存在也要隱藏（通常不會有，保險用）
				$('#yith-ywraq-form').find('input[name="update_raq"]').hide();

				showToast(i18n.updated || '已更新', 'success');
			}).fail(function (jqXHR, status) {
				if (status !== 'abort') {
					showToast(i18n.failed || '更新失敗', 'error');
				}
			}).always(function () {
				$wrapper.removeClass('ys-raq-loading');
				inflight = null;
			});
		}

		/**
		 * In-place 更新 table 內容，保留所有現有 DOM 節點的事件綁定
		 *
		 * - 主題（Blocksy 的 .ct-increase/.ct-decrease、Flatsome 的 .plus/.minus…）
		 *   把 click handler 綁在具體的 DOM node 上，整個 replaceWith 會讓新節點
		 *   沒有任何綁定，導致 +/- 按鈕按了沒反應
		 * - 因此這裡只做最小必要的更新：
		 *   1. 移除被伺服器刪除的 row（qty=0 時 YITH remove_item）
		 *   2. 更新 subtotal 欄位（若主題有顯示）
		 *   qty input 的值不動（使用者剛輸入的才是權威值）
		 */
		function updateTableInPlace($oldTable, $newTable) {
			// 1. 蒐集新 table 還存在的 item name
			var newNames = {};
			$newTable.find('input.qty').each(function () {
				var n = $(this).attr('name');
				if (n) {
					newNames[n] = true;
				}
			});

			// 2. 移除已不存在的 rows
			$oldTable.find('tr.cart_item').each(function () {
				var $row = $(this);
				var name = $row.find('input.qty').attr('name');
				if (name && !newNames[name]) {
					$row.remove();
				}
			});

			// 3. 更新現有 rows 的 subtotal（若有顯示）
			$newTable.find('tr.cart_item').each(function () {
				var $newRow = $(this);
				var name = $newRow.find('input.qty').attr('name');
				if (!name) {
					return;
				}
				var $oldRow = $oldTable.find('input.qty[name="' + name + '"]').closest('tr.cart_item');
				if (!$oldRow.length) {
					return;
				}
				var $newSub = $newRow.find('.product-subtotal');
				var $oldSub = $oldRow.find('.product-subtotal');
				if ($newSub.length && $oldSub.length) {
					$oldSub.html($newSub.html());
				}
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
