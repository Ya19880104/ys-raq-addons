# 架構文件 — YS RAQ Addons

## 概述

YS RAQ Addons 是一個基於 WordPress 外掛架構的 WooCommerce 擴充套件，為 YITH WooCommerce Request a Quote（免費版）提供進階功能。採用 PSR-4 自動載入、Singleton 模式、MVC 分層架構。

## 技術棧

| 項目 | 版本 |
|------|------|
| PHP | 8.0+ |
| WordPress | 6.0+ |
| WooCommerce | 8.0+ |
| jQuery | WordPress 內建 |
| jQuery UI Sortable | WordPress 內建 |

## 目錄結構

```
ys-raq-addons/
├── ys-raq-addons.php              # 主入口：常數定義、自動載入、HPOS 宣告
├── composer.json                   # PSR-4 自動載入配置
├── index.php                       # 安全性空檔
├── README.md                       # 使用說明
├── CHANGELOG.md                    # 版本紀錄
├── ARCHITECTURE.md                 # 本文件
│
├── src/                            # PHP 源碼（PSR-4: YangSheep\RaqAddons\）
│   ├── YSRaqAddons.php            # 主類別（Singleton），初始化所有模組
│   ├── YSRaqAjax.php              # AJAX 處理器（靜態類別）
│   ├── YSRaqShortcodes.php        # Shortcode 註冊
│   │
│   ├── Admin/                      # 後台管理
│   │   ├── YSRaqSettings.php      # 設定頁面、選單註冊、頁面渲染、AJAX 欄位管理
│   │   ├── YSRaqQuoteHistory.php  # 報價歷史 CPT 註冊與資料攔截
│   │   └── YSRaqQuoteListTable.php # WP_List_Table 報價列表
│   │
│   ├── Frontend/                   # 前端功能
│   │   └── YSRaqQuotePage.php     # 報價頁面增強（Output Buffering + HTML 操作）
│   │
│   ├── Email/                      # 電子郵件
│   │   └── YSRaqEmailHandler.php  # 郵件收件人管理、產品資訊控制、自訂欄位附帶
│   │
│   └── Widgets/                    # WordPress Widget
│       └── YSRaqMiniCartWidget.php # 迷你詢價車 Widget
│
├── templates/                      # 可覆寫模板
│   └── widgets/
│       └── mini-cart.php
│
└── assets/                         # 前端資源
    ├── css/
    │   ├── ys-raq-addons-admin.css # 後台管理樣式
    │   ├── ys-raq-quote-page.css   # 報價頁面樣式
    │   └── ys-raq-mini-cart.css    # Mini Cart 樣式
    └── js/
        ├── ys-raq-addons-admin.js  # 後台管理腳本（Tab 切換、AJAX、拖曳排序）
        ├── ys-raq-quote-page.js    # 報價頁面腳本
        └── ys-raq-mini-cart.js     # Mini Cart 腳本（AJAX 刷新、移除）
```

## 核心架構

### 初始化流程

```
ys-raq-addons.php
  ├── 定義常數（版本、路徑、URL）
  ├── 載入 PSR-4 autoloader
  ├── 宣告 HPOS 相容性
  ├── 註冊外掛設定連結
  └── YSRaqAddons::instance()
        └── plugins_loaded (priority 20)
              ├── 檢查 YITH RAQ 是否啟用
              ├── Admin\YSRaqSettings::get_instance()    # 後台設定
              ├── Admin\YSRaqQuoteHistory::get_instance() # 報價紀錄
              ├── Email\YSRaqEmailHandler::get_instance()  # 郵件處理
              ├── Frontend\YSRaqQuotePage::get_instance()  # 前端頁面
              ├── YSRaqAjax::init()                        # AJAX
              ├── Widgets\YSRaqMiniCartWidget (register)   # Widget
              └── YSRaqShortcodes::init()                  # Shortcode
```

### 設計模式

| 模式 | 使用於 | 說明 |
|------|--------|------|
| **Singleton** | `YSRaqAddons`, `YSRaqSettings`, `YSRaqQuoteHistory`, `YSRaqEmailHandler`, `YSRaqQuotePage` | 確保全域只有一個實例 |
| **Static Class** | `YSRaqAjax`, `YSRaqShortcodes` | 無狀態的工具類別 |
| **Template Method** | `YSRaqMiniCartWidget` | Widget 繼承 `WP_Widget` |
| **Output Buffering** | `YSRaqQuotePage`, `YSRaqEmailHandler` | 攔截並修改 YITH 輸出的 HTML |

## 模組詳解

### 1. 後台設定 (`YSRaqSettings`)

最大的單一類別，負責：
- **選單註冊** — 電商工具箱共用選單 + RAQ 擴充子選單 + 報價單紀錄子選單
- **腳本載入** — CSS 使用 `filemtime()` 版本控制，JS 同理
- **設定頁面** — 三分頁（一般設定、顯示設定、表單欄位）
- **報價列表** — 渲染 `WP_List_Table`
- **報價詳情** — 單一報價的完整資訊頁面
- **AJAX 處理** — 欄位 CRUD、排序、啟停用
- **歡迎頁面** — 電商工具箱總覽頁面

### 2. 報價歷史 (`YSRaqQuoteHistory`)

- **CPT 註冊** — `ys_raq_quote`（非公開、不顯示 UI）
- **資料攔截** — Hook `ywraq_process` 和 `send_raq_mail_notification`
- **自訂欄位** — 從 `$_POST` 讀取並儲存到 post meta
- **防重複** — 使用 `did_action()` 檢查避免雙重儲存

### 3. 報價列表 (`YSRaqQuoteListTable`)

繼承 `WP_List_Table`，提供：
- 8 個欄位（checkbox、編號、客戶、產品數、金額、狀態、日期、操作）
- 3 個可排序欄位（ID、日期、金額）
- 狀態篩選器
- 批次操作（標記狀態、刪除）
- 單一刪除（帶 nonce 驗證）

### 4. 前端頁面 (`YSRaqQuotePage`)

使用 Output Buffering 攔截 YITH 報價頁面 HTML：

```
woocommerce_before_template_part → 開啟 ob_start()
woocommerce_after_template_part  → 取得 HTML → 處理 → 輸出
```

HTML 處理流程：
1. `inject_custom_styles()` — 注入產品縮圖寬度等 CSS
2. `modify_product_table()` — 根據設定隱藏/顯示產品資訊欄位
3. `inject_custom_fields()` — 在 `</form>` 前插入自訂表單欄位
4. `hide_native_form_fields()` — 控制原生欄位的顯示/必填
5. `modify_buttons()` — 控制「返回商店」與「更新清單」按鈕

### 5. 電子郵件 (`YSRaqEmailHandler`)

- **收件人管理** — Filter `ywraq_email_recipient` 替換收件人，Filter `wp_mail` 添加 CC/BCC headers
- **產品資訊控制** — 使用 `<div id="ys-raq-product-table">` 包裹產品表格，透過 CSS nth-child 隱藏欄位
- **自訂欄位** — 在 `yith_ywraq_email_after_raq_table` hook 輸出自訂欄位資料表格

### 6. AJAX (`YSRaqAjax`)

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

### Post Meta（報價紀錄）

| Meta Key | 說明 |
|----------|------|
| `_ys_raq_quote_number` | 報價編號（RQ-YYYYMMDD-XXXX） |
| `_ys_raq_customer_name` | 客戶姓名 |
| `_ys_raq_customer_email` | 客戶電子郵件 |
| `_ys_raq_customer_message` | 客戶留言 |
| `_ys_raq_status` | 報價狀態 |
| `_ys_raq_products` | 產品清單（序列化陣列） |
| `_ys_raq_total` | 預估總金額 |
| `_ys_raq_custom_fields_data` | 自訂欄位資料（序列化陣列） |
| `_ys_raq_missing_required` | 缺少的必填欄位（序列化陣列） |

### Options

| Option Key | 說明 |
|------------|------|
| `ys_raq_email_recipients` | 主要收件人 |
| `ys_raq_email_cc_enabled` | CC 啟用 |
| `ys_raq_email_cc_emails` | CC 電子郵件 |
| `ys_raq_email_bcc_enabled` | BCC 啟用 |
| `ys_raq_email_bcc_emails` | BCC 電子郵件 |
| `ys_raq_show_product_images` | 顯示產品圖片 |
| `ys_raq_show_product_price` | 顯示產品價格 |
| `ys_raq_show_product_sku` | 顯示 SKU |
| `ys_raq_show_quantity` | 顯示數量 |
| `ys_raq_show_line_total` | 顯示小計 |
| `ys_raq_show_total` | 顯示總計 |
| `ys_raq_thumbnail_width` | 縮圖寬度 |
| `ys_raq_show_name_field` | 顯示 Name 欄位 |
| `ys_raq_require_name_field` | Name 必填 |
| `ys_raq_show_email_field` | 顯示 Email 欄位 |
| `ys_raq_require_email_field` | Email 必填 |
| `ys_raq_show_message_field` | 顯示 Message 欄位 |
| `ys_raq_require_message_field` | Message 必填 |
| `ys_raq_show_back_to_shop` | 顯示返回商店按鈕 |
| `ys_raq_back_to_shop_label` | 返回商店按鈕文字 |
| `ys_raq_back_to_shop_url` | 返回商店自訂 URL |
| `ys_raq_show_update_list` | 顯示更新清單按鈕 |
| `ys_raq_update_list_label` | 更新清單按鈕文字 |
| `ys_raq_custom_fields` | 自訂表單欄位定義（序列化陣列） |

## 安全性

- 所有 AJAX 請求使用 `check_ajax_referer()` 驗證 nonce
- 後台操作使用 `current_user_can( 'manage_options' )` 權限檢查
- 使用者輸入使用 `sanitize_text_field()`、`sanitize_textarea_field()`、`sanitize_key()`、`sanitize_email()` 等函數清理
- 輸出使用 `esc_html()`、`esc_attr()`、`esc_url()`、`wp_kses_post()` 等函數跳脫
- 批次操作使用 `check_admin_referer( 'bulk-quotes' )` 驗證
- 單一刪除使用個別 nonce `ys_raq_delete_quote_{id}`

## 電商工具箱整合

外掛使用共用的 `ys-toolbox` 頂層選單。初始化時檢查選單是否已存在：

```php
foreach ( $menu as $item ) {
    if ( isset( $item[2] ) && 'ys-toolbox' === $item[2] ) {
        $toolbox_exists = true;
        break;
    }
}
```

歡迎頁面 callback 會依序檢查其他 YS 外掛是否提供了 `render_toolbox_welcome()` 方法，避免重複註冊。

## 開發者

**YANGSHEEP DESIGN** — https://yangsheep.com.tw
