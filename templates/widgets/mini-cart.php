<?php
/**
 * Mini Cart Widget 模板
 *
 * 可在佈景主題中複製到 yourtheme/ys-raq-addons/widgets/mini-cart.php 來覆寫。
 *
 * @package YangSheep\RaqAddons
 * @version 1.1.0
 *
 * @var array  $raq_content       詢價清單內容
 * @var string $title             Widget 標題
 * @var string $item_name         單數品項名稱
 * @var string $item_plural_name  複數品項名稱
 * @var string $button_label      按鈕標籤
 * @var bool   $show_title_inside 在面板內顯示標題
 * @var bool   $show_thumbnail    顯示縮圖
 * @var bool   $show_price        顯示價格
 * @var bool   $show_quantity     顯示數量
 * @var bool   $show_variations   顯示變體資訊
 * @var string $qty_label         [選填] 數量 label 覆寫（shortcode 傳入；未傳入時改讀後台 option）
 */

defined( 'ABSPATH' ) || exit;

$num_items       = is_array( $raq_content ) ? count( $raq_content ) : 0;
$raq_page_url    = YITH_Request_Quote()->get_raq_page_url();
$show_thumbnail  = (bool) $show_thumbnail;
$show_price      = (bool) $show_price;
$show_quantity   = (bool) $show_quantity;
$show_variations = (bool) $show_variations;
$tax_display     = get_option( 'woocommerce_tax_display_cart' );

// 數量 label：shortcode 顯式傳入優先，否則讀後台 option（預設「數量：」）。
// 英文站可將後台設為「Qty: 」或空字串 + 只顯示數字等配置。
$qty_label_override = isset( $qty_label ) ? (string) $qty_label : '';
$qty_label_text     = '' !== $qty_label_override
	? $qty_label_override
	: (string) get_option( 'ys_raq_mini_cart_qty_label', __( '數量：', 'ys-raq-addons' ) );
?>

<?php do_action( 'ys_raq_before_mini_cart' ); ?>

<div class="ys-raq-trigger <?php echo 0 === $num_items ? 'ys-raq-empty' : ''; ?>">
	<a class="ys-raq-trigger-link" href="<?php echo esc_url( $raq_page_url ); ?>">
		<span class="ys-raq-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
			<span class="ys-raq-badge"><?php echo esc_html( $num_items ); ?></span>
		</span>
	</a>
</div>

<div class="ys-raq-dropdown">
	<div class="ys-raq-dropdown-close">&times;</div>
	<div class="ys-raq-dropdown-content">
		<?php if ( $show_title_inside && $num_items > 0 ) : ?>
			<p class="ys-raq-items-count">
				<?php
				printf(
					/* translators: %d: 商品數量, %s: 品項名稱 */
					esc_html__( '%1$d %2$s', 'ys-raq-addons' ),
					esc_html( $num_items ),
					esc_html( $num_items <= 1 ? $item_name : $item_plural_name )
				);
				?>
				&mdash; <?php echo esc_html( $title ); ?>
			</p>
		<?php endif; ?>

		<ul class="ys-raq-list">
			<?php if ( 0 === $num_items ) : ?>
				<li class="ys-raq-no-product">
					<?php echo esc_html( apply_filters( 'ys_raq_mini_cart_empty_message', get_option( 'ys_raq_mini_cart_empty_text', __( '詢價清單是空的', 'ys-raq-addons' ) ) ) ); ?>
				</li>
			<?php else : ?>
				<?php
				foreach ( $raq_content as $key => $raq ) :
					$product_id = isset( $raq['variation_id'] ) && $raq['variation_id'] ? $raq['variation_id'] : $raq['product_id'];
					$_product   = wc_get_product( $product_id );

					if ( ! $_product ) {
						continue;
					}

					$product_name = $_product->get_title();
					$quantity     = isset( $raq['quantity'] ) ? (int) $raq['quantity'] : 1;
					?>
					<li class="ys-raq-list-item">
						<a href="#"
						   class="ys-raq-item-remove"
						   data-remove-item="<?php echo esc_attr( $key ); ?>"
						   data-product_id="<?php echo esc_attr( $_product->get_id() ); ?>"
						   data-wp_nonce="<?php echo esc_attr( wp_create_nonce( 'remove-request-quote-' . $_product->get_id() ) ); ?>"
						   title="<?php esc_attr_e( '移除此項目', 'ys-raq-addons' ); ?>">&times;</a>

						<?php if ( $show_thumbnail ) : ?>
							<div class="ys-raq-item-thumb">
								<?php if ( $_product->is_visible() ) : ?>
									<a href="<?php echo esc_url( $_product->get_permalink() ); ?>">
										<?php echo $_product->get_image( array( 50, 50 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</a>
								<?php else : ?>
									<?php echo $_product->get_image( array( 50, 50 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="ys-raq-item-info">
							<a class="ys-raq-item-name" href="<?php echo esc_url( $_product->get_permalink() ); ?>">
								<?php echo esc_html( $product_name ); ?>
							</a>

							<?php if ( $show_variations && ! empty( $raq['variations'] ) ) : ?>
								<small class="ys-raq-item-variations">
									<?php
									if ( function_exists( 'yith_ywraq_get_product_meta' ) ) {
										yith_ywraq_get_product_meta( $raq );
									}
									?>
								</small>
							<?php endif; ?>

							<?php if ( $show_quantity ) : ?>
								<span class="ys-raq-item-qty-price">
									<?php
									/* RAQ（詢價）情境不顯示金額，只顯示數量；show_price 設定被移除以避免 NT$0 誤導。
									   label 可由後台「迷你詢價車 → 數量 label」設定，或 shortcode qty_label 屬性覆寫，方便英文站調整。 */
									echo esc_html( $qty_label_text ) . esc_html( (int) $quantity );
									?>
								</span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>

		<?php if ( $num_items > 0 ) : ?>
			<a href="<?php echo esc_url( $raq_page_url ); ?>" class="ys-raq-view-list-btn button">
				<?php echo esc_html( apply_filters( 'ys_raq_mini_cart_button_label', $button_label ) ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>

<?php do_action( 'ys_raq_after_mini_cart' ); ?>
