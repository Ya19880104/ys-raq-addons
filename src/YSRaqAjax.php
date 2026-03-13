<?php
/**
 * AJAX Handler
 *
 * 處理 Mini Cart 的即時刷新與移除項目。
 *
 * @package YangSheep\RaqAddons
 */

namespace YangSheep\RaqAddons;

defined( 'ABSPATH' ) || exit;

class YSRaqAjax {

	/**
	 * 初始化 AJAX hooks
	 */
	public static function init(): void {
		// 前後台共用
		$actions = array(
			'ys_raq_refresh_mini_cart',
			'ys_raq_remove_item',
		);

		foreach ( $actions as $action ) {
			add_action( "wp_ajax_{$action}", array( __CLASS__, $action ) );
			add_action( "wp_ajax_nopriv_{$action}", array( __CLASS__, $action ) );
		}

		// 輕量計數（不需 nonce，快取環境下也能正常運作）
		add_action( 'wp_ajax_ys_raq_get_count', array( __CLASS__, 'ys_raq_get_count' ) );
		add_action( 'wp_ajax_nopriv_ys_raq_get_count', array( __CLASS__, 'ys_raq_get_count' ) );

		// 僅後台
		add_action( 'wp_ajax_ys_raq_update_status', array( __CLASS__, 'ys_raq_update_status' ) );
	}

	/**
	 * AJAX：刷新 Mini Cart 內容
	 */
	public static function ys_raq_refresh_mini_cart(): void {
		check_ajax_referer( 'ys-raq-mini-cart', 'nonce' );

		$posted   = wp_unslash( $_POST );
		$defaults = Widgets\YSRaqMiniCartWidget::get_defaults();

		$args = array(
			'raq_content'       => \YITH_Request_Quote()->get_raq_return(),
			'title'             => sanitize_text_field( $posted['title'] ?? $defaults['title'] ),
			'item_name'         => sanitize_text_field( $posted['item_name'] ?? $defaults['item_name'] ),
			'item_plural_name'  => sanitize_text_field( $posted['item_plural_name'] ?? $defaults['item_plural_name'] ),
			'button_label'      => sanitize_text_field( $posted['button_label'] ?? $defaults['button_label'] ),
			'show_title_inside' => (bool) ( $posted['show_title_inside'] ?? $defaults['show_title_inside'] ),
			'show_thumbnail'    => (bool) ( $posted['show_thumbnail'] ?? $defaults['show_thumbnail'] ),
			'show_price'        => (bool) ( $posted['show_price'] ?? $defaults['show_price'] ),
			'show_quantity'     => (bool) ( $posted['show_quantity'] ?? $defaults['show_quantity'] ),
			'show_variations'   => (bool) ( $posted['show_variations'] ?? $defaults['show_variations'] ),
		);

		ob_start();
		Widgets\YSRaqMiniCartWidget::render_template( $args );
		$html = ob_get_clean();

		$num_items = is_array( $args['raq_content'] ) ? count( $args['raq_content'] ) : 0;

		wp_send_json_success( array(
			'html'  => $html,
			'count' => $num_items,
		) );
	}

	/**
	 * AJAX：移除詢價清單中的項目
	 */
	public static function ys_raq_remove_item(): void {
		check_ajax_referer( 'ys-raq-mini-cart', 'nonce' );

		$key        = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );

		if ( empty( $key ) || ! $product_id ) {
			wp_send_json_error( array( 'message' => __( '無效的請求', 'ys-raq-addons' ) ) );
		}

		$raq = \YITH_Request_Quote();
		$raq->remove_item( $key );

		$raq_content = $raq->get_raq_return();
		$num_items   = is_array( $raq_content ) ? count( $raq_content ) : 0;

		wp_send_json_success( array(
			'count'      => $num_items,
			'product_id' => $product_id,
		) );
	}

	/**
	 * AJAX：更新報價狀態（後台）
	 */
	public static function ys_raq_update_status(): void {
		check_ajax_referer( 'ys-raq-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'ys-raq-addons' ) ) );
		}

		$quote_id = absint( $_POST['quote_id'] ?? 0 );
		$status   = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $quote_id || empty( $status ) ) {
			wp_send_json_error( array( 'message' => __( '無效的請求', 'ys-raq-addons' ) ) );
		}

		$valid_statuses = array_keys( Admin\YSRaqQuoteHistory::get_statuses() );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( '無效的狀態', 'ys-raq-addons' ) ) );
		}

		// 確認目標是報價紀錄
		$post = get_post( $quote_id );
		if ( ! $post || Admin\YSRaqQuoteHistory::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( '找不到此報價紀錄', 'ys-raq-addons' ) ) );
		}

		update_post_meta( $quote_id, '_ys_raq_status', $status );

		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * AJAX：取得詢價清單數量（輕量，不需 nonce）
	 *
	 * 此 endpoint 不需要 nonce 驗證，因為：
	 * 1. 僅讀取 session 資料，不修改任何內容
	 * 2. 在頁面快取環境下，nonce 可能過期導致完整刷新失敗
	 * 3. 作為 fallback 確保 badge 數字正確
	 */
	public static function ys_raq_get_count(): void {
		$raq_content = \YITH_Request_Quote()->get_raq_return();
		$num_items   = is_array( $raq_content ) ? count( $raq_content ) : 0;

		wp_send_json_success( array( 'count' => $num_items ) );
	}
}
