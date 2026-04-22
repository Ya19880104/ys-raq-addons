<?php
/**
 * YS RAQ Addons
 * 為 YITH WooCommerce Request a Quote 免費版增強功能。
 *
 * @link              https://yangsheep.com.tw
 * @since             1.0.0
 * @package           YangSheep\RaqAddons
 *
 * @wordpress-plugin
 * Plugin Name:       YS RAQ Addons
 * Description:       為 YITH WooCommerce Request a Quote 免費版新增 Mini Cart、收件人管理、產品資訊顯示控制、自訂表單欄位、報價歷史等功能。
 * Version:           2.3.10
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            YANGSHEEP DESIGN
 * Author URI:        https://yangsheep.com.tw
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ys-raq-addons
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   9.6
 */

defined( 'ABSPATH' ) || exit;

// 外掛常數
define( 'YS_RAQ_ADDONS_VERSION', '2.3.10' );
define( 'YS_RAQ_ADDONS_PLUGIN_FILE', __FILE__ );
define( 'YS_RAQ_ADDONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_RAQ_ADDONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_RAQ_ADDONS_TEMPLATE_PATH', plugin_dir_path( __FILE__ ) . 'templates/' );
define( 'YS_RAQ_ADDONS_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoloader（載入 hub-client 等 vendor 套件）
if ( file_exists( YS_RAQ_ADDONS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once YS_RAQ_ADDONS_PLUGIN_DIR . 'vendor/autoload.php';
}

// PSR-4 Fallback Autoloader（永遠註冊，確保自身 namespace 可載入）
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'YangSheep\\RaqAddons\\';
	$base_dir = YS_RAQ_ADDONS_PLUGIN_DIR . 'src/';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// HPOS 相容性宣告
add_action( 'before_woocommerce_init', function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// 外掛設定連結
add_filter( 'plugin_action_links_' . YS_RAQ_ADDONS_BASENAME, function ( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=ys-raq-addons' ),
		__( '設定', 'ys-raq-addons' )
	);
	array_unshift( $links, $settings_link );
	return $links;
} );

// YS Plugin Hub Client 註冊
add_action( 'plugins_loaded', function () {
	if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
		\YangSheep\PluginHubClient\YSPluginHubClient::register( array(
			'slug'        => 'ys-raq-addons',
			'version'     => YS_RAQ_ADDONS_VERSION,
			'plugin_file' => __FILE__,
			'name'        => 'YS RAQ Addons',
		) );
	}
}, 5 );

// 啟動外掛
YangSheep\RaqAddons\YSRaqAddons::instance();
