/**
 * YS RAQ Mini Cart JavaScript
 *
 * 處理迷你詢價車的 AJAX 刷新、移除和行動裝置互動。
 *
 * 同步策略（類似 WooCommerce cart-fragments）：
 * 1. 事件驅動：MutationObserver + YITH 自訂事件 → 即時刷新
 * 2. 跨頁同步：sessionStorage 儲存 count，頁面載入時比對
 * 3. 跨分頁同步：監聽 storage 事件，其他分頁更新時自動同步
 * 4. Fallback：延遲輕量計數檢查（nonce 過期時也能用）
 *
 * @package YangSheep\RaqAddons
 * @version 1.2.0
 */
(function ($) {
	'use strict';

	if (typeof ys_raq_params === 'undefined') {
		return;
	}

	var STORAGE_COUNT_KEY = 'ys_raq_count';
	var STORAGE_TIME_KEY = 'ys_raq_count_time';
	var CACHE_LIFETIME = 5 * 60 * 1000; // 5 分鐘快取有效期

	var isMobile = window.matchMedia('(max-width: 768px)').matches;
	var refreshTimer = null;
	var lastKnownCount = -1;
	var supportsStorage = (function () {
		try {
			sessionStorage.setItem('_ys_test', '1');
			sessionStorage.removeItem('_ys_test');
			return true;
		} catch (e) {
			return false;
		}
	})();

	// ─── 工具函式 ──────────────────────────────

	/**
	 * 防抖刷新
	 */
	function debouncedRefresh() {
		if (refreshTimer) {
			clearTimeout(refreshTimer);
		}
		refreshTimer = setTimeout(function () {
			refreshTimer = null;
			ysRaqRefreshWidgets();
		}, 500);
	}

	/**
	 * 更新頁面上所有 badge 和 count 元素
	 */
	function updateBadges(count) {
		count = parseInt(count, 10) || 0;

		if (lastKnownCount === count) {
			return;
		}
		lastKnownCount = count;

		$(document).find('.ys-raq-badge').text(count);
		$(document).find('.ys-raq-count').text(count);

		$(document).find('.ys-raq-trigger').each(function () {
			if (count > 0) {
				$(this).removeClass('ys-raq-empty');
			} else {
				$(this).addClass('ys-raq-empty');
			}
		});

		// 儲存到 sessionStorage，供跨頁 / 跨分頁同步使用
		saveCountToStorage(count);
	}

	/**
	 * 儲存計數到 sessionStorage
	 */
	function saveCountToStorage(count) {
		if (!supportsStorage) {
			return;
		}
		try {
			sessionStorage.setItem(STORAGE_COUNT_KEY, count);
			sessionStorage.setItem(STORAGE_TIME_KEY, new Date().getTime());
			// 同時寫入 localStorage 觸發跨分頁 storage 事件
			localStorage.setItem(STORAGE_COUNT_KEY, count);
		} catch (e) {
			// Storage 滿或不可用，忽略
		}
	}

	/**
	 * 從 sessionStorage 讀取快取的計數
	 * @return {number|false} 快取的數量，或 false 表示無快取/已過期
	 */
	function getCountFromStorage() {
		if (!supportsStorage) {
			return false;
		}
		try {
			var count = sessionStorage.getItem(STORAGE_COUNT_KEY);
			var time = sessionStorage.getItem(STORAGE_TIME_KEY);

			if (count === null || time === null) {
				return false;
			}

			// 檢查是否過期
			if (new Date().getTime() - parseInt(time, 10) > CACHE_LIFETIME) {
				return false;
			}

			return parseInt(count, 10);
		} catch (e) {
			return false;
		}
	}

	// ─── AJAX 刷新 ─────────────────────────────

	/**
	 * 輕量計數刷新（不需 nonce，快取環境下也能運作）
	 */
	function ysRaqRefreshCount() {
		$.ajax({
			type: 'POST',
			url: ys_raq_params.ajaxurl,
			data: { action: 'ys_raq_get_count' },
			success: function (response) {
				if (response.success) {
					updateBadges(response.data.count);
				}
			}
		});
	}

	/**
	 * 刷新所有 Mini Cart Widget
	 */
	function ysRaqRefreshWidgets() {
		var $widgets = $(document).find('.ys-raq-mini-cart-wrapper');

		if ($widgets.length === 0) {
			ysRaqRefreshCount();
			return;
		}

		$widgets.each(function () {
			var $wrapper = $(this);
			var instanceData = $wrapper.data('instance');

			var data = {
				action: 'ys_raq_refresh_mini_cart',
				nonce: ys_raq_params.nonce
			};

			if (instanceData) {
				try {
					var parsed = typeof instanceData === 'object' ? instanceData : JSON.parse(instanceData);
					$.extend(data, parsed);
				} catch (e) {}
			}

			$.ajax({
				type: 'POST',
				url: ys_raq_params.ajaxurl,
				data: data,
				success: function (response) {
					if (response.success) {
						$wrapper.html(response.data.html);
						updateBadges(response.data.count);
						$(document).trigger('ys_raq_mini_cart_refreshed', [response.data]);
					}
				},
				error: function () {
					// nonce 過期，fallback 到輕量計數
					ysRaqRefreshCount();
				}
			});
		});
	}

	// ─── 初始化同步 ────────────────────────────

	/**
	 * 頁面載入同步策略（類似 WC cart-fragments）：
	 *
	 * 1. 先用 sessionStorage 快取立即更新 badge（零延遲）
	 * 2. 然後做一次 AJAX 取得真實數量
	 * 3. 3 秒後再做一次 fallback 檢查
	 */
	(function initSync() {
		// Step 1: 立即用 sessionStorage 的快取值更新（避免閃爍 0）
		var cachedCount = getCountFromStorage();
		if (cachedCount !== false) {
			updateBadges(cachedCount);
		}

		// Step 2: AJAX 取得真實數量
		ysRaqRefreshWidgets();

		// Step 3: 延遲 fallback（防止 nonce 過期導致 Step 2 失敗）
		setTimeout(function () {
			ysRaqRefreshCount();
		}, 3000);
	})();

	// ─── 跨分頁同步 ────────────────────────────

	/**
	 * 監聽 localStorage 的 storage 事件
	 *
	 * 當使用者在 A 分頁加入商品，B 分頁會透過此事件即時更新 badge。
	 * 這是 WooCommerce cart-fragments 使用的同一機制。
	 */
	if (supportsStorage) {
		window.addEventListener('storage', function (e) {
			if (e.key === STORAGE_COUNT_KEY && e.newValue !== null) {
				var newCount = parseInt(e.newValue, 10);
				if (!isNaN(newCount)) {
					updateBadges(newCount);
				}
			}
		});
	}

	// ─── 事件驅動刷新 ──────────────────────────

	/**
	 * MutationObserver：偵測 YITH RAQ 免費版的 DOM 變化
	 */
	var observer = new MutationObserver(function (mutations) {
		for (var i = 0; i < mutations.length; i++) {
			var mutation = mutations[i];

			if (mutation.addedNodes && mutation.addedNodes.length) {
				for (var j = 0; j < mutation.addedNodes.length; j++) {
					var node = mutation.addedNodes[j];
					if (node.nodeType !== 1) continue;

					if (node.classList && node.classList.contains('yith_ywraq_add_item_response_message')) {
						debouncedRefresh();
						return;
					}
				}
			}

			if (mutation.removedNodes && mutation.removedNodes.length) {
				for (var k = 0; k < mutation.removedNodes.length; k++) {
					var removed = mutation.removedNodes[k];
					if (removed.nodeType !== 1) continue;

					if (removed.classList && removed.classList.contains('cart_item')) {
						debouncedRefresh();
						return;
					}
				}
			}
		}
	});

	observer.observe(document.body, {
		childList: true,
		subtree: true
	});

	/**
	 * 相容 YITH 付費版自訂事件
	 */
	$(document).on('yith_wwraq_added_successfully yith_wwraq_removed_successfully', function () {
		debouncedRefresh();
	});

	/**
	 * 監聽 YITH 免費版 AJAX 完成
	 *
	 * YITH 免費版的 add-to-quote 是透過 AJAX，完成後我們取得最新計數。
	 */
	$(document).ajaxComplete(function (event, xhr, settings) {
		if (!settings || !settings.data) {
			return;
		}

		var data = typeof settings.data === 'string' ? settings.data : '';

		if (data.indexOf('yith_ywraq_action') !== -1 ||
			data.indexOf('add_item') !== -1 ||
			data.indexOf('remove_item') !== -1) {
			setTimeout(function () {
				ysRaqRefreshCount();
			}, 800);
		}
	});

	// ─── 行動裝置互動 ─────────────────────────

	$(document).on('click', '.ys-raq-trigger-link', function (e) {
		if (!isMobile) {
			return;
		}
		e.preventDefault();
		var $wrapper = $(this).closest('.ys-raq-mini-cart-wrapper');
		$wrapper.addClass('ys-raq-open');
		$('body').addClass('ys-raq-body-locked');
	});

	$(document).on('click', '.ys-raq-dropdown-close', function () {
		var $wrapper = $(this).closest('.ys-raq-mini-cart-wrapper');
		$wrapper.removeClass('ys-raq-open');
		$('body').removeClass('ys-raq-body-locked');
	});

	$(document).on('click', '.ys-raq-dropdown', function (e) {
		if ($(e.target).hasClass('ys-raq-dropdown')) {
			var $wrapper = $(this).closest('.ys-raq-mini-cart-wrapper');
			$wrapper.removeClass('ys-raq-open');
			$('body').removeClass('ys-raq-body-locked');
		}
	});

	// ─── Mini Cart 項目移除 ────────────────────

	$(document).on('click', '.ys-raq-item-remove', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $item = $btn.closest('.ys-raq-list-item');
		var key = $btn.data('remove-item');
		var productId = $btn.data('product_id');

		$item.css('opacity', 0.4);

		$.ajax({
			type: 'POST',
			url: ys_raq_params.ajaxurl,
			data: {
				action: 'ys_raq_remove_item',
				nonce: ys_raq_params.nonce,
				key: key,
				product_id: productId
			},
			success: function (response) {
				if (response.success) {
					ysRaqRefreshWidgets();

					var $quote = $(document).find('.yith-ywraq-add-to-quote.add-to-quote-' + productId);
					$quote.find('.yith_ywraq_add_item_response_message').remove();
					$quote.find('.yith_ywraq_add_item_browse_message').remove();
					$quote.find('.add-request-quote-button').parent().show().removeClass('addedd');

					$(document).trigger('ys_raq_item_removed', [response.data]);
				} else {
					$item.css('opacity', 1);
				}
			},
			error: function () {
				$item.css('opacity', 1);
			}
		});
	});

	// ─── 視窗大小 ──────────────────────────────

	window.addEventListener('resize', function () {
		isMobile = window.matchMedia('(max-width: 768px)').matches;
		if (!isMobile) {
			$('.ys-raq-mini-cart-wrapper').removeClass('ys-raq-open');
			$('body').removeClass('ys-raq-body-locked');
		}
	});

})(jQuery);
