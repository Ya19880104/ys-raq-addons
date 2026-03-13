<?php
/**
 * 報價歷史列表表格
 *
 * @package YangSheep\RaqAddons\Admin
 */

namespace YangSheep\RaqAddons\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 報價請求歷史 WP_List_Table
 */
class YSRaqQuoteListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'quote',
			'plural'   => 'quotes',
			'ajax'     => false,
		) );
	}

	/**
	 * 定義表格欄位
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'quote_number'   => __( '報價編號', 'ys-raq-addons' ),
			'customer'       => __( '客戶', 'ys-raq-addons' ),
			'products_count' => __( '產品數', 'ys-raq-addons' ),
			'total'          => __( '預估金額', 'ys-raq-addons' ),
			'status'         => __( '狀態', 'ys-raq-addons' ),
			'date'           => __( '日期', 'ys-raq-addons' ),
			'actions'        => __( '操作', 'ys-raq-addons' ),
		);
	}

	/**
	 * 可排序的欄位
	 */
	public function get_sortable_columns(): array {
		return array(
			'quote_number' => array( 'ID', true ),
			'date'         => array( 'date', true ),
			'total'        => array( 'total', false ),
		);
	}

	/**
	 * Checkbox 欄位
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="quote_ids[]" value="%d" />', $item->ID );
	}

	/**
	 * 報價編號欄位
	 */
	public function column_quote_number( $item ): string {
		$quote_number = get_post_meta( $item->ID, '_ys_raq_quote_number', true );
		$detail_url   = add_query_arg( array(
			'page'     => 'ys-raq-quotes',
			'quote_id' => $item->ID,
		), admin_url( 'admin.php' ) );

		return sprintf(
			'<a href="%s"><strong>#%s</strong></a>',
			esc_url( $detail_url ),
			esc_html( $quote_number ?: $item->ID )
		);
	}

	/**
	 * 客戶欄位
	 */
	public function column_customer( $item ): string {
		$name  = get_post_meta( $item->ID, '_ys_raq_customer_name', true );
		$email = get_post_meta( $item->ID, '_ys_raq_customer_email', true );

		$output = '<strong>' . esc_html( $name ) . '</strong>';
		if ( ! empty( $email ) ) {
			$output .= '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}
		return $output;
	}

	/**
	 * 產品數量欄位
	 */
	public function column_products_count( $item ): string {
		$products = get_post_meta( $item->ID, '_ys_raq_products', true );
		$count    = is_array( $products ) ? count( $products ) : 0;
		return sprintf( '%d %s', $count, __( '項', 'ys-raq-addons' ) );
	}

	/**
	 * 金額欄位
	 */
	public function column_total( $item ): string {
		$total = get_post_meta( $item->ID, '_ys_raq_total', true );
		return function_exists( 'wc_price' ) ? wc_price( $total ) : number_format( (float) $total, 2 );
	}

	/**
	 * 狀態欄位 — 快速操作下拉選單
	 */
	public function column_status( $item ): string {
		$status   = get_post_meta( $item->ID, '_ys_raq_status', true ) ?: 'new';
		$statuses = YSRaqQuoteHistory::get_statuses();

		$colors = array(
			'new'     => '#2271b1',
			'pending' => '#dba617',
			'replied' => '#00a32a',
			'expired' => '#996800',
			'closed'  => '#787c82',
		);

		$color = $colors[ $status ] ?? '#787c82';

		$html = sprintf(
			'<select class="ys-raq-list-status-select" data-quote-id="%d" style="border-color:%s;color:%s;font-weight:500;font-size:12px;padding:2px 6px;border-radius:6px;min-width:80px;">',
			$item->ID,
			esc_attr( $color ),
			esc_attr( $color )
		);

		foreach ( $statuses as $key => $label ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $status, $key, false ),
				esc_html( $label )
			);
		}

		$html .= '</select>';

		return $html;
	}

	/**
	 * 日期欄位
	 */
	public function column_date( $item ): string {
		$date = get_the_date( 'Y-m-d H:i', $item->ID );
		$human = human_time_diff( get_the_time( 'U', $item->ID ), current_time( 'timestamp' ) );

		return sprintf(
			'<span title="%s">%s %s</span>',
			esc_attr( $date ),
			esc_html( $human ),
			esc_html__( '前', 'ys-raq-addons' )
		);
	}

	/**
	 * 操作欄位
	 */
	public function column_actions( $item ): string {
		$detail_url = add_query_arg( array(
			'page'     => 'ys-raq-quotes',
			'quote_id' => $item->ID,
		), admin_url( 'admin.php' ) );

		$delete_url = wp_nonce_url(
			add_query_arg( array(
				'page'     => 'ys-raq-quotes',
				'action'   => 'delete',
				'quote_id' => $item->ID,
			), admin_url( 'admin.php' ) ),
			'ys_raq_delete_quote_' . $item->ID
		);

		return '<div class="ys-raq-actions-inline">' . sprintf(
			'<a href="%s" class="button button-small" title="%s"><span class="dashicons dashicons-visibility" style="vertical-align:text-bottom;"></span> %s</a> ',
			esc_url( $detail_url ),
			esc_attr__( '查看', 'ys-raq-addons' ),
			esc_html__( '查看', 'ys-raq-addons' )
		) . sprintf(
			'<a href="%s" class="button button-small ys-raq-delete-btn" title="%s" onclick="return confirm(\'%s\');"><span class="dashicons dashicons-trash" style="vertical-align:text-bottom;"></span></a>',
			esc_url( $delete_url ),
			esc_attr__( '刪除', 'ys-raq-addons' ),
			esc_js( __( '確定要刪除此報價紀錄嗎？', 'ys-raq-addons' ) )
		) . '</div>';
	}

	/**
	 * 批次操作
	 */
	public function get_bulk_actions(): array {
		return array(
			'mark_pending' => __( '標記為處理中', 'ys-raq-addons' ),
			'mark_replied' => __( '標記為已回覆', 'ys-raq-addons' ),
			'mark_closed'  => __( '標記為已關閉', 'ys-raq-addons' ),
			'delete'       => __( '刪除', 'ys-raq-addons' ),
		);
	}

	/**
	 * 處理批次操作
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// 單一刪除
		if ( 'delete' === $action && isset( $_GET['quote_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$quote_id = absint( $_GET['quote_id'] );
			$post     = get_post( $quote_id );
			if ( $post
				&& YSRaqQuoteHistory::POST_TYPE === $post->post_type
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ys_raq_delete_quote_' . $quote_id )
			) {
				wp_delete_post( $quote_id, true );
			}
			return;
		}

		// 批次操作
		if ( ! isset( $_POST['quote_ids'] ) || ! is_array( $_POST['quote_ids'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-quotes' );

		$ids = array_map( 'absint', $_POST['quote_ids'] );

		foreach ( $ids as $id ) {
			// 確認目標是報價紀錄
			$post = get_post( $id );
			if ( ! $post || YSRaqQuoteHistory::POST_TYPE !== $post->post_type ) {
				continue;
			}

			if ( 'delete' === $action ) {
				wp_delete_post( $id, true );
			} elseif ( str_starts_with( $action, 'mark_' ) ) {
				$status = str_replace( 'mark_', '', $action );
				update_post_meta( $id, '_ys_raq_status', sanitize_key( $status ) );
			}
		}
	}

	/**
	 * 篩選器
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status = isset( $_GET['quote_status'] ) ? sanitize_key( $_GET['quote_status'] ) : '';
		$statuses       = YSRaqQuoteHistory::get_statuses();

		echo '<div class="alignleft actions">';
		echo '<select name="quote_status">';
		echo '<option value="">' . esc_html__( '所有狀態', 'ys-raq-addons' ) . '</option>';
		foreach ( $statuses as $key => $label ) {
			$count = YSRaqQuoteHistory::get_count_by_status( $key );
			printf(
				'<option value="%s"%s>%s (%d)</option>',
				esc_attr( $key ),
				selected( $current_status, $key, false ),
				esc_html( $label ),
				$count
			);
		}
		echo '</select>';
		submit_button( __( '篩選', 'ys-raq-addons' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * 無資料時的顯示
	 */
	public function no_items(): void {
		esc_html_e( '尚無報價請求紀錄。', 'ys-raq-addons' );
	}

	/**
	 * 準備列表資料
	 */
	public function prepare_items(): void {
		$this->process_bulk_action();

		$per_page = 20;
		$paged    = $this->get_pagenum();
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'ID';
		$order    = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';

		$args = array(
			'post_type'      => YSRaqQuoteHistory::POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'post_status'    => 'publish',
			'orderby'        => $orderby,
			'order'          => strtoupper( $order ),
		);

		// 狀態篩選
		$status_filter = isset( $_GET['quote_status'] ) ? sanitize_key( $_GET['quote_status'] ) : '';
		if ( ! empty( $status_filter ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ys_raq_status',
					'value' => $status_filter,
				),
			);
		}

		// 搜尋
		if ( ! empty( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		// 依金額排序
		if ( 'total' === $orderby ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_ys_raq_total';
		}

		$query = new \WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args( array(
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}
}
