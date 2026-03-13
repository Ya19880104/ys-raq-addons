# 開發文件 — YS RAQ Addons

## 技術棧

| 項目 | 版本 |
|------|------|
| PHP | 8.0+ |
| WordPress | 6.0+ |
| WooCommerce | 8.0+ |
| jQuery / jQuery UI Sortable | WordPress 內建 |

## 目錄結構

```
ys-raq-addons/
├── ys-raq-addons.php              # 主入口：常數、自動載入、HPOS 宣告
├── composer.json                   # PSR-4 自動載入
├── index.php                       # 安全性空檔
│
├── src/                            # PHP 源碼（PSR-4: YangSheep\RaqAddons\）
│   ├── YSRaqAddons.php            # 主類別（Singleton），初始化所有模組
│   ├── YSRaqAjax.php              # AJAX 處理器（靜態類別）
│   ├── YSRaqShortcodes.php        # Shortcode 註冊
│   ├── Admin/
│   │   ├── YSRaqSettings.php      # 設定頁面、選單、頁面渲染、AJAX 欄位管理
│   │   ├── YSRaqQuoteHistory.php  # 報價歷史 CPT 註冊與資料攔截
│   │   └── YSRaqQuoteListTable.php # WP_List_Table 報價列表
│   ├── Frontend/
│   │   └── YSRaqQuotePage.php     # 報價頁面增強（Output Buffering）
│   ├── Email/
│   │   └── YSRaqEmailHandler.php  # 郵件收件人、產品控制、自訂欄位
│   └── Widgets/
│       └── YSRaqMiniCartWidget.php # 迷你詢價車 Widget
│
├── templates/widgets/mini-cart.php # 可覆寫模板
│
└── assets/
    ├── css/
    │   ├── ys-raq-addons-admin.css # 後台樣式
    │   ├── ys-raq-quote-page.css   # 報價頁面樣式
    │   └── ys-raq-mini-cart.css    # Mini Cart 樣式
    └── js/
        ├── ys-raq-addons-admin.js  # 後台腳本
        ├── ys-raq-quote-page.js    # 報價頁面腳本
        └── ys-raq-mini-cart.js     # Mini Cart 腳本
```

## 初始化流程

```
ys-raq-addons.php
  ├── 定義常數（版本、路徑、URL）
  ├── PSR-4 autoloader
  ├── HPOS 相容性宣告
  └── YSRaqAddons::instance()
        └── plugins_loaded (priority 20)
              ├── 檢查 YITH RAQ 是否啟用
              ├── Admin\YSRaqSettings         # 後台設定
              ├── Admin\YSRaqQuoteHistory      # 報價紀錄 CPT
              ├── Email\YSRaqEmailHandler      # 郵件處理
              ├── Frontend\YSRaqQuotePage      # 前端頁面
              ├── YSRaqAjax                    # AJAX
              ├── Widgets\YSRaqMiniCartWidget  # Widget
              └── YSRaqShortcodes              # Shortcode
```

## 設計模式

| 模式 | 使用於 |
|------|--------|
| **Singleton** | `YSRaqAddons`, `YSRaqSettings`, `YSRaqQuoteHistory`, `YSRaqEmailHandler`, `YSRaqQuotePage` |
| **Static Class** | `YSRaqAjax`, `YSRaqShortcodes` |
| **Output Buffering** | `YSRaqQuotePage`, `YSRaqEmailHandler` — 攔截並修改 YITH 輸出的 HTML |

## AJAX Endpoints

| Action | 說明 | Nonce |
|--------|------|-------|
| `ys_raq_refresh_mini_cart` | 刷新 Mini Cart | `ys-raq-mini-cart` |
| `ys_raq_remove_item` | 移除詢價項目 | `ys-raq-mini-cart` |
| `ys_raq_get_count` | 取得詢價數量 | 不需要 |
| `ys_raq_update_status` | 更新報價狀態 | `ys-raq-admin` |
| `ys_raq_save_field` | 儲存自訂欄位 | `ys-raq-admin` |
| `ys_raq_delete_field` | 刪除自訂欄位 | `ys-raq-admin` |
| `ys_raq_toggle_field` | 切換欄位啟停 | `ys-raq-admin` |
| `ys_raq_reorder_fields` | 欄位排序 | `ys-raq-admin` |

## 資料儲存

### Post Meta（報價紀錄 CPT: `ys_raq_quote`）

| Meta Key | 說明 |
|----------|------|
| `_ys_raq_quote_number` | 報價編號（RQ-YYYYMMDD-XXXX） |
| `_ys_raq_customer_name` | 客戶姓名 |
| `_ys_raq_customer_email` | 客戶電子郵件 |
| `_ys_raq_customer_message` | 客戶留言 |
| `_ys_raq_status` | 報價狀態（new/pending/replied/expired/closed） |
| `_ys_raq_products` | 產品清單（序列化陣列） |
| `_ys_raq_total` | 預估總金額 |
| `_ys_raq_custom_fields_data` | 自訂欄位資料 |
| `_ys_raq_missing_required` | 缺少的必填欄位 |

### Options

| Option Key | 說明 |
|------------|------|
| `ys_raq_email_recipients` | 主要收件人 |
| `ys_raq_email_cc_enabled` / `_emails` | CC 設定 |
| `ys_raq_email_bcc_enabled` / `_emails` | BCC 設定 |
| `ys_raq_show_product_images` | 顯示產品圖片 |
| `ys_raq_show_product_price` | 顯示產品價格 |
| `ys_raq_show_product_sku` | 顯示 SKU |
| `ys_raq_show_quantity` | 顯示數量 |
| `ys_raq_show_line_total` | 顯示小計 |
| `ys_raq_show_total` | 顯示總計 |
| `ys_raq_thumbnail_width` | 縮圖寬度 |
| `ys_raq_show_name_field` / `require_` | Name 欄位控制 |
| `ys_raq_show_email_field` / `require_` | Email 欄位控制 |
| `ys_raq_show_message_field` / `require_` | Message 欄位控制 |
| `ys_raq_show_back_to_shop` / `_label` / `_url` | 返回商店按鈕 |
| `ys_raq_show_update_list` / `_label` | 更新清單按鈕 |
| `ys_raq_custom_fields` | 自訂表單欄位定義 |

## 安全性

- AJAX：`check_ajax_referer()` + `current_user_can( 'manage_options' )`
- 輸入：`sanitize_text_field()` / `sanitize_textarea_field()` / `sanitize_key()` / `sanitize_email()`
- 輸出：`esc_html()` / `esc_attr()` / `esc_url()` / `wp_kses_post()`
- 批次操作：`check_admin_referer( 'bulk-quotes' )`
- 單一刪除：nonce `ys_raq_delete_quote_{id}`

## 電商工具箱整合

共用 `ys-toolbox` 頂層選單，初始化時檢查是否已被其他 YS 外掛建立。歡迎頁面 callback 優先使用其他外掛提供的 `render_toolbox_welcome()` 方法。

## 開發者

**YANGSHEEP DESIGN** — https://yangsheep.com.tw
