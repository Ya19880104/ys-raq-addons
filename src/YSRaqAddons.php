<?php
/**
 * 外掛主類別（Singleton）
 *
 * @package YangSheep\RaqAddons
 */

namespace YangSheep\RaqAddons;

defined( 'ABSPATH' ) || exit;

final class YSRaqAddons {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * 初始化外掛功能
	 */
	public function init(): void {
		// 檢查 YITH RAQ 是否啟用
		if ( ! function_exists( 'YITH_Request_Quote' ) ) {
			add_action( 'admin_notices', function (): void {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'YS RAQ Addons 需要 YITH WooCommerce Request a Quote 外掛才能運作。', 'ys-raq-addons' );
				echo '</p></div>';
			} );
			return;
		}

		load_plugin_textdomain( 'ys-raq-addons', false, dirname( YS_RAQ_ADDONS_BASENAME ) . '/languages' );

		// 後台管理
		if ( is_admin() ) {
			Admin\YSRaqSettings::get_instance();
		}

		// 前端功能
		$this->init_frontend();

		// 電子郵件處理
		Email\YSRaqEmailHandler::get_instance();

		// 報價歷史紀錄（攔截報價提交）
		Admin\YSRaqQuoteHistory::get_instance();

		// AJAX handlers
		YSRaqAjax::init();
	}

	/**
	 * 初始化前端功能
	 */
	private function init_frontend(): void {
		// Widget
		add_action( 'widgets_init', function (): void {
			register_widget( Widgets\YSRaqMiniCartWidget::class );
		} );

		// Shortcodes
		YSRaqShortcodes::init();

		// 前端資源
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// 報價頁面增強
		Frontend\YSRaqQuotePage::get_instance();
	}

	/**
	 * 載入前端 CSS 與 JS
	 */
	public function enqueue_frontend_assets(): void {
		// Mini Cart 樣式與腳本
		wp_enqueue_style(
			'ys-raq-mini-cart',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/css/ys-raq-mini-cart.css',
			array(),
			YS_RAQ_ADDONS_VERSION
		);

		wp_enqueue_script(
			'ys-raq-mini-cart',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/js/ys-raq-mini-cart.js',
			array( 'jquery' ),
			YS_RAQ_ADDONS_VERSION,
			true
		);

		// 刷新延遲（可由後台調整）— MutationObserver / YITH 事件觸發後，先等多久做第一輪刷新
		$refresh_delay = absint( get_option( 'ys_raq_mini_cart_refresh_delay', 600 ) );
		$refresh_delay = max( 200, min( 3000, $refresh_delay ) );

		// 安全刷新延遲 — 第一輪之後再做一次保險刷新，確保 YITH DOM 完全更新後 badge 正確
		$settle_delay = absint( get_option( 'ys_raq_mini_cart_settle_delay', 1500 ) );
		$settle_delay = max( 500, min( 5000, $settle_delay ) );

		wp_localize_script( 'ys-raq-mini-cart', 'ys_raq_params', array(
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'ys-raq-mini-cart' ),
			'raq_url'      => function_exists( 'YITH_Request_Quote' ) ? \YITH_Request_Quote()->get_raq_page_url() : '',
			'refreshDelay' => $refresh_delay,
			'settleDelay'  => $settle_delay,
			'i18n'         => array(
				'empty_list' => esc_html( get_option( 'ys_raq_mini_cart_empty_text', __( '詢價清單是空的', 'ys-raq-addons' ) ) ),
			),
		) );
	}
}
