<?php
/**
 * Mini Cart Widget for YITH Request a Quote
 *
 * 提供類似 WooCommerce Mini Cart 的詢價清單小工具。
 *
 * @package YangSheep\RaqAddons\Widgets
 */

namespace YangSheep\RaqAddons\Widgets;

defined( 'ABSPATH' ) || exit;

class YSRaqMiniCartWidget extends \WP_Widget {

	/**
	 * 建構子
	 */
	public function __construct() {
		parent::__construct(
			'ys_raq_mini_cart',
			esc_html__( 'YS 迷你詢價車', 'ys-raq-addons' ),
			array(
				'classname'   => 'woocommerce ys-raq-mini-cart-widget',
				'description' => esc_html__( '顯示詢價清單的迷你購物車（適用於 YITH RAQ 免費版）', 'ys-raq-addons' ),
			)
		);
	}

	/**
	 * 前台顯示
	 *
	 * @param array $args     Widget 區域參數。
	 * @param array $instance Widget 設定。
	 */
	public function widget( $args, $instance ): void {
		$defaults = self::get_defaults();
		$instance = wp_parse_args( $instance, $defaults );

		$title             = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$show_title_inside = (bool) $instance['show_title_inside'];
		$show_thumbnail    = (bool) $instance['show_thumbnail'];
		$show_quantity     = (bool) $instance['show_quantity'];
		$show_variations   = (bool) $instance['show_variations'];
		// qty_label 為選填：空字串代表「改讀後台 option」；有值則由 shortcode/widget 覆寫
		$qty_label         = isset( $instance['qty_label'] ) ? (string) $instance['qty_label'] : '';

		if ( ! apply_filters( 'ys_raq_before_print_mini_cart', true ) ) {
			return;
		}

		$raq_content = \YITH_Request_Quote()->get_raq_return();

		$template_args = array(
			'raq_content'       => $raq_content,
			'title'             => $title,
			'item_name'         => $instance['item_name'],
			'item_plural_name'  => $instance['item_plural_name'],
			'button_label'      => $instance['button_label'],
			'show_title_inside' => $show_title_inside,
			'show_thumbnail'    => $show_thumbnail,
			'show_quantity'     => $show_quantity,
			'show_variations'   => $show_variations,
			'qty_label'         => $qty_label,
		);

		// 將 instance 資料編碼到 data attribute，供 AJAX 刷新使用（JSON 不會有 &/=/+ 問題）
		$data_instance = wp_json_encode( $instance );

		echo wp_kses_post( $args['before_widget'] );
		echo '<div class="ys-raq-mini-cart-wrapper" data-instance="' . esc_attr( $data_instance ) . '">';

		self::render_template( $template_args );

		echo '</div>';
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * 渲染模板
	 *
	 * @param array $args 模板參數。
	 */
	public static function render_template( array $args ): void {
		// 優先從主題讀取 override
		$template = locate_template( 'ys-raq-addons/widgets/mini-cart.php' );
		if ( ! $template ) {
			$template = YS_RAQ_ADDONS_TEMPLATE_PATH . 'widgets/mini-cart.php';
		}

		if ( file_exists( $template ) ) {
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $template;
		}
	}

	/**
	 * 後台表單
	 *
	 * @param array $instance Widget 設定。
	 */
	public function form( $instance ): void {
		$defaults = self::get_defaults();
		$instance = wp_parse_args( (array) $instance, $defaults );

		$fields = array(
			'title'             => array( 'label' => __( '標題：', 'ys-raq-addons' ), 'type' => 'text' ),
			'item_name'         => array( 'label' => __( '品項名稱（單數）：', 'ys-raq-addons' ), 'type' => 'text' ),
			'item_plural_name'  => array( 'label' => __( '品項名稱（複數）：', 'ys-raq-addons' ), 'type' => 'text' ),
			'button_label'      => array( 'label' => __( '按鈕標籤：', 'ys-raq-addons' ), 'type' => 'text' ),
			'show_title_inside' => array( 'label' => __( '在面板內顯示標題', 'ys-raq-addons' ), 'type' => 'checkbox' ),
			'show_thumbnail'    => array( 'label' => __( '顯示產品縮圖', 'ys-raq-addons' ), 'type' => 'checkbox' ),
			'show_quantity'     => array( 'label' => __( '顯示數量', 'ys-raq-addons' ), 'type' => 'checkbox' ),
			'show_variations'   => array( 'label' => __( '顯示變體資訊', 'ys-raq-addons' ), 'type' => 'checkbox' ),
		);

		foreach ( $fields as $key => $field ) {
			if ( 'text' === $field['type'] ) {
				printf(
					'<p><label for="%1$s">%2$s</label><input type="text" class="widefat" id="%1$s" name="%3$s" value="%4$s" /></p>',
					esc_attr( $this->get_field_id( $key ) ),
					esc_html( $field['label'] ),
					esc_attr( $this->get_field_name( $key ) ),
					esc_attr( $instance[ $key ] )
				);
			} else {
				printf(
					'<p><label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label></p>',
					esc_attr( $this->get_field_id( $key ) ),
					esc_attr( $this->get_field_name( $key ) ),
					checked( $instance[ $key ], 1, false ),
					esc_html( $field['label'] )
				);
			}
		}
	}

	/**
	 * 儲存設定
	 *
	 * @param array $new_instance 新設定。
	 * @param array $old_instance 舊設定。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance = array();

		$text_fields = array( 'title', 'item_name', 'item_plural_name', 'button_label' );
		foreach ( $text_fields as $field ) {
			$instance[ $field ] = wp_strip_all_tags( stripslashes( $new_instance[ $field ] ?? '' ) );
		}

		$checkbox_fields = array( 'show_title_inside', 'show_thumbnail', 'show_quantity', 'show_variations' );
		foreach ( $checkbox_fields as $field ) {
			$instance[ $field ] = ! empty( $new_instance[ $field ] ) ? 1 : 0;
		}

		return $instance;
	}

	/**
	 * 預設值
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'title'             => __( '詢價清單', 'ys-raq-addons' ),
			'item_name'         => __( '項商品', 'ys-raq-addons' ),
			'item_plural_name'  => __( '項商品', 'ys-raq-addons' ),
			'button_label'      => get_option( 'ys_raq_mini_cart_button_label', __( '查看詢價清單', 'ys-raq-addons' ) ),
			'show_title_inside' => 0,
			'show_thumbnail'    => 1,
			'show_quantity'     => 1,
			'show_variations'   => 1,
			'qty_label'         => '', // 空字串 = 改讀後台 option（ys_raq_mini_cart_qty_label）
		);
	}
}
