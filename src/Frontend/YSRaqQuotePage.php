<?php
/**
 * 報價頁面增強功能
 *
 * 使用 output buffering 攔截 YITH RAQ 模板輸出，直接在伺服器端修改 HTML：
 * - 產品圖片、SKU、價格、數量等欄位的顯示控制
 * - 「返回商店」按鈕注入 actions 行
 * - 自訂表單欄位注入送出按鈕前
 *
 * @package YangSheep\RaqAddons\Frontend
 */

namespace YangSheep\RaqAddons\Frontend;

defined( 'ABSPATH' ) || exit;

final class YSRaqQuotePage {

	private static ?self $instance = null;

	/**
	 * 追蹤正在 buffering 的模板
	 *
	 * @var array<string, bool>
	 */
	private array $buffering = array();

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// ── YITH 原生選項覆蓋 ──
		add_filter( 'option_ywraq_show_sku', array( $this, 'override_show_sku' ) );
		add_filter( 'option_ywraq_hide_price', array( $this, 'override_hide_price' ) );
		add_filter( 'option_ywraq_show_update_list', array( $this, 'override_show_update_list' ) );
		add_filter( 'option_ywraq_update_list_label', array( $this, 'override_update_list_label' ) );

		// ── 產品縮圖控制 ──
		add_filter( 'ywraq_item_thumbnail', array( $this, 'filter_thumbnail' ), 10, 1 );

		// ── Output Buffering：攔截 YITH 模板並修改 HTML ──
		add_action( 'woocommerce_before_template_part', array( $this, 'maybe_start_buffer' ), 10, 4 );
		add_action( 'woocommerce_after_template_part', array( $this, 'maybe_end_buffer' ), 10, 4 );

		// ── 前端 CSS ──
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	// ────────────────────────────────────────────
	// YITH 選項覆蓋（僅在前端生效）
	// ────────────────────────────────────────────

	public function override_show_sku( $value ) {
		if ( is_admin() ) {
			return $value;
		}
		return get_option( 'ys_raq_show_product_sku', 'no' );
	}

	public function override_hide_price( $value ) {
		if ( is_admin() ) {
			return $value;
		}
		$show_price = get_option( 'ys_raq_show_product_price', 'yes' );
		return 'yes' === $show_price ? 'no' : 'yes';
	}

	public function override_show_update_list( $value ) {
		if ( is_admin() ) {
			return $value;
		}
		return get_option( 'ys_raq_show_update_list', 'yes' );
	}

	public function override_update_list_label( $value ) {
		if ( is_admin() ) {
			return $value;
		}
		$custom_label = get_option( 'ys_raq_update_list_label', '' );
		return ! empty( $custom_label ) ? $custom_label : $value;
	}

	// ────────────────────────────────────────────
	// 產品縮圖
	// ────────────────────────────────────────────

	public function filter_thumbnail( $show ) {
		if ( 'yes' !== get_option( 'ys_raq_show_product_images', 'yes' ) ) {
			return false;
		}
		return $show;
	}

	// ────────────────────────────────────────────
	// Output Buffering：模板攔截與修改
	// ────────────────────────────────────────────

	/**
	 * 在 YITH RAQ 模板渲染前開始 output buffering
	 */
	public function maybe_start_buffer( string $template_name, string $template_path, string $located, array $args ): void {
		if ( in_array( $template_name, array( 'request-quote-view.php', 'request-quote-form.php' ), true ) ) {
			$this->buffering[ $template_name ] = true;
			ob_start();
		}
	}

	/**
	 * 在 YITH RAQ 模板渲染後結束 buffering，修改 HTML 並輸出
	 */
	public function maybe_end_buffer( string $template_name, string $template_path, string $located, array $args ): void {
		if ( empty( $this->buffering[ $template_name ] ) ) {
			return;
		}

		unset( $this->buffering[ $template_name ] );
		$html = ob_get_clean();

		if ( 'request-quote-view.php' === $template_name ) {
			echo $this->modify_view_template( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'request-quote-form.php' === $template_name ) {
			echo $this->modify_form_template( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	// ────────────────────────────────────────────
	// 商品列表模板修改（request-quote-view.php）
	// ────────────────────────────────────────────

	/**
	 * 修改商品列表模板：
	 * 1. 注入 inline CSS 隱藏欄位
	 * 2. 在 actions 行注入「返回商店」按鈕
	 */
	private function modify_view_template( string $html ): string {
		// 1. 注入 inline styles
		$styles = $this->get_view_inline_styles();
		if ( ! empty( $styles ) ) {
			$html = '<style>' . $styles . '</style>' . "\n" . $html;
		}

		// 2. 注入「返回商店」按鈕到 actions 行
		$html = $this->inject_back_button( $html );

		return $html;
	}

	/**
	 * 取得商品列表的 inline CSS
	 */
	private function get_view_inline_styles(): string {
		$css = '';

		// 隱藏產品圖片欄
		if ( 'yes' !== get_option( 'ys_raq_show_product_images', 'yes' ) ) {
			$css .= '#yith-ywrq-table-list .product-thumbnail { display: none !important; }';
		} else {
			// 縮圖寬度控制
			$thumb_width = absint( get_option( 'ys_raq_thumbnail_width', 80 ) );
			if ( $thumb_width > 0 ) {
				$css .= '#yith-ywrq-table-list .product-thumbnail img { width: ' . $thumb_width . 'px; height: auto; }';
			}
		}

		// 隱藏數量欄
		if ( 'yes' !== get_option( 'ys_raq_show_quantity', 'yes' ) ) {
			$css .= '#yith-ywrq-table-list .product-quantity, #yith-ywrq-table-list thead th.product-quantity { display: none !important; }';
		}

		// 隱藏小計欄
		if ( 'yes' !== get_option( 'ys_raq_show_line_total', 'yes' ) ) {
			$css .= '#yith-ywrq-table-list .product-subtotal, #yith-ywrq-table-list thead th.product-subtotal { display: none !important; }';
		}

		// actions 行內 wrapper flex 佈局
		$css .= '.ys-raq-actions-wrapper { display: flex; justify-content: space-between; align-items: center; gap: 10px; width: 100%; }';
		$css .= '.ys-raq-actions-left, .ys-raq-actions-right { flex-shrink: 0; }';

		return $css;
	}

	/**
	 * 在 actions 行注入「返回商店」按鈕
	 *
	 * 將 actions td 的內容包入左側容器，新增右側容器放置「返回商店」按鈕。
	 */
	private function inject_back_button( string $html ): string {
		if ( 'yes' !== get_option( 'ys_raq_show_back_to_shop', 'yes' ) ) {
			return $html;
		}

		$label = get_option( 'ys_raq_back_to_shop_label', __( '返回商店', 'ys-raq-addons' ) );
		$url   = get_option( 'ys_raq_back_to_shop_url', '' );

		if ( empty( $url ) ) {
			$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		}

		$button_html = sprintf(
			'<a href="%s" class="button ys-raq-back-to-shop">&#8592; %s</a>',
			esc_url( $url ),
			esc_html( $label )
		);

		// 嘗試找到 actions td 並注入按鈕
		// 用 wrapper div 包裹原有內容 + 返回按鈕，避免在 td 上使用 flex
		// 模板結構：<td colspan="X" class="actions">...(submit + hidden input)...</td>
		if ( preg_match( '/(<td[^>]*class="actions"[^>]*>)(.*?)(<\/td>)/s', $html, $matches ) ) {
			$replacement = $matches[1]
				. '<div class="ys-raq-actions-wrapper">'
				. '<div class="ys-raq-actions-left">' . $matches[2] . '</div>'
				. '<div class="ys-raq-actions-right">' . $button_html . '</div>'
				. '</div>'
				. $matches[3];
			$html = str_replace( $matches[0], $replacement, $html );
		} else {
			// 備用方案：在模板末尾添加
			$html .= '<div class="ys-raq-back-to-shop-fallback" style="margin:15px 0;">' . $button_html . '</div>';
		}

		return $html;
	}

	// ────────────────────────────────────────────
	// 表單模板修改（request-quote-form.php）
	// ────────────────────────────────────────────

	/**
	 * 修改表單模板：隱藏原生欄位 + 注入自訂欄位
	 */
	private function modify_form_template( string $html ): string {
		// 1. 隱藏停用的原生欄位
		$html = $this->hide_native_form_fields( $html );

		// 2. 注入自訂欄位
		$fields = get_option( 'ys_raq_custom_fields', array() );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $html;
		}

		ob_start();
		$this->render_custom_form_fields();
		$fields_html = ob_get_clean();

		if ( empty( $fields_html ) ) {
			return $html;
		}

		// 在 Email 欄位之後、Message 欄位之前插入自訂欄位
		// YITH 表單結構：#rqa_name_row → #rqa_email_row → #rqa_message_row → submit
		$marker = 'id="rqa_message_row"';
		$pos    = strpos( $html, $marker );

		if ( false !== $pos ) {
			$before = substr( $html, 0, $pos );
			$last_p = strrpos( $before, '<p' );

			if ( false !== $last_p ) {
				$html = substr( $html, 0, $last_p ) . $fields_html . "\n\t\t" . substr( $html, $last_p );
				return $html;
			}
		}

		// 備用方案：在 </form> 前插入
		$form_end = strrpos( $html, '</form>' );
		if ( false !== $form_end ) {
			$html = substr( $html, 0, $form_end ) . $fields_html . "\n" . substr( $html, $form_end );
		}

		return $html;
	}

	/**
	 * 隱藏停用的原生表單欄位 + 控制必填屬性（Name / Email / Message）
	 */
	private function hide_native_form_fields( string $html ): string {
		$native_fields = array(
			'name'    => array(
				'show_key'     => 'ys_raq_show_name_field',
				'required_key' => 'ys_raq_require_name_field',
				'row_id'       => 'rqa_name_row',
				'input_name'   => 'rqa_name',
				'default_req'  => 'yes',
			),
			'email'   => array(
				'show_key'     => 'ys_raq_show_email_field',
				'required_key' => 'ys_raq_require_email_field',
				'row_id'       => 'rqa_email_row',
				'input_name'   => 'rqa_email',
				'default_req'  => 'yes',
			),
			'message' => array(
				'show_key'     => 'ys_raq_show_message_field',
				'required_key' => 'ys_raq_require_message_field',
				'row_id'       => 'rqa_message_row',
				'input_name'   => 'rqa_message',
				'default_req'  => 'no',
			),
		);

		foreach ( $native_fields as $field ) {
			$show   = get_option( $field['show_key'], 'yes' );
			$row_id = $field['row_id'];

			// 隱藏停用的欄位
			if ( 'yes' !== $show ) {
				$pattern = '/(<p[^>]*id="' . preg_quote( $row_id, '/' ) . '")/';
				$html    = preg_replace( $pattern, '$1 style="display:none;"', $html );
				continue;
			}

			// 必填控制：scope 到整個 <p id="xxx_row">...</p> 段落內處理，避免跨 row 誤傷
			$html = $this->rebuild_row_required_marker( $html, $field );
		}

		// 最後安全網：若 label 內仍殘留多個必填星號，只保留第一個
		$html = $this->dedupe_required_abbrs( $html );

		return $html;
	}

	/**
	 * 重建單一原生欄位的必填標記（星號 + required 屬性）
	 *
	 * 將目標 row 的整個 <p>...</p> 段落抓出後，於該段內：
	 *   1. 清除 label 內所有 <abbr class="required">*</abbr>
	 *   2. 清除 input/textarea 上的 required 屬性
	 *   3. 若設定為必填，於 label 末尾加一個 abbr 並於 input 上加回 required
	 */
	private function rebuild_row_required_marker( string $html, array $field ): string {
		$row_id     = $field['row_id'];
		$input_name = $field['input_name'];
		$required   = 'yes' === get_option( $field['required_key'], $field['default_req'] );

		$row_pattern = '/<p\b[^>]*\bid="' . preg_quote( $row_id, '/' ) . '"[^>]*>.*?<\/p>/s';
		$abbr_pattern = '/\s*<abbr\b[^>]*\bclass="[^"]*\brequired\b[^"]*"[^>]*>.*?<\/abbr>/s';

		$result = preg_replace_callback(
			$row_pattern,
			static function ( array $m ) use ( $input_name, $required, $abbr_pattern ): string {
				$row = $m[0];

				// 1. 清除 label 內所有 <abbr class="required">
				$row = preg_replace_callback(
					'/(<label\b[^>]*>)(.*?)(<\/label>)/s',
					static function ( array $lm ) use ( $abbr_pattern ): string {
						$cleaned = preg_replace( $abbr_pattern, '', $lm[2] );
						return $lm[1] . rtrim( (string) $cleaned ) . $lm[3];
					},
					$row
				);

				// 2. 清除 input/textarea 上既有的 required 屬性
				$row = preg_replace(
					'/(\bname="' . preg_quote( $input_name, '/' ) . '"[^>]*?)\s+required(?=[\s\/>])/s',
					'$1',
					$row
				);

				if ( $required ) {
					// 3a. 在 input/textarea tag 末端加回 required
					$row = preg_replace(
						'/(\bname="' . preg_quote( $input_name, '/' ) . '"[^>]*?)(\s*\/?>)/s',
						'$1 required$2',
						$row,
						1
					);
					// 3b. 在 label 末尾加回一個必填星號
					$row = preg_replace(
						'/(<label\b[^>]*>)(.*?)(<\/label>)/s',
						'$1$2 <abbr class="required" title="required">*</abbr>$3',
						$row,
						1
					);
				}

				return $row;
			},
			$html,
			1
		);

		return null === $result ? $html : $result;
	}

	/**
	 * 安全網：同一個 label 內若出現多個 <abbr class="required">，只保留第一個
	 *
	 * 用於防止任何未預期的重複注入（包含其他外掛、主題覆寫或 regex 邊界案例）。
	 */
	private function dedupe_required_abbrs( string $html ): string {
		$abbr_pattern = '/<abbr\b[^>]*\bclass="[^"]*\brequired\b[^"]*"[^>]*>.*?<\/abbr>/s';

		$result = preg_replace_callback(
			'/(<label\b[^>]*>)(.*?)(<\/label>)/s',
			static function ( array $m ) use ( $abbr_pattern ): string {
				if ( preg_match_all( $abbr_pattern, $m[2], $all ) <= 1 ) {
					return $m[0];
				}

				$seen  = false;
				$clean = preg_replace_callback(
					$abbr_pattern,
					static function ( array $_m ) use ( &$seen ): string {
						if ( $seen ) {
							return '';
						}
						$seen = true;
						return $_m[0];
					},
					$m[2]
				);

				return $m[1] . (string) $clean . $m[3];
			},
			$html
		);

		return null === $result ? $html : $result;
	}

	// ────────────────────────────────────────────
	// 自訂表單欄位渲染
	// ────────────────────────────────────────────

	/**
	 * 渲染自訂表單欄位
	 */
	public function render_custom_form_fields(): void {
		$fields = get_option( 'ys_raq_custom_fields', array() );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return;
		}

		foreach ( $fields as $field_id => $field ) {
			if ( empty( $field['enabled'] ) || 'yes' !== $field['enabled'] ) {
				continue;
			}

			$field_type = $field['type'] ?? 'text';
			$label      = $field['label'] ?? '';
			$required   = ! empty( $field['required'] ) && 'yes' === $field['required'];
			$name       = 'ys_raq_field_' . sanitize_key( $field_id );
			$value      = '';

			// 從使用者資料預填
			if ( is_user_logged_in() ) {
				$user  = wp_get_current_user();
				$value = get_user_meta( $user->ID, $name, true );
			}

			echo '<p class="form-row form-row-wide ys-raq-custom-field ys-raq-field-' . esc_attr( $field_type ) . '">';
			echo '<label for="' . esc_attr( $name ) . '">';
			echo esc_html( $label );
			if ( $required ) {
				echo ' <abbr class="required" title="required">*</abbr>';
			}
			echo '</label>';

			switch ( $field_type ) {
				case 'textarea':
					printf(
						'<textarea name="%s" id="%s" class="input-text" rows="4"%s>%s</textarea>',
						esc_attr( $name ),
						esc_attr( $name ),
						$required ? ' required' : '',
						esc_textarea( $value )
					);
					break;

				case 'select':
					$options = $field['options'] ?? array();
					printf( '<select name="%s" id="%s"%s>', esc_attr( $name ), esc_attr( $name ), $required ? ' required' : '' );
					echo '<option value="">' . esc_html__( '請選擇...', 'ys-raq-addons' ) . '</option>';
					foreach ( $options as $opt_value => $opt_label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $opt_value ),
							selected( $value, $opt_value, false ),
							esc_html( $opt_label )
						);
					}
					echo '</select>';
					break;

				case 'checkbox':
					printf(
						'<input type="checkbox" name="%s" id="%s" value="yes"%s%s /> %s',
						esc_attr( $name ),
						esc_attr( $name ),
						checked( $value, 'yes', false ),
						$required ? ' required' : '',
						esc_html( $field['checkbox_label'] ?? '' )
					);
					break;

				case 'radio':
					$options  = $field['options'] ?? array();
					$is_first = true;
					foreach ( $options as $opt_value => $opt_label ) {
						$req_attr = ( $required && $is_first ) ? ' required' : '';
						$is_first = false;
						printf(
							'<label class="ys-raq-radio-label"><input type="radio" name="%s" value="%s"%s%s /> %s</label>',
							esc_attr( $name ),
							esc_attr( $opt_value ),
							checked( $value, $opt_value, false ),
							$req_attr,
							esc_html( $opt_label )
						);
					}
					break;

				case 'email':
					printf(
						'<input type="email" name="%s" id="%s" class="input-text" value="%s"%s />',
						esc_attr( $name ),
						esc_attr( $name ),
						esc_attr( $value ),
						$required ? ' required' : ''
					);
					break;

				case 'tel':
					printf(
						'<input type="tel" name="%s" id="%s" class="input-text" value="%s"%s />',
						esc_attr( $name ),
						esc_attr( $name ),
						esc_attr( $value ),
						$required ? ' required' : ''
					);
					break;

				case 'number':
					printf(
						'<input type="number" name="%s" id="%s" class="input-text" value="%s"%s />',
						esc_attr( $name ),
						esc_attr( $name ),
						esc_attr( $value ),
						$required ? ' required' : ''
					);
					break;

				case 'date':
					printf(
						'<input type="date" name="%s" id="%s" class="input-text" value="%s"%s />',
						esc_attr( $name ),
						esc_attr( $name ),
						esc_attr( $value ),
						$required ? ' required' : ''
					);
					break;

				default: // text
					printf(
						'<input type="text" name="%s" id="%s" class="input-text" value="%s"%s />',
						esc_attr( $name ),
						esc_attr( $name ),
						esc_attr( $value ),
						$required ? ' required' : ''
					);
					break;
			}

			echo '</p>';
		}
	}

	// ────────────────────────────────────────────
	// 前端資源
	// ────────────────────────────────────────────

	/**
	 * 載入前端 CSS
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_raq_page() ) {
			return;
		}

		wp_enqueue_style(
			'ys-raq-quote-page',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/css/ys-raq-quote-page.css',
			array(),
			YS_RAQ_ADDONS_VERSION
		);

		wp_enqueue_script(
			'ys-raq-quote-page',
			YS_RAQ_ADDONS_PLUGIN_URL . 'assets/js/ys-raq-quote-page.js',
			array( 'jquery' ),
			YS_RAQ_ADDONS_VERSION,
			true
		);

		wp_localize_script(
			'ys-raq-quote-page',
			'ys_raq_quote_params',
			array(
				'debounceMs' => 500,
				'i18n'       => array(
					'updating' => __( '更新中...', 'ys-raq-addons' ),
					'updated'  => __( '已更新', 'ys-raq-addons' ),
					'failed'   => __( '更新失敗', 'ys-raq-addons' ),
				),
			)
		);
	}

	// ────────────────────────────────────────────
	// 輔助方法
	// ────────────────────────────────────────────

	/**
	 * 判斷是否為報價請求頁面
	 */
	private function is_raq_page(): bool {
		if ( ! function_exists( 'YITH_Request_Quote' ) ) {
			return false;
		}

		$page_id = get_option( 'ywraq_page_id', 0 );

		return $page_id && is_page( absint( $page_id ) );
	}
}
