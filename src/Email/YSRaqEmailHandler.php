<?php
/**
 * 電子郵件收件人管理
 *
 * @package YangSheep\RaqAddons\Email
 */

namespace YangSheep\RaqAddons\Email;

defined( 'ABSPATH' ) || exit;

/**
 * 管理報價請求電子郵件的收件人、CC/BCC 和產品資訊顯示
 */
final class YSRaqEmailHandler {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// 修改 YITH RAQ 郵件收件人
		add_filter( 'woocommerce_email_recipient_ywraq_email', array( $this, 'filter_email_recipients' ), 10, 2 );

		// 添加 CC/BCC 標頭
		add_filter( 'woocommerce_email_headers', array( $this, 'add_email_headers' ), 10, 3 );

		// 修改郵件中的產品資訊顯示
		add_action( 'yith_ywraq_email_before_raq_table', array( $this, 'email_before_table' ) );

		// 在郵件產品表格後添加自訂欄位資料
		add_action( 'yith_ywraq_email_after_raq_table', array( $this, 'email_after_table' ) );
	}

	/**
	 * 過濾電子郵件收件人
	 *
	 * 若設定了主要收件人，則完全覆蓋 YITH 預設收件人。
	 * 支援逗號分隔多組信箱。
	 *
	 * @param string $recipient 原始收件人。
	 * @param object $object    郵件物件。
	 * @return string
	 */
	public function filter_email_recipients( string $recipient, $object ): string {
		$custom_recipients = get_option( 'ys_raq_email_recipients', '' );

		if ( ! empty( $custom_recipients ) ) {
			return $custom_recipients;
		}

		return $recipient;
	}

	/**
	 * 添加 CC/BCC 標頭到郵件
	 *
	 * 支援逗號分隔多組信箱。
	 *
	 * @param string $headers  郵件標頭。
	 * @param string $email_id 郵件 ID。
	 * @param object $object   郵件物件。
	 * @return string
	 */
	public function add_email_headers( string $headers, string $email_id, $object ): string {
		if ( 'ywraq_email' !== $email_id ) {
			return $headers;
		}

		// CC 收件人
		if ( 'yes' === get_option( 'ys_raq_email_cc_enabled', 'no' ) ) {
			$cc_emails = get_option( 'ys_raq_email_cc_emails', '' );
			if ( ! empty( $cc_emails ) ) {
				$headers .= 'Cc: ' . sanitize_text_field( $cc_emails ) . "\r\n";
			}
		}

		// BCC 收件人
		if ( 'yes' === get_option( 'ys_raq_email_bcc_enabled', 'no' ) ) {
			$bcc_emails = get_option( 'ys_raq_email_bcc_emails', '' );
			if ( ! empty( $bcc_emails ) ) {
				$headers .= 'Bcc: ' . sanitize_text_field( $bcc_emails ) . "\r\n";
			}
		}

		return $headers;
	}

	/**
	 * 在郵件表格前添加自訂樣式以控制產品資訊顯示
	 *
	 * 免費版 YITH RAQ 郵件模板結構：
	 * - <th>/<td> 第 1 欄：Product（產品名稱）
	 * - <th>/<td> 第 2 欄：Quantity（數量）
	 * - <th>/<td> 第 3 欄：Subtotal（小計）
	 *
	 * 使用 #ys-raq-product-table 作為精確選擇器，透過在表格前注入 id wrapper 來鎖定。
	 */
	public function email_before_table(): void {
		$hide_styles = array();

		// 隱藏小計欄（郵件模板的第 3 欄）
		if ( 'yes' !== get_option( 'ys_raq_show_line_total', 'yes' ) ) {
			$hide_styles[] = '#ys-raq-product-table th:last-child, #ys-raq-product-table td:last-child { display: none !important; }';
		}

		// 隱藏數量欄（郵件模板的第 2 欄）
		if ( 'yes' !== get_option( 'ys_raq_show_quantity', 'yes' ) ) {
			$hide_styles[] = '#ys-raq-product-table th:nth-child(2), #ys-raq-product-table td:nth-child(2) { display: none !important; }';
		}

		if ( ! empty( $hide_styles ) ) {
			echo '<style>' . implode( ' ', $hide_styles ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// 注入一個 wrapper div 以提供精確的 CSS 選擇器
		// YITH 模板在此 hook 後緊接著輸出 <h2>Quote request</h2> 和產品 <table>
		// 使用 JavaScript-free 方式：在 <h2> 和 <table> 外包裹一個帶 id 的 div
		echo '<div id="ys-raq-product-table">';
	}

	/**
	 * 在郵件產品表格後輸出自訂欄位資料
	 *
	 * 郵件發送與表單提交在同一次請求中，因此可從 $_POST 讀取自訂欄位值。
	 */
	public function email_after_table(): void {
		// 關閉 email_before_table 中開啟的 wrapper div
		echo '</div>';

		$fields = get_option( 'ys_raq_custom_fields', array() );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return;
		}

		$rows = array();

		foreach ( $fields as $field_id => $field ) {
			if ( empty( $field['enabled'] ) || 'yes' !== $field['enabled'] ) {
				continue;
			}

			$field_type = $field['type'] ?? 'text';
			$name       = 'ys_raq_field_' . sanitize_key( $field_id );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';

			// textarea 欄位使用 sanitize_textarea_field 保留換行
			$value = 'textarea' === $field_type
				? sanitize_textarea_field( $raw )
				: sanitize_text_field( $raw );

			if ( '' === $value ) {
				continue;
			}

			$rows[] = array(
				'label' => $field['label'] ?? $field_id,
				'value' => $value,
			);
		}

		if ( empty( $rows ) ) {
			return;
		}

		echo '<h2>' . esc_html__( '附加資訊', 'ys-raq-addons' ) . '</h2>';
		echo '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #eee" border="1" bordercolor="#eee">';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td style="text-align:left;padding:8px 12px;border:1px solid #eee;font-weight:bold;width:30%;">' . esc_html( $row['label'] ) . '</td>';
			echo '<td style="text-align:left;padding:8px 12px;border:1px solid #eee;">' . nl2br( esc_html( $row['value'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}
