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

## 授權

GPL-2.0+

## 開發者

**YANGSHEEP DESIGN** — https://yangsheep.com.tw
