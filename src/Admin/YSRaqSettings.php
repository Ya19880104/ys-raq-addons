<?php
/**
 * 後台設定頁面
 *
 * @package YangSheep\RaqAddons\Admin
 */

namespace YangSheep\RaqAddons\Admin;

defined( 'ABSPATH' ) || exit;

final class YSRaqSettings {

	private static ?self $instance = null;

	private string $option_group = 'ys_raq_addons';

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_toolbox_menu' ), 24 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'ys_toolbox_plugins', array( $this, 'register_toolbox_card' ) );

		// AJAX: 表單欄位管理
		add_action( 'wp_ajax_ys_raq_save_field', array( $this, 'ajax_save_field' ) );
		add_action( 'wp_ajax_ys_raq_delete_field', array( $this, 'ajax_delete_field' ) );
		add_action( 'wp_ajax_ys_raq_toggle_field', array( $this, 'ajax_toggle_field' ) );
		add_action( 'wp_ajax_ys_raq_reorder_fields', array( $this, 'ajax_reorder_fields' ) );
	}

	// ────────────────────────────────────────────
	// 電商工具箱整合
	// ────────────────────────────────────────────

	public function register_toolbox_card( array $plugins ): array {
		$plugins[] = array(
			'name'    => 'RAQ 擴充套件',
			'version' => YS_RAQ_ADDONS_VERSION,
			'icon'    => 'dashicons-format-chat',
			'desc'    => '為 YITH Request a Quote 新增收件人管理、顯示控制、自訂欄位、報價歷史等功能。',
			'url'     => admin_url( 'admin.php?page=ys-raq-addons' ),
		);
		return $plugins;
	}

	public function register_toolbox_menu(): void {
		global $menu;

		$toolbox_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'ys-toolbox' === $item[2] ) {
					$toolbox_exists = true;
					break;
				}
			}
		}

		if ( ! $toolbox_exists ) {
			$welcome_callback = $this->get_toolbox_welcome_callback();

			add_menu_page(
				__( '電商工具箱', 'ys-raq-addons' ),
				__( '電商工具箱', 'ys-raq-addons' ),
				'manage_options',
				'ys-toolbox',
				$welcome_callback,
				'dashicons-store',
				56
			);

			add_submenu_page(
				'ys-toolbox',
				__( '電商工具箱', 'ys-raq-addons' ),
				__( '總覽', 'ys-raq-addons' ),
				'manage_options',
				'ys-toolbox',
				$welcome_callback
			);
		}

		// RAQ 擴充設定
		add_submenu_page(
			'ys-toolbox',
			__( 'RAQ 擴充套件', 'ys-raq-addons' ),
			__( 'RAQ 擴充', 'ys-raq-addons' ),
			'manage_options',
			'ys-raq-addons',
			array( $this, 'render_settings_page' )
		);

		// 報價單紀錄（獨立頁面）
		$new_count   = YSRaqQuoteHistory::get_count_by_status( 'new' );
		$menu_suffix = $new_count > 0
			? sprintf( ' <span class="awaiting-mod">%d</span>', $new_count )
			: '';

		add_submenu_page(
			'ys-toolbox',
			__( '報價單紀錄', 'ys-raq-addons' ),
			__( '報價單紀錄', 'ys-raq-addons' ) . $menu_suffix,
			'manage_options',
			'ys-raq-quotes',
			array( $this, 'render_quotes_page' )
		);
	}

	private function get_toolbox_welcome_callback(): callable {
		$fallback_classes = array(
			'\YangSheep\ShoplinePayment\Admin\YSAdminSettings',
			'\YangSheep\PayNow\Shipping\Settings\YSSettingsTab',
			'\YangSheep\CheckoutOptimizer\Admin\YSCheckoutSettings',
		);

		foreach ( $fallback_classes as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'render_toolbox_welcome' ) ) {
				return array( $class, 'render_toolbox_welcome' );
			}
		}

		return array( __CLASS__, 'render_toolbox_welcome' );
	}

	// ────────────────────────────────────────────
	// 腳本載入
	// ────────────────────────────────────────────

	public function enqueue_admin_scripts( string $hook ): void {
		if ( false === strpos( $hook, 'ys-toolbox' ) && false === strpos( $hook, 'ys-raq-addons' ) && false === strpos( $hook, 'ys-raq-quotes' ) ) {
			return;
		}

		$css_file = YS_RAQ_ADDONS_PLUGIN_DIR . 'assets/css/ys-raq-addons-admin.css';
		$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : YS_RAQ_ADDONS_VERSION;

		wp_enqueue_style(
			'ys-raq-addons-admin',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/css/ys-raq-addons-admin.css',
			array(),
			$css_ver
		);

		$js_file = YS_RAQ_ADDONS_PLUGIN_DIR . 'assets/js/ys-raq-addons-admin.js';
		$js_ver  = file_exists( $js_file ) ? (string) filemtime( $js_file ) : YS_RAQ_ADDONS_VERSION;

		wp_enqueue_script(
			'ys-raq-addons-admin',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/js/ys-raq-addons-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			$js_ver,
			true
		);

		wp_localize_script( 'ys-raq-addons-admin', 'ys_raq_admin', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ys-raq-admin' ),
			'i18n'    => array(
				'confirm_delete' => __( '確定要刪除此欄位嗎？', 'ys-raq-addons' ),
				'saved'          => __( '已儲存', 'ys-raq-addons' ),
				'error'          => __( '發生錯誤', 'ys-raq-addons' ),
			),
		) );
	}

	// ────────────────────────────────────────────
	// 設定頁面
	// ────────────────────────────────────────────

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 處理儲存
		if ( isset( $_POST['submit'] ) && check_admin_referer( $this->option_group . '-options' ) ) {
			$this->save_settings();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定已儲存。', 'ys-raq-addons' ) . '</p></div>';
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline" style="display:none;"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		</div>

		<div class="ys-settings-wrap">
			<div class="ys-settings-header">
				<h2><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'RAQ 擴充套件設定', 'ys-raq-addons' ); ?></h2>
				<p class="ys-settings-desc"><?php esc_html_e( '為 YITH WooCommerce Request a Quote 增強報價管理功能', 'ys-raq-addons' ); ?></p>
			</div>

			<nav class="nav-tab-wrapper ys-settings-tabs">
				<a href="#" class="nav-tab ys-tab-link <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="general">
					<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( '一般設定', 'ys-raq-addons' ); ?>
				</a>
				<a href="#" class="nav-tab ys-tab-link <?php echo 'display' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="display">
					<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( '顯示設定', 'ys-raq-addons' ); ?>
				</a>
				<a href="#" class="nav-tab ys-tab-link <?php echo 'fields' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="fields">
					<span class="dashicons dashicons-editor-table"></span> <?php esc_html_e( '表單欄位', 'ys-raq-addons' ); ?>
				</a>
			</nav>

			<form method="post" action="" class="ys-settings-form" style="<?php echo 'fields' === $active_tab ? 'display:none;' : ''; ?>">
				<?php wp_nonce_field( $this->option_group . '-options' ); ?>

				<!-- 一般設定 -->
				<div class="ys-tab-content" id="ys-tab-general" style="<?php echo 'general' !== $active_tab ? 'display:none;' : ''; ?>">
					<?php $this->render_general_tab(); ?>
				</div>

				<!-- 顯示設定 -->
				<div class="ys-tab-content" id="ys-tab-display" style="<?php echo 'display' !== $active_tab ? 'display:none;' : ''; ?>">
					<?php $this->render_display_tab(); ?>
				</div>

				<div class="ys-submit-wrap" id="ys-submit-button" style="<?php echo 'fields' === $active_tab ? 'display:none;' : ''; ?>">
					<?php submit_button( __( '儲存設定', 'ys-raq-addons' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>

			<!-- 表單欄位（使用 AJAX，不需要 form 包裝） -->
			<div class="ys-tab-content" id="ys-tab-fields" style="<?php echo 'fields' !== $active_tab ? 'display:none;' : ''; ?>">
				<?php $this->render_fields_tab(); ?>
			</div>
		</div>
		<?php
	}

	// ────────────────────────────────────────────
	// Tab: 一般設定（收件人管理）
	// ────────────────────────────────────────────

	private function render_general_tab(): void {
		?>
		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( '報價通知收件人', 'ys-raq-addons' ); ?>
			</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ys_raq_email_recipients"><?php esc_html_e( '主要收件人', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_email_recipients" name="ys_raq_email_recipients"
							value="<?php echo esc_attr( get_option( 'ys_raq_email_recipients', '' ) ); ?>"
							class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
						<p class="description"><?php esc_html_e( '留空則使用管理員信箱。多個收件人請用逗號分隔。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'CC 副本', 'ys-raq-addons' ); ?></th>
					<td>
						<label class="ys-toggle-switch">
							<input type="checkbox" name="ys_raq_email_cc_enabled" value="yes"
								<?php checked( get_option( 'ys_raq_email_cc_enabled', 'no' ), 'yes' ); ?> />
							<span class="ys-toggle-slider"></span>
						</label>
						<span class="ys-toggle-desc"><?php esc_html_e( '啟用 CC 副本', 'ys-raq-addons' ); ?></span>
					</td>
				</tr>
				<tr class="ys-raq-cc-row" style="<?php echo 'yes' !== get_option( 'ys_raq_email_cc_enabled', 'no' ) ? 'display:none;' : ''; ?>">
					<th scope="row">
						<label for="ys_raq_email_cc_emails"><?php esc_html_e( 'CC 電子郵件', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_email_cc_emails" name="ys_raq_email_cc_emails"
							value="<?php echo esc_attr( get_option( 'ys_raq_email_cc_emails', '' ) ); ?>"
							class="regular-text" />
						<p class="description"><?php esc_html_e( '多個電子郵件請用逗號分隔。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'BCC 密件副本', 'ys-raq-addons' ); ?></th>
					<td>
						<label class="ys-toggle-switch">
							<input type="checkbox" name="ys_raq_email_bcc_enabled" value="yes"
								<?php checked( get_option( 'ys_raq_email_bcc_enabled', 'no' ), 'yes' ); ?> />
							<span class="ys-toggle-slider"></span>
						</label>
						<span class="ys-toggle-desc"><?php esc_html_e( '啟用 BCC 密件副本', 'ys-raq-addons' ); ?></span>
					</td>
				</tr>
				<tr class="ys-raq-bcc-row" style="<?php echo 'yes' !== get_option( 'ys_raq_email_bcc_enabled', 'no' ) ? 'display:none;' : ''; ?>">
					<th scope="row">
						<label for="ys_raq_email_bcc_emails"><?php esc_html_e( 'BCC 電子郵件', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_email_bcc_emails" name="ys_raq_email_bcc_emails"
							value="<?php echo esc_attr( get_option( 'ys_raq_email_bcc_emails', '' ) ); ?>"
							class="regular-text" />
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// ────────────────────────────────────────────
	// Tab: 顯示設定
	// ────────────────────────────────────────────

	private function render_display_tab(): void {
		?>
		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( '產品資訊顯示', 'ys-raq-addons' ); ?>
			</h3>
			<p class="description" style="margin:0 0 15px;padding:0 10px;"><?php esc_html_e( '選擇在報價頁面和電子郵件中要顯示的產品資訊。', 'ys-raq-addons' ); ?></p>
			<table class="form-table">
				<?php
				$display_options = array(
					'ys_raq_show_product_images' => array( 'label' => __( '產品圖片', 'ys-raq-addons' ), 'default' => 'yes' ),
					'ys_raq_show_product_price'  => array( 'label' => __( '產品價格', 'ys-raq-addons' ), 'default' => 'yes' ),
					'ys_raq_show_product_sku'    => array( 'label' => __( '產品 SKU', 'ys-raq-addons' ), 'default' => 'no' ),
					'ys_raq_show_quantity'        => array( 'label' => __( '數量', 'ys-raq-addons' ), 'default' => 'yes' ),
					'ys_raq_show_line_total'      => array( 'label' => __( '單一產品總金額', 'ys-raq-addons' ), 'default' => 'yes' ),
					'ys_raq_show_total'           => array( 'label' => __( '所有產品總金額', 'ys-raq-addons' ), 'default' => 'yes' ),
				);

				foreach ( $display_options as $key => $opt ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $opt['label'] ); ?></th>
						<td>
							<label class="ys-toggle-switch">
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="yes"
									<?php checked( get_option( $key, $opt['default'] ), 'yes' ); ?> />
								<span class="ys-toggle-slider"></span>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row">
						<label for="ys_raq_thumbnail_width"><?php esc_html_e( '產品圖片寬度', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="number" id="ys_raq_thumbnail_width" name="ys_raq_thumbnail_width"
							value="<?php echo esc_attr( get_option( 'ys_raq_thumbnail_width', '80' ) ); ?>"
							class="small-text" min="30" max="300" step="1" /> px
						<p class="description"><?php esc_html_e( '報價頁面產品縮圖的顯示寬度（預設 80px）。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-editor-table"></span> <?php esc_html_e( '表單欄位顯示', 'ys-raq-addons' ); ?>
			</h3>
			<p class="description" style="margin:0 0 15px;padding:0 10px;"><?php esc_html_e( '控制報價表單中原生欄位的顯示與必填設定。', 'ys-raq-addons' ); ?></p>
			<table class="form-table">
				<?php
				$native_fields = array(
					'name'    => array(
						'label'        => __( 'Name（姓名）', 'ys-raq-addons' ),
						'show_key'     => 'ys_raq_show_name_field',
						'required_key' => 'ys_raq_require_name_field',
						'default_req'  => 'yes',
					),
					'email'   => array(
						'label'        => __( 'Email（電子郵件）', 'ys-raq-addons' ),
						'show_key'     => 'ys_raq_show_email_field',
						'required_key' => 'ys_raq_require_email_field',
						'default_req'  => 'yes',
					),
					'message' => array(
						'label'        => __( 'Message（留言）', 'ys-raq-addons' ),
						'show_key'     => 'ys_raq_show_message_field',
						'required_key' => 'ys_raq_require_message_field',
						'default_req'  => 'no',
					),
				);

				foreach ( $native_fields as $field_key => $field ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $field['label'] ); ?></th>
						<td>
							<div class="ys-native-field-controls">
								<span class="ys-control-group">
									<label class="ys-toggle-switch">
										<input type="checkbox" name="<?php echo esc_attr( $field['show_key'] ); ?>" value="yes"
											<?php checked( get_option( $field['show_key'], 'yes' ), 'yes' ); ?> />
										<span class="ys-toggle-slider"></span>
									</label>
									<span class="ys-toggle-desc"><?php esc_html_e( '顯示', 'ys-raq-addons' ); ?></span>
								</span>
								<span class="ys-control-group">
									<label class="ys-toggle-switch">
										<input type="checkbox" name="<?php echo esc_attr( $field['required_key'] ); ?>" value="yes"
											<?php checked( get_option( $field['required_key'], $field['default_req'] ), 'yes' ); ?> />
										<span class="ys-toggle-slider"></span>
									</label>
									<span class="ys-toggle-desc"><?php esc_html_e( '必填', 'ys-raq-addons' ); ?></span>
								</span>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-button"></span> <?php esc_html_e( '頁面按鈕', 'ys-raq-addons' ); ?>
			</h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( '「返回商店」按鈕', 'ys-raq-addons' ); ?></th>
					<td>
						<label class="ys-toggle-switch">
							<input type="checkbox" name="ys_raq_show_back_to_shop" value="yes"
								<?php checked( get_option( 'ys_raq_show_back_to_shop', 'yes' ), 'yes' ); ?> />
							<span class="ys-toggle-slider"></span>
						</label>
						<span class="ys-toggle-desc"><?php esc_html_e( '顯示返回商店按鈕', 'ys-raq-addons' ); ?></span>
					</td>
				</tr>
				<tr class="ys-raq-back-shop-row">
					<th scope="row">
						<label for="ys_raq_back_to_shop_label"><?php esc_html_e( '按鈕文字', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_back_to_shop_label" name="ys_raq_back_to_shop_label"
							value="<?php echo esc_attr( get_option( 'ys_raq_back_to_shop_label', __( '返回商店', 'ys-raq-addons' ) ) ); ?>"
							class="regular-text" />
					</td>
				</tr>
				<tr class="ys-raq-back-shop-row">
					<th scope="row">
						<label for="ys_raq_back_to_shop_url"><?php esc_html_e( '自訂 URL', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="url" id="ys_raq_back_to_shop_url" name="ys_raq_back_to_shop_url"
							value="<?php echo esc_attr( get_option( 'ys_raq_back_to_shop_url', '' ) ); ?>"
							class="regular-text" placeholder="<?php esc_attr_e( '留空使用 WooCommerce 商店頁面', 'ys-raq-addons' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '「更新清單」按鈕', 'ys-raq-addons' ); ?></th>
					<td>
						<label class="ys-toggle-switch">
							<input type="checkbox" name="ys_raq_show_update_list" value="yes"
								<?php checked( get_option( 'ys_raq_show_update_list', 'yes' ), 'yes' ); ?> />
							<span class="ys-toggle-slider"></span>
						</label>
						<span class="ys-toggle-desc"><?php esc_html_e( '顯示更新清單按鈕', 'ys-raq-addons' ); ?></span>
					</td>
				</tr>
				<tr class="ys-raq-update-row">
					<th scope="row">
						<label for="ys_raq_update_list_label"><?php esc_html_e( '按鈕文字', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_update_list_label" name="ys_raq_update_list_label"
							value="<?php echo esc_attr( get_option( 'ys_raq_update_list_label', __( '更新清單', 'ys-raq-addons' ) ) ); ?>"
							class="regular-text" />
					</td>
				</tr>
			</table>
		</div>

		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-cart"></span> <?php esc_html_e( '迷你詢價車', 'ys-raq-addons' ); ?>
			</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ys_raq_mini_cart_button_label"><?php esc_html_e( '查看清單按鈕文字', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_mini_cart_button_label" name="ys_raq_mini_cart_button_label"
							value="<?php echo esc_attr( get_option( 'ys_raq_mini_cart_button_label', __( '查看詢價清單', 'ys-raq-addons' ) ) ); ?>"
							class="regular-text" />
						<p class="description"><?php esc_html_e( '迷你詢價車視窗底部的按鈕文字。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ys_raq_mini_cart_empty_text"><?php esc_html_e( '空清單提示文字', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_mini_cart_empty_text" name="ys_raq_mini_cart_empty_text"
							value="<?php echo esc_attr( get_option( 'ys_raq_mini_cart_empty_text', __( '詢價清單是空的', 'ys-raq-addons' ) ) ); ?>"
							class="regular-text" />
						<p class="description"><?php esc_html_e( '迷你詢價車清單為空時顯示的文字。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ys_raq_mini_cart_qty_label"><?php esc_html_e( '數量標籤文字', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="text" id="ys_raq_mini_cart_qty_label" name="ys_raq_mini_cart_qty_label"
							value="<?php echo esc_attr( get_option( 'ys_raq_mini_cart_qty_label', __( '數量：', 'ys-raq-addons' ) ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( '例：數量： / Qty: / 件數：', 'ys-raq-addons' ); ?>" />
						<p class="description"><?php esc_html_e( '迷你詢價車商品列每筆顯示的數量前綴。英文站可改為「Qty: 」、日文站可改「数量：」等。直接連著數字顯示（不加空格）。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( '迷你詢價車 AJAX 刷新', 'ys-raq-addons' ); ?>
			</h3>
			<p class="description" style="margin:0 0 15px;padding:0 10px;">
				<?php esc_html_e( '控制加入／移除詢價項目後，迷你詢價車的刷新時機。採用「雙點刷新」策略：第一輪快速反應，第二輪於 YITH DOM 完全更新後補刷一次，確保 badge 數字正確。', 'ys-raq-addons' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ys_raq_mini_cart_refresh_delay"><?php esc_html_e( '第一輪刷新延遲', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="number" id="ys_raq_mini_cart_refresh_delay" name="ys_raq_mini_cart_refresh_delay"
							value="<?php echo esc_attr( get_option( 'ys_raq_mini_cart_refresh_delay', '600' ) ); ?>"
							class="small-text" min="200" max="3000" step="50" /> ms
						<p class="description"><?php esc_html_e( '偵測到 YITH 加入／移除事件後，等待多久執行第一次刷新（預設 600ms，範圍 200–3000ms）。連線較慢或主機較慢可適度加長。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ys_raq_mini_cart_settle_delay"><?php esc_html_e( '第二輪保險刷新延遲', 'ys-raq-addons' ); ?></label>
					</th>
					<td>
						<input type="number" id="ys_raq_mini_cart_settle_delay" name="ys_raq_mini_cart_settle_delay"
							value="<?php echo esc_attr( get_option( 'ys_raq_mini_cart_settle_delay', '1500' ) ); ?>"
							class="small-text" min="500" max="5000" step="100" /> ms
						<p class="description"><?php esc_html_e( '第一輪後再等多久做保險刷新，確保 session 與 DOM 已完全更新（預設 1500ms，範圍 500–5000ms）。若仍偶發漏更新，請把此值調到 2000–2500ms。', 'ys-raq-addons' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// ────────────────────────────────────────────
	// Tab: 表單欄位管理
	// ────────────────────────────────────────────

	private function render_fields_tab(): void {
		$fields = get_option( 'ys_raq_custom_fields', array() );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		$field_types = array(
			'text'     => __( '文字', 'ys-raq-addons' ),
			'email'    => __( '電子郵件', 'ys-raq-addons' ),
			'tel'      => __( '電話', 'ys-raq-addons' ),
			'number'   => __( '數字', 'ys-raq-addons' ),
			'textarea' => __( '多行文字', 'ys-raq-addons' ),
			'select'   => __( '下拉選單', 'ys-raq-addons' ),
			'radio'    => __( '單選按鈕', 'ys-raq-addons' ),
			'checkbox' => __( '核取方塊', 'ys-raq-addons' ),
			'date'     => __( '日期', 'ys-raq-addons' ),
		);
		?>
		<div class="ys-section-card">
			<h3 class="ys-section-title">
				<span class="dashicons dashicons-editor-table"></span> <?php esc_html_e( '自訂表單欄位', 'ys-raq-addons' ); ?>
				<button type="button" class="button button-primary ys-raq-add-field-btn">
					<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span>
					<?php esc_html_e( '新增欄位', 'ys-raq-addons' ); ?>
				</button>
			</h3>
			<p class="description" style="margin:0 0 15px;padding:0 10px;">
				<?php esc_html_e( '新增自訂欄位到報價請求表單。這些欄位會顯示在預設表單欄位之後。', 'ys-raq-addons' ); ?>
			</p>

			<table class="widefat ys-raq-fields-table" id="ys-raq-fields-table">
				<thead>
					<tr>
						<th style="width:30px;"></th>
						<th><?php esc_html_e( '欄位名稱', 'ys-raq-addons' ); ?></th>
						<th><?php esc_html_e( '類型', 'ys-raq-addons' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( '必填', 'ys-raq-addons' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( '啟用', 'ys-raq-addons' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( '操作', 'ys-raq-addons' ); ?></th>
					</tr>
				</thead>
				<tbody id="ys-raq-fields-body">
					<?php if ( empty( $fields ) ) : ?>
						<tr class="ys-raq-no-fields">
							<td colspan="6" style="text-align:center;padding:20px;color:#999;">
								<?php esc_html_e( '尚未新增自訂欄位。點擊「新增欄位」開始。', 'ys-raq-addons' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $fields as $field_id => $field ) : ?>
							<tr data-field-id="<?php echo esc_attr( $field_id ); ?>">
								<td class="ys-raq-sort-handle"><span class="dashicons dashicons-menu" style="cursor:move;color:#999;"></span></td>
								<td><strong><?php echo esc_html( $field['label'] ?? '' ); ?></strong><br><code><?php echo esc_html( $field_id ); ?></code></td>
								<td><?php echo esc_html( $field_types[ $field['type'] ?? 'text' ] ?? $field['type'] ); ?></td>
								<td><?php echo 'yes' === ( $field['required'] ?? 'no' ) ? '<span class="dashicons dashicons-yes" style="color:#00a32a;"></span>' : '<span class="dashicons dashicons-minus" style="color:#ccc;"></span>'; ?></td>
								<td>
									<label class="ys-toggle-switch ys-toggle-small">
										<input type="checkbox" class="ys-raq-toggle-field" data-field-id="<?php echo esc_attr( $field_id ); ?>"
											<?php checked( $field['enabled'] ?? 'yes', 'yes' ); ?> />
										<span class="ys-toggle-slider"></span>
									</label>
								</td>
								<td>
									<button type="button" class="button button-small ys-raq-edit-field" data-field-id="<?php echo esc_attr( $field_id ); ?>"
										data-field='<?php echo esc_attr( wp_json_encode( $field ) ); ?>'>
										<span class="dashicons dashicons-edit" style="vertical-align:text-bottom;font-size:16px;"></span>
									</button>
									<button type="button" class="button button-small ys-raq-delete-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
										<span class="dashicons dashicons-trash" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- 欄位編輯彈窗 -->
		<div id="ys-raq-field-modal" class="ys-raq-modal" style="display:none;">
			<div class="ys-raq-modal-overlay"></div>
			<div class="ys-raq-modal-content">
				<div class="ys-raq-modal-header">
					<h3 id="ys-raq-modal-title"><?php esc_html_e( '新增欄位', 'ys-raq-addons' ); ?></h3>
					<button type="button" class="ys-raq-modal-close">&times;</button>
				</div>
				<div class="ys-raq-modal-body">
					<input type="hidden" id="ys-raq-field-editing-id" value="" />

					<p>
						<label for="ys-raq-field-label"><?php esc_html_e( '欄位標籤', 'ys-raq-addons' ); ?> <span class="required">*</span></label>
						<input type="text" id="ys-raq-field-label" class="widefat" />
					</p>
					<p>
						<label for="ys-raq-field-id"><?php esc_html_e( '欄位 ID', 'ys-raq-addons' ); ?></label>
						<input type="text" id="ys-raq-field-id" class="widefat" placeholder="<?php esc_attr_e( '留空自動產生', 'ys-raq-addons' ); ?>" />
						<span class="description"><?php esc_html_e( '英文小寫、底線，不含空格。', 'ys-raq-addons' ); ?></span>
					</p>
					<p>
						<label for="ys-raq-field-type"><?php esc_html_e( '欄位類型', 'ys-raq-addons' ); ?></label>
						<select id="ys-raq-field-type" class="widefat">
							<?php foreach ( $field_types as $type_key => $type_label ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p id="ys-raq-field-options-wrap" style="display:none;">
						<label for="ys-raq-field-options"><?php esc_html_e( '選項（每行一個，格式：值|標籤）', 'ys-raq-addons' ); ?></label>
						<textarea id="ys-raq-field-options" class="widefat" rows="4" placeholder="option1|選項一&#10;option2|選項二"></textarea>
					</p>
					<p id="ys-raq-field-checkbox-label-wrap" style="display:none;">
						<label for="ys-raq-field-checkbox-label"><?php esc_html_e( '核取方塊說明文字', 'ys-raq-addons' ); ?></label>
						<input type="text" id="ys-raq-field-checkbox-label" class="widefat" />
					</p>
					<p>
						<label>
							<input type="checkbox" id="ys-raq-field-required" />
							<?php esc_html_e( '此欄位為必填', 'ys-raq-addons' ); ?>
						</label>
					</p>
				</div>
				<div class="ys-raq-modal-footer">
					<button type="button" class="button ys-raq-modal-cancel"><?php esc_html_e( '取消', 'ys-raq-addons' ); ?></button>
					<button type="button" class="button button-primary ys-raq-modal-save"><?php esc_html_e( '儲存欄位', 'ys-raq-addons' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	// ────────────────────────────────────────────
	// 報價單紀錄（獨立頁面）
	// ────────────────────────────────────────────

	/**
	 * 渲染報價單紀錄頁面
	 */
	public function render_quotes_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 處理單一刪除（GET 請求帶 action=delete）
		if ( 'delete' === ( $_GET['action'] ?? '' ) && isset( $_GET['quote_id'], $_GET['_wpnonce'] ) ) {
			$quote_id = absint( $_GET['quote_id'] );
			$post     = get_post( $quote_id );

			if ( $post
				&& YSRaqQuoteHistory::POST_TYPE === $post->post_type
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ys_raq_delete_quote_' . $quote_id )
			) {
				wp_delete_post( $quote_id, true );
				// 重新導向到列表頁，避免重複刪除
				wp_safe_redirect( admin_url( 'admin.php?page=ys-raq-quotes&deleted=1' ) );
				exit;
			}
		}

		// 查看單一報價詳情
		if ( isset( $_GET['quote_id'] ) && ! isset( $_GET['action'] ) ) {
			$this->render_quotes_page_wrap( true, absint( $_GET['quote_id'] ) );
			return;
		}

		$this->render_quotes_page_wrap( false );
	}

	/**
	 * 報價單紀錄頁面容器
	 */
	private function render_quotes_page_wrap( bool $is_detail, int $quote_id = 0 ): void {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( '報價單紀錄', 'ys-raq-addons' ); ?></h1>

			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '報價紀錄已刪除。', 'ys-raq-addons' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['bulk_action'], $_GET['processed'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					$processed = absint( $_GET['processed'] );
					if ( 'delete' === $_GET['bulk_action'] ) {
						printf( esc_html__( '已刪除 %d 筆報價紀錄。', 'ys-raq-addons' ), $processed );
					} else {
						printf( esc_html__( '已更新 %d 筆報價紀錄的狀態。', 'ys-raq-addons' ), $processed );
					}
					?>
				</p></div>
			<?php endif; ?>
		</div>

		<div class="ys-settings-wrap">
			<div class="ys-settings-header">
				<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( '報價單紀錄', 'ys-raq-addons' ); ?></h2>
				<p class="ys-settings-desc"><?php esc_html_e( '查看與管理所有報價請求紀錄', 'ys-raq-addons' ); ?></p>
			</div>

			<div class="ys-quotes-content">
				<?php
				if ( $is_detail ) {
					$this->render_quote_detail( $quote_id );
				} else {
					$this->render_quote_list();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * 渲染報價列表
	 */
	private function render_quote_list(): void {
		$table = new YSRaqQuoteListTable();
		$table->prepare_items();
		?>
		<div class="ys-section-card ys-quotes-list-card">
			<div class="ys-quotes-list-header">
				<h3 class="ys-section-title" style="margin:0;border:0;padding:0;">
					<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( '報價請求紀錄', 'ys-raq-addons' ); ?>
				</h3>
				<span class="description">
					<?php
					$total = YSRaqQuoteHistory::get_count_by_status();
					printf( esc_html__( '共 %d 筆紀錄', 'ys-raq-addons' ), $total );
					?>
				</span>
			</div>
			<div class="ys-quotes-list-body">
				<form method="post">
					<input type="hidden" name="page" value="ys-raq-quotes" />
					<?php $table->display(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * 渲染單一報價詳情
	 */
	private function render_quote_detail( int $quote_id ): void {
		$post = get_post( $quote_id );

		if ( ! $post || YSRaqQuoteHistory::POST_TYPE !== $post->post_type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( '找不到此報價紀錄。', 'ys-raq-addons' ) . '</p></div>';
			return;
		}

		$quote_number = get_post_meta( $quote_id, '_ys_raq_quote_number', true );
		$name         = get_post_meta( $quote_id, '_ys_raq_customer_name', true );
		$email        = get_post_meta( $quote_id, '_ys_raq_customer_email', true );
		$message      = get_post_meta( $quote_id, '_ys_raq_customer_message', true );
		$products     = get_post_meta( $quote_id, '_ys_raq_products', true );
		$total        = get_post_meta( $quote_id, '_ys_raq_total', true );
		$status       = get_post_meta( $quote_id, '_ys_raq_status', true ) ?: 'new';
		$custom_data  = get_post_meta( $quote_id, '_ys_raq_custom_fields_data', true );
		$statuses     = YSRaqQuoteHistory::get_statuses();

		// 讀取顯示設定
		$show_images     = 'yes' === get_option( 'ys_raq_show_product_images', 'yes' );
		$show_price      = 'yes' === get_option( 'ys_raq_show_product_price', 'yes' );
		$show_sku        = 'yes' === get_option( 'ys_raq_show_product_sku', 'no' );
		$show_quantity   = 'yes' === get_option( 'ys_raq_show_quantity', 'yes' );
		$show_line_total = 'yes' === get_option( 'ys_raq_show_line_total', 'yes' );
		$show_total      = 'yes' === get_option( 'ys_raq_show_total', 'yes' );

		$back_url = admin_url( 'admin.php?page=ys-raq-quotes' );
		?>
		<div class="ys-section-card">
			<div class="ys-quote-detail-header">
				<h3 class="ys-section-title" style="margin:0;border:0;padding:0;">
					<span class="dashicons dashicons-format-chat"></span>
					<?php printf( esc_html__( '報價 #%s', 'ys-raq-addons' ), esc_html( $quote_number ) ); ?>
				</h3>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button">
					<span class="dashicons dashicons-arrow-left-alt" style="vertical-align:text-bottom;"></span>
					<?php esc_html_e( '返回列表', 'ys-raq-addons' ); ?>
				</a>
			</div>

			<div class="ys-quote-detail-grid">
				<!-- 客戶資訊 -->
				<div class="ys-quote-info-card">
					<h4><?php esc_html_e( '客戶資訊', 'ys-raq-addons' ); ?></h4>
					<p><strong><?php esc_html_e( '姓名：', 'ys-raq-addons' ); ?></strong><?php echo esc_html( $name ); ?></p>
					<p><strong><?php esc_html_e( '信箱：', 'ys-raq-addons' ); ?></strong><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
					<?php if ( ! empty( $message ) ) : ?>
						<p><strong><?php esc_html_e( '留言：', 'ys-raq-addons' ); ?></strong></p>
						<div class="ys-quote-message"><?php echo nl2br( esc_html( $message ) ); ?></div>
					<?php endif; ?>
				</div>

				<!-- 狀態與日期 -->
				<div class="ys-quote-info-card">
					<h4><?php esc_html_e( '報價資訊', 'ys-raq-addons' ); ?></h4>
					<p><strong><?php esc_html_e( '狀態：', 'ys-raq-addons' ); ?></strong>
						<select name="ys_raq_quote_status" class="ys-raq-status-select" data-quote-id="<?php echo esc_attr( $quote_id ); ?>">
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p><strong><?php esc_html_e( '日期：', 'ys-raq-addons' ); ?></strong><?php echo esc_html( get_the_date( 'Y-m-d H:i:s', $quote_id ) ); ?></p>
					<?php if ( $show_total ) : ?>
						<p><strong><?php esc_html_e( '預估金額：', 'ys-raq-addons' ); ?></strong><?php echo wp_kses_post( wc_price( $total ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $custom_data ) && is_array( $custom_data ) ) : ?>
				<div class="ys-quote-info-card ys-quote-custom-fields">
					<h4><?php esc_html_e( '自訂欄位', 'ys-raq-addons' ); ?></h4>
					<?php foreach ( $custom_data as $cf ) : ?>
						<p><strong><?php echo esc_html( $cf['label'] ?? '' ); ?>：</strong><?php echo esc_html( $cf['value'] ?? '' ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- 產品清單 -->
			<div class="ys-quote-products">
				<h4><?php esc_html_e( '產品清單', 'ys-raq-addons' ); ?></h4>
				<table class="widefat striped ys-quote-products-table">
					<thead>
						<tr>
							<?php if ( $show_images ) : ?>
								<th class="ys-col-image"><?php esc_html_e( '圖片', 'ys-raq-addons' ); ?></th>
							<?php endif; ?>
							<th><?php esc_html_e( '產品', 'ys-raq-addons' ); ?></th>
							<?php if ( $show_sku ) : ?>
								<th><?php esc_html_e( 'SKU', 'ys-raq-addons' ); ?></th>
							<?php endif; ?>
							<?php if ( $show_price ) : ?>
								<th class="ys-col-right"><?php esc_html_e( '單價', 'ys-raq-addons' ); ?></th>
							<?php endif; ?>
							<?php if ( $show_quantity ) : ?>
								<th class="ys-col-center"><?php esc_html_e( '數量', 'ys-raq-addons' ); ?></th>
							<?php endif; ?>
							<?php if ( $show_line_total ) : ?>
								<th class="ys-col-right"><?php esc_html_e( '小計', 'ys-raq-addons' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( is_array( $products ) ) : ?>
							<?php foreach ( $products as $item ) : ?>
								<?php $product = wc_get_product( $item['product_id'] ?? 0 ); ?>
								<tr>
									<?php if ( $show_images ) : ?>
										<td class="ys-col-image">
											<?php
											if ( $product ) {
												echo $product->get_image( array( 48, 48 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											}
											?>
										</td>
									<?php endif; ?>
									<td>
										<?php
										if ( $product ) {
											echo '<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a>';
										} else {
											echo esc_html( $item['product_name'] ?? '' );
										}
										?>
									</td>
									<?php if ( $show_sku ) : ?>
										<td><?php echo esc_html( $item['product_sku'] ?? '-' ); ?></td>
									<?php endif; ?>
									<?php if ( $show_price ) : ?>
										<td class="ys-col-right"><?php echo wp_kses_post( wc_price( (float) ( $item['product_price'] ?? 0 ) ) ); ?></td>
									<?php endif; ?>
									<?php if ( $show_quantity ) : ?>
										<td class="ys-col-center"><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
									<?php endif; ?>
									<?php if ( $show_line_total ) : ?>
										<td class="ys-col-right"><?php echo wp_kses_post( wc_price( (float) ( $item['product_price'] ?? 0 ) * (int) ( $item['quantity'] ?? 1 ) ) ); ?></td>
									<?php endif; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<?php if ( $show_total ) : ?>
						<tfoot>
							<tr>
								<?php
								$colspan = 1;
								if ( $show_images ) { ++$colspan; }
								if ( $show_sku ) { ++$colspan; }
								if ( $show_price ) { ++$colspan; }
								if ( $show_quantity ) { ++$colspan; }
								?>
								<td colspan="<?php echo esc_attr( $colspan ); ?>" class="ys-col-right"><strong><?php esc_html_e( '合計：', 'ys-raq-addons' ); ?></strong></td>
								<td class="ys-col-right"><strong><?php echo wp_kses_post( wc_price( $total ) ); ?></strong></td>
							</tr>
						</tfoot>
					<?php endif; ?>
				</table>
			</div>
		</div>
		<?php
	}

	// ────────────────────────────────────────────
	// 設定儲存
	// ────────────────────────────────────────────

	private function save_settings(): void {
		// Checkbox 欄位
		$checkboxes = array(
			'ys_raq_email_cc_enabled',
			'ys_raq_email_bcc_enabled',
			'ys_raq_show_product_images',
			'ys_raq_show_product_price',
			'ys_raq_show_product_sku',
			'ys_raq_show_quantity',
			'ys_raq_show_line_total',
			'ys_raq_show_total',
			'ys_raq_show_back_to_shop',
			'ys_raq_show_update_list',
			'ys_raq_show_name_field',
			'ys_raq_show_email_field',
			'ys_raq_show_message_field',
			'ys_raq_require_name_field',
			'ys_raq_require_email_field',
			'ys_raq_require_message_field',
		);

		foreach ( $checkboxes as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
		}

		// 文字欄位
		$text_fields = array(
			'ys_raq_email_recipients',
			'ys_raq_email_cc_emails',
			'ys_raq_email_bcc_emails',
			'ys_raq_back_to_shop_label',
			'ys_raq_update_list_label',
			'ys_raq_mini_cart_button_label',
			'ys_raq_mini_cart_empty_text',
			'ys_raq_mini_cart_qty_label',
		);

		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		// URL 欄位
		if ( isset( $_POST['ys_raq_back_to_shop_url'] ) ) {
			update_option( 'ys_raq_back_to_shop_url', esc_url_raw( wp_unslash( $_POST['ys_raq_back_to_shop_url'] ) ) );
		}

		// 數值欄位（縮圖寬度）
		if ( isset( $_POST['ys_raq_thumbnail_width'] ) ) {
			$width = absint( $_POST['ys_raq_thumbnail_width'] );
			$width = max( 30, min( 300, $width ) );
			update_option( 'ys_raq_thumbnail_width', (string) $width );
		}

		// 迷你詢價車 AJAX 刷新延遲（第一輪）
		if ( isset( $_POST['ys_raq_mini_cart_refresh_delay'] ) ) {
			$delay = absint( $_POST['ys_raq_mini_cart_refresh_delay'] );
			$delay = max( 200, min( 3000, $delay ) );
			update_option( 'ys_raq_mini_cart_refresh_delay', (string) $delay );
		}

		// 迷你詢價車 AJAX 保險刷新延遲（第二輪）
		if ( isset( $_POST['ys_raq_mini_cart_settle_delay'] ) ) {
			$settle = absint( $_POST['ys_raq_mini_cart_settle_delay'] );
			$settle = max( 500, min( 5000, $settle ) );
			update_option( 'ys_raq_mini_cart_settle_delay', (string) $settle );
		}
	}

	// ────────────────────────────────────────────
	// AJAX: 表單欄位管理
	// ────────────────────────────────────────────

	/**
	 * 儲存（新增/編輯）表單欄位
	 */
	public function ajax_save_field(): void {
		check_ajax_referer( 'ys-raq-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'ys-raq-addons' ) ) );
		}

		$field_id = isset( $_POST['field_id'] ) ? sanitize_key( $_POST['field_id'] ) : '';
		$label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( empty( $label ) ) {
			wp_send_json_error( array( 'message' => __( '欄位標籤為必填', 'ys-raq-addons' ) ) );
		}

		// 自動產生 ID
		$editing_id = isset( $_POST['editing_id'] ) ? sanitize_key( $_POST['editing_id'] ) : '';
		if ( empty( $field_id ) ) {
			$field_id = sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) );
			if ( empty( $field_id ) ) {
				$field_id = 'field_' . wp_rand( 1000, 9999 );
			}
		}

		$field_type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'text';
		$required   = isset( $_POST['required'] ) && 'true' === $_POST['required'] ? 'yes' : 'no';

		// 編輯模式時保留原本的啟用狀態
		$existing_enabled = 'yes';
		if ( ! empty( $editing_id ) ) {
			$existing_fields  = get_option( 'ys_raq_custom_fields', array() );
			$existing_enabled = $existing_fields[ $editing_id ]['enabled'] ?? 'yes';
		}

		$field_data = array(
			'label'    => $label,
			'type'     => $field_type,
			'required' => $required,
			'enabled'  => $existing_enabled,
		);

		// 處理選項（select/radio）
		if ( in_array( $field_type, array( 'select', 'radio' ), true ) ) {
			$options_raw = isset( $_POST['options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['options'] ) ) : '';
			$options     = array();

			foreach ( explode( "\n", $options_raw ) as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				if ( str_contains( $line, '|' ) ) {
					list( $val, $lbl ) = explode( '|', $line, 2 );
					$options[ sanitize_key( trim( $val ) ) ] = sanitize_text_field( trim( $lbl ) );
				} else {
					$key = sanitize_key( $line );
					$options[ $key ] = sanitize_text_field( $line );
				}
			}

			$field_data['options'] = $options;
		}

		// 核取方塊說明文字
		if ( 'checkbox' === $field_type ) {
			$field_data['checkbox_label'] = isset( $_POST['checkbox_label'] ) ? sanitize_text_field( wp_unslash( $_POST['checkbox_label'] ) ) : '';
		}

		$fields = get_option( 'ys_raq_custom_fields', array() );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		// 編輯模式：移除舊 key
		if ( ! empty( $editing_id ) && $editing_id !== $field_id && isset( $fields[ $editing_id ] ) ) {
			unset( $fields[ $editing_id ] );
		}

		$fields[ $field_id ] = $field_data;
		update_option( 'ys_raq_custom_fields', $fields );

		wp_send_json_success( array(
			'message'  => __( '欄位已儲存', 'ys-raq-addons' ),
			'field_id' => $field_id,
			'field'    => $field_data,
		) );
	}

	/**
	 * 刪除表單欄位
	 */
	public function ajax_delete_field(): void {
		check_ajax_referer( 'ys-raq-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$field_id = isset( $_POST['field_id'] ) ? sanitize_key( $_POST['field_id'] ) : '';
		$fields   = get_option( 'ys_raq_custom_fields', array() );

		if ( isset( $fields[ $field_id ] ) ) {
			unset( $fields[ $field_id ] );
			update_option( 'ys_raq_custom_fields', $fields );
		}

		wp_send_json_success();
	}

	/**
	 * 切換欄位啟用狀態
	 */
	public function ajax_toggle_field(): void {
		check_ajax_referer( 'ys-raq-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$field_id = isset( $_POST['field_id'] ) ? sanitize_key( $_POST['field_id'] ) : '';
		$enabled  = isset( $_POST['enabled'] ) && 'true' === $_POST['enabled'] ? 'yes' : 'no';
		$fields   = get_option( 'ys_raq_custom_fields', array() );

		if ( isset( $fields[ $field_id ] ) ) {
			$fields[ $field_id ]['enabled'] = $enabled;
			update_option( 'ys_raq_custom_fields', $fields );
		}

		wp_send_json_success();
	}

	/**
	 * 欄位排序
	 */
	public function ajax_reorder_fields(): void {
		check_ajax_referer( 'ys-raq-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$order  = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'sanitize_key', $_POST['order'] ) : array();
		$fields = get_option( 'ys_raq_custom_fields', array() );

		$sorted = array();
		foreach ( $order as $field_id ) {
			if ( isset( $fields[ $field_id ] ) ) {
				$sorted[ $field_id ] = $fields[ $field_id ];
			}
		}

		// 保留未在排序中的欄位
		foreach ( $fields as $id => $field ) {
			if ( ! isset( $sorted[ $id ] ) ) {
				$sorted[ $id ] = $field;
			}
		}

		update_option( 'ys_raq_custom_fields', $sorted );

		wp_send_json_success();
	}

	// ────────────────────────────────────────────
	// 歡迎頁面
	// ────────────────────────────────────────────

	public static function render_toolbox_welcome(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugins = apply_filters( 'ys_toolbox_plugins', array() );
		?>
		<div class="wrap">
			<h1 style="display:none;"><?php esc_html_e( '電商工具箱', 'ys-raq-addons' ); ?></h1>
		</div>

		<div class="ys-toolbox-welcome">
			<div class="ys-toolbox-header">
				<div class="ys-toolbox-header-content">
					<div class="ys-toolbox-logo">
						<span class="dashicons dashicons-store"></span>
					</div>
					<h2>電商工具箱</h2>
					<p class="ys-toolbox-subtitle">WooCommerce 電商擴充套件，由 YANGSHEEP DESIGN 開發維護</p>
				</div>
			</div>

			<?php if ( ! empty( $plugins ) ) : ?>
				<div class="ys-toolbox-cards">
					<?php
					foreach ( $plugins as $plugin ) :
						$plugin = wp_parse_args( $plugin, array(
							'name'    => __( '未知外掛', 'ys-raq-addons' ),
							'version' => '0.0.0',
							'icon'    => 'dashicons-admin-plugins',
							'desc'    => '',
							'url'     => '#',
						) );
						?>
						<a href="<?php echo esc_url( $plugin['url'] ); ?>" class="ys-toolbox-card">
							<div class="ys-toolbox-card-icon">
								<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
							</div>
							<div class="ys-toolbox-card-body">
								<h3><?php echo esc_html( $plugin['name'] ); ?></h3>
								<span class="ys-toolbox-card-version">v<?php echo esc_html( $plugin['version'] ); ?></span>
								<p><?php echo esc_html( $plugin['desc'] ); ?></p>
							</div>
							<span class="ys-toolbox-card-arrow dashicons dashicons-arrow-right-alt2"></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="ys-toolbox-empty">
					<span class="dashicons dashicons-info-outline"></span>
					<p>尚未偵測到已啟用的 YS 外掛。</p>
				</div>
			<?php endif; ?>

			<div class="ys-toolbox-footer">
				<div class="ys-toolbox-footer-info">
					<span class="dashicons dashicons-heart"></span>
					<span>由 <strong>YANGSHEEP DESIGN</strong> 用心開發</span>
					<span class="ys-toolbox-sep">|</span>
					<a href="https://yangsheep.com.tw" target="_blank" rel="noopener">yangsheep.com.tw</a>
				</div>
			</div>
		</div>

		<style>
			.ys-toolbox-welcome{max-width:860px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,Cantarell,sans-serif}
			.ys-toolbox-header{background:linear-gradient(135deg,#3a4f63 0%,#2c3e50 100%);border-radius:12px;padding:40px;margin-bottom:24px;color:#fff}
			.ys-toolbox-header-content{text-align:center}
			.ys-toolbox-logo{display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:16px;margin-bottom:16px}
			.ys-toolbox-logo .dashicons{font-size:32px;width:32px;height:32px;color:#fff}
			.ys-toolbox-header h2{font-size:24px;font-weight:600;margin:0 0 8px;color:#fff}
			.ys-toolbox-subtitle{font-size:14px;opacity:.8;margin:0}
			.ys-toolbox-cards{display:flex;flex-direction:column;gap:12px;margin-bottom:24px}
			.ys-toolbox-card{display:flex;align-items:center;gap:20px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;text-decoration:none;color:inherit;transition:all .2s ease}
			.ys-toolbox-card:hover{border-color:#8fa8b8;box-shadow:0 2px 12px rgba(0,0,0,.08);transform:translateY(-1px)}
			.ys-toolbox-card:focus{outline:2px solid #8fa8b8;outline-offset:2px}
			.ys-toolbox-card-icon{flex-shrink:0;display:flex;align-items:center;justify-content:center;width:52px;height:52px;background:#f0f4f7;border-radius:12px}
			.ys-toolbox-card-icon .dashicons{font-size:24px;width:24px;height:24px;color:#3a4f63}
			.ys-toolbox-card-body{flex:1;min-width:0}
			.ys-toolbox-card-body h3{font-size:15px;font-weight:600;margin:0 0 4px;color:#1d2327;display:inline}
			.ys-toolbox-card-version{display:inline-block;font-size:11px;color:#8fa8b8;background:#f0f4f7;padding:1px 8px;border-radius:10px;margin-left:8px;vertical-align:middle}
			.ys-toolbox-card-body p{font-size:13px;color:#646970;margin:6px 0 0;line-height:1.5}
			.ys-toolbox-card-arrow{flex-shrink:0;color:#c3c4c7;transition:color .2s ease}
			.ys-toolbox-card:hover .ys-toolbox-card-arrow{color:#8fa8b8}
			.ys-toolbox-empty{text-align:center;padding:48px 24px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:24px}
			.ys-toolbox-empty .dashicons{font-size:40px;width:40px;height:40px;color:#c3c4c7;margin-bottom:12px}
			.ys-toolbox-empty p{color:#646970;font-size:14px;margin:0}
			.ys-toolbox-footer{text-align:center;padding:16px 0}
			.ys-toolbox-footer-info{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#8c8f94}
			.ys-toolbox-footer-info .dashicons{font-size:14px;width:14px;height:14px;color:#cc99c2}
			.ys-toolbox-footer-info a{color:#8fa8b8;text-decoration:none}
			.ys-toolbox-footer-info a:hover{color:#3a4f63}
			.ys-toolbox-sep{color:#ddd;margin:0 4px}
		</style>
		<?php
	}
}
