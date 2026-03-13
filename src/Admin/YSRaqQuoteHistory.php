<?php
/**
 * 報價歷史紀錄管理
 *
 * 攔截報價提交，儲存為 CPT，並提供後台查看功能
 *
 * @package YangSheep\RaqAddons\Admin
 */

namespace YangSheep\RaqAddons\Admin;

defined( 'ABSPATH' ) || exit;

final class YSRaqQuoteHistory {

	private static ?self $instance = null;

	/** @var string 自訂文章類型 */
	const POST_TYPE = 'ys_raq_quote';

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// 註冊 CPT
		add_action( 'init', array( $this, 'register_post_type' ) );

		// 攔截報價提交，儲存紀錄
		add_action( 'ywraq_process', array( $this, 'save_quote_request' ), 5 );

		// 也嘗試 hook send_raq_mail 以備用
		add_action( 'send_raq_mail_notification', array( $this, 'save_quote_from_mail' ), 5 );
	}

	/**
	 * 註冊自訂文章類型
	 */
	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, array(
			'labels'       => array(
				'name'          => __( '報價請求', 'ys-raq-addons' ),
				'singular_name' => __( '報價請求', 'ys-raq-addons' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
		) );
	}

	/**
	 * 攔截 ywraq_process 儲存報價紀錄
	 *
	 * @param array $args 報價請求資料。
	 */
	public function save_quote_request( $args ): void {
		if ( empty( $args ) ) {
			return;
		}

		$customer_name  = $args['user_name'] ?? '';
		$customer_email = $args['user_email'] ?? '';
		$message        = $args['user_message'] ?? '';
		$raq_content    = $args['raq_content'] ?? array();

		if ( empty( $customer_email ) && empty( $raq_content ) ) {
			return;
		}

		$this->create_quote_record( $customer_name, $customer_email, $message, $raq_content );
	}

	/**
	 * 從郵件通知攔截儲存（備用方案）
	 *
	 * @param array $args 郵件參數。
	 */
	public function save_quote_from_mail( $args ): void {
		// 防止重複儲存：檢查是否已在 ywraq_process 中儲存
		if ( did_action( 'ywraq_process' ) > 0 ) {
			return;
		}

		if ( empty( $args ) ) {
			return;
		}

		$customer_name  = $args['user_name'] ?? '';
		$customer_email = $args['user_email'] ?? '';
		$message        = $args['user_message'] ?? '';
		$raq_content    = $args['raq_content'] ?? array();

		$this->create_quote_record( $customer_name, $customer_email, $message, $raq_content );
	}

	/**
	 * 建立報價紀錄
	 *
	 * @param string $name    客戶名稱。
	 * @param string $email   客戶電子郵件。
	 * @param string $message 客戶留言。
	 * @param array  $content 報價產品清單。
	 */
	private function create_quote_record( string $name, string $email, string $message, array $content ): void {
		// 建立 quote 號碼
		$quote_number = 'RQ-' . gmdate( 'Ymd' ) . '-' . wp_rand( 1000, 9999 );

		$post_id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $quote_number,
			'post_status' => 'publish',
			'post_author' => get_current_user_id() ?: 0,
		) );

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		// 儲存報價資料
		update_post_meta( $post_id, '_ys_raq_customer_name', sanitize_text_field( $name ) );
		update_post_meta( $post_id, '_ys_raq_customer_email', sanitize_email( $email ) );
		update_post_meta( $post_id, '_ys_raq_customer_message', sanitize_textarea_field( $message ) );
		update_post_meta( $post_id, '_ys_raq_quote_number', $quote_number );
		update_post_meta( $post_id, '_ys_raq_status', 'new' );

		// 儲存產品清單（序列化）
		$products = array();
		foreach ( $content as $key => $item ) {
			$product_id = $item['product_id'] ?? 0;
			$product    = wc_get_product( $product_id );

			$products[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $item['variation_id'] ?? 0,
				'quantity'     => $item['quantity'] ?? 1,
				'product_name' => $product ? $product->get_name() : __( '（已刪除的產品）', 'ys-raq-addons' ),
				'product_sku'  => $product ? $product->get_sku() : '',
				'product_price' => $product ? $product->get_price() : 0,
			);
		}
		update_post_meta( $post_id, '_ys_raq_products', $products );

		// 計算總金額
		$total = 0;
		foreach ( $products as $item ) {
			$total += floatval( $item['product_price'] ) * intval( $item['quantity'] );
		}
		update_post_meta( $post_id, '_ys_raq_total', $total );

		// 儲存自訂表單欄位資料（含後端必填驗證）
		$custom_fields = get_option( 'ys_raq_custom_fields', array() );
		if ( ! empty( $custom_fields ) ) {
			$custom_data    = array();
			$missing_fields = array();

			foreach ( $custom_fields as $field_id => $field ) {
				if ( ( $field['enabled'] ?? 'no' ) !== 'yes' ) {
					continue;
				}

				$field_name = 'ys_raq_field_' . sanitize_key( $field_id );
				$field_type = $field['type'] ?? 'text';
				$raw        = isset( $_POST[ $field_name ] ) ? wp_unslash( $_POST[ $field_name ] ) : '';
				$raw_value  = 'textarea' === $field_type
					? sanitize_textarea_field( $raw )
					: sanitize_text_field( $raw );

				// 後端必填檢查
				if ( ( $field['required'] ?? 'no' ) === 'yes' && '' === $raw_value ) {
					$missing_fields[] = $field['label'] ?? $field_id;
				}

				if ( '' !== $raw_value ) {
					$custom_data[ $field_id ] = array(
						'label' => $field['label'] ?? $field_id,
						'value' => $raw_value,
					);
				}
			}

			// 記錄缺少的必填欄位（不阻斷流程，但存入 meta 供管理員知曉）
			if ( ! empty( $missing_fields ) ) {
				update_post_meta( $post_id, '_ys_raq_missing_required', $missing_fields );
			}

			if ( ! empty( $custom_data ) ) {
				update_post_meta( $post_id, '_ys_raq_custom_fields_data', $custom_data );
			}
		}

		/**
		 * 報價紀錄建立後觸發
		 *
		 * @param int   $post_id  紀錄 ID。
		 * @param array $products 產品清單。
		 */
		do_action( 'ys_raq_quote_saved', $post_id, $products );
	}

	/**
	 * 取得所有報價狀態
	 *
	 * @return array
	 */
	public static function get_statuses(): array {
		return array(
			'new'      => __( '新的', 'ys-raq-addons' ),
			'pending'  => __( '處理中', 'ys-raq-addons' ),
			'replied'  => __( '已回覆', 'ys-raq-addons' ),
			'expired'  => __( '已過期', 'ys-raq-addons' ),
			'closed'   => __( '已關閉', 'ys-raq-addons' ),
		);
	}

	/**
	 * 取得報價數量（依狀態）
	 *
	 * @param string $status 報價狀態。
	 * @return int
	 */
	public static function get_count_by_status( string $status = '' ): int {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( ! empty( $status ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ys_raq_status',
					'value' => $status,
				),
			);
		}

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}
}
