<?php
/**
 * Shortcode 註冊
 *
 * @package YangSheep\RaqAddons
 */

namespace YangSheep\RaqAddons;

defined( 'ABSPATH' ) || exit;

class YSRaqShortcodes {

	/**
	 * 初始化 Shortcodes
	 */
	public static function init(): void {
		add_shortcode( 'ys_raq_mini_cart', array( __CLASS__, 'mini_cart' ) );
		add_shortcode( 'ys_raq_count', array( __CLASS__, 'raq_count' ) );
	}

	/**
	 * 迷你詢價車 Shortcode
	 *
	 * 用法：[ys_raq_mini_cart title="詢價清單" show_thumbnail="1" show_price="1" show_quantity="1" show_variations="1" button_label="查看清單"]
	 *
	 * @param array|string $atts Shortcode 屬性。
	 * @return string
	 */
	public static function mini_cart( array|string $atts = array() ): string {
		$defaults = Widgets\YSRaqMiniCartWidget::get_defaults();

		$args = shortcode_atts( $defaults, $atts, 'ys_raq_mini_cart' );

		ob_start();
		the_widget( Widgets\YSRaqMiniCartWidget::class, $args );
		return ob_get_clean();
	}

	/**
	 * 詢價數量 Shortcode
	 *
	 * 用法：[ys_raq_count show_text="1" item_name="項商品" item_plural_name="項商品" link="1"]
	 *
	 * @param array|string $atts Shortcode 屬性。
	 * @return string
	 */
	public static function raq_count( array|string $atts = array() ): string {
		$args = shortcode_atts(
			array(
				'show_text'        => 1,
				'item_name'        => __( '項商品', 'ys-raq-addons' ),
				'item_plural_name' => __( '項商品', 'ys-raq-addons' ),
				'link'             => 1,
			),
			$atts,
			'ys_raq_count'
		);

		$num_items = count( \YITH_Request_Quote()->get_raq_return() );
		$text      = '';

		if ( $args['show_text'] ) {
			$text = sprintf( ' %s', esc_html( $num_items <= 1 ? $args['item_name'] : $args['item_plural_name'] ) );
		}

		$output = sprintf(
			'<span class="ys-raq-count-shortcode"><span class="ys-raq-count">%d</span>%s</span>',
			$num_items,
			$text
		);

		if ( $args['link'] ) {
			$raq_url = \YITH_Request_Quote()->get_raq_page_url();
			$output  = sprintf( '<a href="%s" class="ys-raq-count-link">%s</a>', esc_url( $raq_url ), $output );
		}

		return $output;
	}
}
