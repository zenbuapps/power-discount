# Power Discount 折扣規則外掛

> 專為台灣電商打造的 WooCommerce 折扣規則引擎。由 [Powerhouse](https://powerhouse.cloud) 開發。

[![Version](https://img.shields.io/badge/version-1.0.0-2271b1.svg)](https://github.com/zenbuapps/power-discount/releases/tag/v1.0.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588a.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

把台灣常見的促銷模式——任選 N 件、紅配綠、第 N 件 X 折、滿額贈、條件免運——全部整合進一個高效能、可視化、可擴充的規則引擎，不用再東拼西湊一堆零散外掛就能做出完整的促銷活動。

![折扣規則列表](docs/screenshots/01-rules-list.png)

---

## 目錄

- [主要特色](#主要特色)
- [9 種折扣策略](#9-種折扣策略)
- [13 種觸發條件](#13-種觸發條件)
- [6 種商品篩選](#6-種商品篩選)
- [安裝方式](#安裝方式)
- [快速上手](#快速上手)
- [畫面截圖](#畫面截圖)
- [常見問題](#常見問題)
- [系統需求](#系統需求)
- [技術架構](#技術架構)
- [授權](#授權)

---

## 主要特色

- **完整視覺化規則編輯器**：不需要寫 JSON、不用改 PHP，所有折扣設定透過表單完成
- **拖拉排序**：直接在規則列表拖拉就能調整優先順序，越上面的越優先
- **每月重複排程**：設定「每月 20 到 30 號」這類循環活動，不需要每月手動開關
- **套用後停止（獨佔模式）**：一條規則符合後即停止後續規則，避免折扣疊加
- **區塊購物車相容**：同時支援經典購物車和 WooCommerce 區塊購物車（Store API）
- **HPOS 相容**：支援 WooCommerce High-Performance Order Storage
- **繁體中文**：整套介面、術語、訊息都是繁中，NT$ 金額顯示
- **折扣統計報表**：內建 Reports 頁面查詢每條規則的套用次數與總折扣金額
- **單元測試齊全**：286 個單元測試、534 個斷言，核心引擎經過完整驗證

---

## 9 種折扣策略

| 策略 | 中文名稱 | 使用情境 |
| --- | --- | --- |
| `simple` | **商品折扣** | 全站 9 折、指定商品折 \$50、本商品固定賣 \$299 |
| `bulk` | **數量階梯折扣** | 1–4 件原價、5–9 件 9 折、10 件以上 8 折 |
| `cart` | **整車折扣** | 滿千折百、整單 9 折 |
| `set` | **任選 N 件組合** | 任選 2 件 \$90、任選 3 件 9 折、任選 4 件現折 \$100 |
| `buy_x_get_y` | **買 X 送 Y** | 買 2 送 1（最便宜）、買 3 送指定贈品 |
| `nth_item` | **第 N 件 X 折** | 第二件 6 折、第三件半價 |
| `cross_category` | **紅配綠／跨類組合** | 上衣 1 件 + 褲子 1 件整組 8 折 |
| `free_shipping` | **條件免運** | 滿 \$1000 免運、特定運送方式運費半價 |
| `gift_with_purchase` | **滿額贈** | 滿 \$1500 自動加入贈品並折抵為 0 元 |

---

## 13 種觸發條件

規則可以設定任意觸發條件的 AND / OR 組合：

- **購物車小計** — `>=`、`<=`、`=` 等比較運算子
- **購物車總數量** — 商品總件數
- **購物車品項數** — 不同商品的種類數
- **顧客累計消費** — 此顧客歷史所有訂單合計
- **使用者角色** — customer、subscriber、vip…
- **是否登入** — 僅限登入會員
- **付款方式** — cod、stripe、ecpay…
- **運送方式** — flat_rate、local_pickup…
- **日期區間** — 特定起迄日期
- **星期幾** — 週一到週日任意組合
- **時段** — 14:00 到 18:00
- **首次訂單** — 是否為顧客的第一筆訂單
- **生日當月** — 僅在顧客生日月份生效

---

## 6 種商品篩選

每條規則都可以用篩選條件精準鎖定要套用折扣的商品：

- **全部商品** — 套用到購物車內所有商品
- **指定商品** — 用 WooCommerce 內建的商品搜尋選擇
- **商品分類** — 支援包含子分類
- **商品標籤** — 多標籤任意組合
- **商品屬性** — 顏色、尺寸、產地等 attribute
- **特價商品** — 僅套用於目前正在特價的商品

篩選可以用 **in（在清單內）** 或 **not in（不在清單內）** 兩種模式，讓你排除特定商品也很容易。

---

## 安裝方式

### 方法一：從 Release 下載

1. 到 [Releases 頁面](https://github.com/zenbuapps/power-discount/releases) 下載最新版的 `power-discount-1.0.0.zip`
2. WordPress 後台 → **外掛** → **安裝外掛** → **上傳外掛**
3. 選擇下載的 zip 檔 → **立即安裝** → **啟用外掛**

### 方法二：從原始碼安裝

```bash
cd wp-content/plugins
git clone https://github.com/zenbuapps/power-discount.git
cd power-discount
composer install --no-dev --optimize-autoloader
```

然後到 WordPress 後台啟用外掛即可。

---

## 快速上手

### 新增第一條折扣規則

1. 啟用外掛後，左側選單會出現 **PowerDiscount** 項目
2. 點選 **折扣規則** → 右上角 **+ 新增規則**
3. 填入基本設定：
   - **規則名稱**：例如「全站 95 折」
   - **折扣類型**：選擇 9 種策略之一
   - **狀態**：啟用或停用
   - **排程**：單次日期區間或每月重複
   - **購物車顯示文字**：顧客在購物車看到的折扣標籤
4. **折扣內容**：每種策略都有自己的設定介面（例如數量階梯會讓你新增多個級別）
5. **商品篩選**：選擇套用的商品範圍，留空就是全部商品
6. **觸發條件**：例如「購物車小計 ≥ 1000」，留空就是永遠觸發
7. 點 **儲存規則** 完成

![編輯規則畫面](docs/screenshots/02-rule-edit.png)

### 調整規則優先順序

在折扣規則列表，每一列最左邊有拖拉手柄（三條橫線圖示）。用滑鼠抓住就可以上下拖動調整順序，**越上面的規則越優先套用**。

### 設定每月重複活動

在編輯規則頁面的「排程」區塊，選擇 **每月重複**，然後填入「每月 1 到 31 號」當中的任意區間。例如設 20–30 就代表每個月 20 號到 30 號之間規則會生效。

![每月重複排程](docs/screenshots/03-free-shipping-edit.png)

### 指定免運適用於哪些運送方式

編輯「條件免運」類型的規則時，會看到 **適用於哪些物流方式** 區塊，自動列出 WooCommerce 設定好的所有運送區域與方式。點擊 chip 即可切換選擇狀態——**全部未勾選就代表套用到所有物流方式**。

![免運 chip 選擇](docs/screenshots/06-shipping-chip.png)

### 查看折扣統計報表

左側選單點選 **PowerDiscount** → **報表**，可依日期區間查詢：
- 總折扣金額
- 套用次數
- 產生折扣的訂單數
- 熱門規則排行

![折扣統計報表](docs/screenshots/04-reports.png)

---

## 畫面截圖

### 折扣規則列表

![規則列表](docs/screenshots/01-rules-list.png)

列表頁具備：拖拉排序（左側把手）、狀態即時切換（Toggle 開關）、雙語類型徽章、排程預覽（含「每月 X–Y 號」）、已使用次數統計，以及常駐顯示的編輯/複製/刪除操作連結。

### 規則編輯器

![編輯規則](docs/screenshots/02-rule-edit.png)

四個清楚分區：基本設定 / 折扣內容 / 商品篩選 / 觸發條件。每種折扣類型都有專屬的設定介面（例如數量階梯可新增多個級別、紅配綠可新增多個分類群組）。

### 購物車與結帳頁折扣通知

![購物車](docs/screenshots/05-cart.png)

購物車與結帳頁下方會自動插入橘色「已套用折扣」總整理框，列出每條規則套用的金額與套用到的商品清單，讓顧客清楚看到省了多少錢。相容經典購物車和 WooCommerce 區塊購物車。

### 折扣統計報表

![報表](docs/screenshots/04-reports.png)

儀表卡顯示總折扣、套用次數、訂單數等關鍵指標，下方表格依規則拆解熱門度，方便行銷團隊追蹤促銷成效。

---

## 常見問題

### Q1. 這個外掛跟 WooCommerce 內建的優惠券衝突嗎？

不會。Power Discount 是透過 WooCommerce 計價流程直接調整商品單價與建立折扣 fee，和優惠券是兩套獨立機制，可以同時使用。

### Q2. 支援 WooCommerce 區塊購物車嗎？

支援。外掛同時相容經典購物車與區塊購物車（Store API），訂單建立後折扣會寫入 order line item meta。

### Q3. 多條規則可以同時套用嗎？

可以。規則會依列表順序由上到下依序執行。如果希望某條規則符合後就停止後續規則，在編輯頁面勾選 **套用後停止** 即可。

### Q4. 可以匯出折扣套用紀錄嗎？

目前 Reports 頁面可依日期區間查詢統計。CSV 匯出功能會在後續版本加入。

### Q5. 滿額贈的贈品顧客需要自己加入購物車嗎？

不用。當購物車達到門檻時，外掛會**自動**把贈品加入購物車，並將其價格折抵為 0 元。顧客無需手動操作。

### Q6. 規則的觸發條件可以「用條件 A **或** 條件 B」嗎？

可以。觸發條件區塊頂端有 **Logic** 下拉選單，可選 **AND（全部符合）** 或 **OR（任一符合）**。

### Q7. 可以限制某條規則只能用 N 次嗎？

可以。編輯規則頁面的 **使用次數上限** 填入數字後，這條規則在達到此次數後就會自動停止套用。

---

## 系統需求

- **PHP** 7.4 或以上
- **WordPress** 6.0 或以上
- **WooCommerce** 7.0 或以上（已測試至 8.5）
- **資料庫**：MySQL 5.7+ 或 MariaDB 10.3+
- **支援 HPOS**：已宣告相容 WooCommerce High-Performance Order Storage

---

## 技術架構

Power Discount 採用 Strategy Pattern + Registry 設計，Domain 層完全與 WooCommerce 解耦：

```
src/
├── Domain/         # Rule、CartContext、DiscountResult 純值物件
├── Strategy/       # 9 種折扣策略各自獨立實作
├── Condition/      # 13 種觸發條件各自獨立實作
├── Filter/         # 6 種商品篩選各自獨立實作
├── Engine/         # Calculator、Aggregator、ExclusivityResolver
├── Integration/    # WooCommerce hooks（CartHooks、ShippingHooks 等）
├── Persistence/    # DatabaseAdapter（WpdbAdapter、InMemoryDatabaseAdapter）
├── Repository/     # RuleRepository、OrderDiscountRepository
├── Admin/          # 後台頁面、表單處理、Ajax 端點
├── Frontend/       # 前台元件（GiftProgressBar、FreeShippingBar）
├── Install/        # Migrator、Activator
└── I18n/           # 執行階段 gettext filter，免 .mo 編譯
```

---

## 授權

本外掛採用 [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) 授權。

---

## 關於 Powerhouse

**Power Discount** 由 [Powerhouse](https://powerhouse.cloud) 開發與維護。Powerhouse 專注於為台灣電商提供可擴充、可維護、符合在地需求的 WordPress 與 WooCommerce 解決方案。

如果你在使用上遇到任何問題、或有功能建議，歡迎：

- 🐛 [回報 Issue](https://github.com/zenbuapps/power-discount/issues)
- 🌐 [Powerhouse 官網](https://powerhouse.cloud)

---

<p align="center">Made with ❤️ in Taiwan by <a href="https://powerhouse.cloud">Powerhouse</a></p>
