# YS RAQ Addons

為 **YITH WooCommerce Request a Quote**（免費版）提供全方位功能增強的 WordPress 外掛。

## 需求

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 8.0+
- YITH WooCommerce Request a Quote（免費版）

## 功能總覽

### 迷你詢價車
- **Mini Cart Widget** — 類似 WooCommerce Mini Cart 的下拉式詢價清單
- **即時 AJAX 更新** — 新增/移除產品時自動刷新
- **響應式設計** — 桌面版 hover 顯示，行動裝置 bottom sheet 效果
- **可自訂** — 顯示/隱藏縮圖、價格、數量、變體資訊
- **模板可覆寫** — 複製到佈景主題即可自訂外觀

### 報價歷史紀錄
- **自動記錄** — 攔截報價提交，自動儲存為自訂文章類型（CPT）
- **報價列表** — 使用 WP_List_Table 呈現，支援搜尋、篩選、排序、分頁
- **狀態管理** — 5 種狀態（新的 / 處理中 / 已回覆 / 已過期 / 已關閉）
- **快速操作** — 列表頁下拉選單即時變更狀態（AJAX）
- **批次操作** — 批次標記狀態或刪除
- **詳情頁面** — 客戶資訊、產品清單、自訂欄位資料一目瞭然

### 自訂表單欄位
- **9 種欄位類型** — 文字、電子郵件、電話、數字、多行文字、下拉選單、單選按鈕、核取方塊、日期
- **拖曳排序** — 直覺式調整欄位順序
- **啟用/停用** — 單獨控制每個欄位是否顯示
- **必填控制** — 前後端同步驗證

### 原生欄位控制
- **Name / Email / Message** — 可個別控制顯示/隱藏與必填屬性
- **即時生效** — 透過 HTML 操作直接修改 YITH 原生表單

### 電子郵件增強
- **收件人管理** — 自訂主要收件人，支援 CC / BCC
- **自訂欄位附帶** — 表單自訂欄位資料自動附加到通知郵件
- **產品資訊控制** — 控制郵件中顯示的產品欄位

### 顯示設定
- **產品資訊** — 控制圖片、價格、SKU、數量、小計、總計的顯示
- **縮圖寬度** — 自訂產品縮圖寬度（30-300px）
- **頁面按鈕** — 「返回商店」與「更新清單」按鈕的顯示與文字自訂

## 安裝

1. 上傳 `ys-raq-addons` 資料夾到 `/wp-content/plugins/`
2. 在 WordPress 後台啟用外掛
3. 確認 YITH WooCommerce Request a Quote 已啟用
4. 前往 **電商工具箱 → RAQ 擴充** 進行設定

## 使用方式

### 後台設定

外掛設定位於 **電商工具箱 → RAQ 擴充**，包含三個分頁：

| 分頁 | 說明 |
|------|------|
| 一般設定 | 報價通知收件人管理（主要收件人、CC、BCC） |
| 顯示設定 | 產品資訊顯示、原生欄位控制、頁面按鈕設定 |
| 表單欄位 | 自訂表單欄位管理（新增、編輯、排序、啟停用） |

報價歷史紀錄位於 **電商工具箱 → 報價單紀錄**。

### Shortcode

#### 迷你詢價車

```
[ys_raq_mini_cart]
```

**可用參數：**

| 參數 | 預設值 | 說明 |
|------|--------|------|
| `title` | 詢價清單 | Widget 標題 |
| `item_name` | 項商品 | 品項名稱（單數） |
| `item_plural_name` | 項商品 | 品項名稱（複數） |
| `button_label` | 查看詢價清單 | 按鈕標籤 |
| `show_title_inside` | 0 | 在面板內顯示標題（1/0） |
| `show_thumbnail` | 1 | 顯示產品縮圖（1/0） |
| `show_price` | 1 | 顯示價格（1/0） |
| `show_quantity` | 1 | 顯示數量（1/0） |
| `show_variations` | 1 | 顯示變體資訊（1/0） |

**完整範例：**

```
[ys_raq_mini_cart title="我的詢價車" show_thumbnail="1" show_price="1" button_label="前往詢價單"]
```

#### 詢價數量

```
[ys_raq_count]
```

| 參數 | 預設值 | 說明 |
|------|--------|------|
| `show_text` | 1 | 顯示文字（1/0） |
| `item_name` | 項商品 | 品項名稱 |
| `link` | 1 | 是否連結至詢價頁面（1/0） |

## 模板覆寫

複製以下檔案到佈景主題目錄即可自訂：

```
ys-raq-addons/widgets/mini-cart.php → yourtheme/ys-raq-addons/widgets/mini-cart.php
```

## Hooks

### Actions

| Hook | 說明 |
|------|------|
| `ys_raq_before_mini_cart` | Mini Cart 模板渲染前 |
| `ys_raq_after_mini_cart` | Mini Cart 模板渲染後 |
| `ys_raq_quote_saved` | 報價紀錄建立後（參數：`$post_id`, `$products`） |

### Filters

| Filter | 說明 |
|--------|------|
| `ys_raq_before_print_mini_cart` | 控制是否顯示 Widget（回傳 false 隱藏） |
| `ys_raq_mini_cart_empty_message` | 自訂空清單訊息 |
| `ys_raq_mini_cart_button_label` | 自訂按鈕標籤 |
| `ys_toolbox_plugins` | 電商工具箱外掛列表 |

### JavaScript Events

| Event | 說明 |
|-------|------|
| `ys_raq_mini_cart_refreshed` | Widget AJAX 刷新完成後觸發 |
| `ys_raq_item_removed` | 項目移除成功後觸發 |

## HPOS 相容性

本外掛完全支援 WooCommerce High-Performance Order Storage（HPOS）。

## Changelog

### 2.3.10 — 2026-04-20

- 修正：原生必填欄位在 label 仍可能出現兩個 `*` 星號的殘留案例（`hide_native_form_fields()` 改為 row-scoped + 全站 label 去重安全網，徹底阻斷重複注入）
- 修正：報價清單數量 AJAX 自動更新完全不觸發（JS 選擇器誤植 `#yith-ywrq-form`，應為 `#yith-ywraq-form`；YITH 表單 id 帶 `a`，列表 table id 不帶 `a`）

### 2.3.9 — 2026-04-16

- 新增：報價清單數量 AJAX 自動更新——改數量後 500ms debounce 自動送出 `update_raq`，抽換 `#yith-ywrq-table-list`，免手動按 Update List（原按鈕自動隱藏）
- 新增：更新過程 loading 遮罩（spinner）與右下角 toast 提示（`已更新` / `更新失敗`）
- 調整：`ys-raq-quote-page.js` 改由 `enqueue_assets()` 正式載入（之前檔案存在但未入列）

### 2.3.8 — 2026-04-16

- 修正：Email 等原生必填欄位在 label 旁出現兩個 `*` 星號的問題（`hide_native_form_fields()` 改為先清除再重建，避免與 YITH RAQ 原生模板重複注入 `<abbr class="required">`）

### 2.0.0 — 2026-03-13

- 新增：報價歷史紀錄系統（CPT + WP_List_Table + 狀態管理 + 批次操作）
- 新增：自訂表單欄位（9 種類型、拖曳排序、啟停用、必填控制）
- 新增：原生欄位控制（Name / Email / Message 顯示與必填）
- 新增：電子郵件增強（收件人管理、CC/BCC、自訂欄位附帶）
- 新增：產品資訊顯示設定（圖片、價格、SKU、數量、小計、總計）
- 新增：產品縮圖寬度設定（30-300px）
- 新增：頁面按鈕控制（返回商店、更新清單）
- 新增：電商工具箱共用選單
- 變更：後台配色改為淺藍莫蘭迪色系
- 變更：管理介面全面改版（卡片式佈局、分頁設定）

### 1.0.0 — 2026-02-01

- 新增：迷你詢價車 Widget（AJAX 刷新、響應式設計）
- 新增：Shortcode 支援（`[ys_raq_mini_cart]`、`[ys_raq_count]`）
- 新增：模板可覆寫

## 授權

GPL-2.0+

## 開發者

**YANGSHEEP DESIGN** — https://yangsheep.com.tw
