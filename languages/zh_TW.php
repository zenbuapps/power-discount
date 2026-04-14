<?php
/**
 * Power Discount — 繁體中文翻譯。
 *
 * 由 src/I18n/Loader.php 透過 gettext filter 在 runtime 套用。
 * 與標準 .po/.mo 檔不同：純 PHP array，無需 msgfmt 編譯，便於團隊維護。
 *
 * @return array<string, string>
 */

return [
    // === Menu ===
    'PowerDiscount'                                     => 'PowerDiscount',
    'Discount Rules'                                    => '折扣規則',
    'Reports'                                           => '報表',

    // === List page ===
    'Power Discount Rules'                              => '折扣規則管理',
    'Add New'                                           => '新增規則',
    'Title'                                             => '名稱',
    'Type'                                              => '類型',
    'Status'                                            => '狀態',
    'Priority'                                          => '優先序',
    'Schedule'                                          => '排程',
    'Used'                                              => '已使用',
    'Always'                                            => '不限',
    'Enabled'                                           => '啟用',
    'Disabled'                                          => '已停用',
    'Toggle'                                            => '切換',
    'Edit'                                              => '編輯',
    'Duplicate'                                         => '複製',
    'Delete'                                            => '刪除',
    'Delete this rule?'                                 => '確定要刪除這條規則嗎？',
    'Permission denied.'                                => '權限不足。',
    'You do not have permission to access this page.'   => '你沒有權限存取這個頁面。',
    'Rule deleted.'                                     => '規則已刪除。',
    'Rule duplicated.'                                  => '規則已複製。',
    'Rule created.'                                     => '規則已建立。',
    'Rule updated.'                                     => '規則已更新。',

    // === Edit page sections ===
    'Add Rule'                                          => '新增規則',
    'Edit Rule'                                         => '編輯規則',
    'Back to list'                                      => '回到列表',
    '1. Basic details'                                  => '1. 基本設定',
    '2. Discount settings'                              => '2. 折扣內容',
    '3. Product filters'                                => '3. 商品篩選',
    '4. Conditions'                                     => '4. 觸發條件',
    'Save rule'                                         => '儲存規則',
    'Create rule'                                       => '建立規則',

    // === Basic details fields ===
    'Rule name'                                         => '規則名稱',
    'Discount type'                                     => '折扣類型',
    'Lower number = higher priority.'                   => '數字越小，優先序越高。',
    'Exclusive'                                         => '互斥',
    'Stop after this rule matches'                      => '此規則命中後，停止套用後續規則',
    'to'                                                => '到',
    'Leave blank for no schedule limit.'                => '留空表示不限期間。',
    'Usage limit'                                       => '使用次數上限',
    'Used: %d'                                          => '已使用：%d 次',
    'Cart label'                                        => '購物車顯示文字',
    'Shown to customers in the cart when this rule applies.' => '此規則套用時會在購物車顯示給顧客的文字。',

    // === Strategy type labels (already Chinese; passthrough) ===
    'Simple — 商品折扣'                                  => 'Simple — 商品折扣',
    'Bulk — 數量階梯折扣'                                 => 'Bulk — 數量階梯折扣',
    'Cart — 整車折扣'                                    => 'Cart — 整車折扣',
    'Set — 任選 N 件組合'                                => 'Set — 任選 N 件組合',
    'Buy X Get Y — 買 X 送 Y'                           => 'Buy X Get Y — 買 X 送 Y',
    'Nth item — 第 N 件 X 折'                           => 'Nth item — 第 N 件 X 折',
    'Cross-category — 紅配綠（跨類組合）'                  => 'Cross-category — 紅配綠（跨類組合）',
    'Free Shipping — 條件免運'                           => 'Free Shipping — 條件免運',
    'Gift with purchase — 滿額贈'                        => 'Gift with purchase — 滿額贈',

    // === Strategy descriptions (already Chinese; passthrough) ===
    '對符合篩選條件的每件商品套用一個固定折扣（百分比、扣固定金額、或設定固定售價）。最常用，例：全站 9 折、指定商品折 $50、本商品固定賣 $299。'
        => '對符合篩選條件的每件商品套用一個固定折扣（百分比、扣固定金額、或設定固定售價）。最常用，例：全站 9 折、指定商品折 $50、本商品固定賣 $299。',
    '依購買數量決定折扣級別。例：1–4 件原價、5–9 件 9 折、10 件以上 8 折。可指定算總量或逐項計算。'
        => '依購買數量決定折扣級別。例：1–4 件原價、5–9 件 9 折、10 件以上 8 折。可指定算總量或逐項計算。',
    '整張購物車達到條件後，從 cart total 扣固定金額或百分比。例：滿千折百、整單 9 折。'
        => '整張購物車達到條件後，從整張購物車金額扣固定金額或百分比。例：滿千折百、整單 9 折。',
    '從符合條件的商品中任選 N 件，套用組合價、組合折扣或現折固定金額。例：任選 2 件 $90、任選 3 件 9 折、任選 4 件現折 $100。'
        => '從符合條件的商品中任選 N 件，套用組合價、組合折扣或現折固定金額。例：任選 2 件 $90、任選 3 件 9 折、任選 4 件現折 $100。',
    '購買 X 件就送 Y 件。贈品可以是同樣的商品、購物車中最便宜的商品、或指定的商品清單。可開啟循環模式重複套用。'
        => '購買 X 件就送 Y 件。贈品可以是同樣的商品、購物車中最便宜的商品、或指定的商品清單。可開啟循環模式重複套用。',
    '依購物車內商品的順序，第 N 件套用對應折扣。例：第二件 6 折、第三件半價、第四件免費。可循環。'
        => '依購物車內商品的順序，第 N 件套用對應折扣。例：第二件 6 折、第三件半價、第四件免費。可循環。',
    '要求顧客同時購買多個分類的商品才能享折扣。例：上衣一件 + 褲子一件，整組 8 折。可形成多組重複套用。'
        => '要求顧客同時購買多個分類的商品才能享折扣。例：上衣一件 + 褲子一件，整組 8 折。可形成多組重複套用。',
    '條件達成後，免除全部運費或運費打折。例：滿 $1000 免運、特定運送方式運費半價。'
        => '條件達成後，免除全部運費或運費打折。例：滿 $1000 免運、特定運送方式運費半價。',
    '購物車金額達到門檻就送指定商品。顧客需自行把贈品加入購物車，系統會自動把它的價格折抵為 0。例：滿 $1000 送馬克杯。'
        => '購物車金額達到門檻就送指定商品。顧客需自行把贈品加入購物車，系統會自動把它的價格折抵為 0。例：滿 $1000 送馬克杯。',

    // === Strategy: simple ===
    'Discount method'                                   => '折扣方式',
    'Percentage off'                                    => '百分比折扣',
    'Flat amount off (per item)'                        => '固定金額折扣（每件）',
    'Fixed price (each item becomes this price)'        => '固定售價（每件改成這個價格）',
    'Value'                                             => '數值',
    '% for percentage, NT$ for flat/fixed price'        => '百分比填 %、固定金額填 NT$',

    // === Strategy: bulk ===
    'Count scope'                                       => '計算範圍',
    'Cumulative — sum qty across all matched items'     => '累計 — 所有符合的商品數量加總',
    'Per item — each line counts on its own'            => '逐項 — 每個商品自己算',
    'Quantity tiers'                                    => '數量階梯',
    'From'                                              => '從',
    'Add tier'                                          => '新增階梯',

    // === Strategy: cart ===
    'Method'                                            => '方式',
    'Percentage off whole cart'                         => '整張購物車打折（百分比）',
    'Fixed amount off cart total'                       => '整張購物車扣固定金額',
    'Fixed amount off per item'                         => '每件商品扣固定金額',

    // === Strategy: set ===
    'Bundle size (N items)'                             => '組合件數（N 件）',
    'Set method'                                        => '組合方式',
    'Set price — N items for NT$X'                      => '組合價 — N 件 NT$X',
    'Set percentage — N items for X% off'               => '組合折扣 — N 件 X% off',
    'Set flat off — N items for flat NT$X off (Taiwan exclusive)'
        => '組合現折 — N 件現折 NT$X（Taiwan 獨家）',
    'Repeat'                                            => '重複套用',
    'Apply multiple bundles if customer has enough items'
        => '若顧客件數足夠，可組成多組',

    // === Strategy: buy_x_get_y ===
    'Trigger — buy this many'                           => '觸發 — 要買幾件',
    'Any filter-matching item'                          => '任一符合篩選的商品',
    'Specific products (set via filter below)'          => '特定商品（透過下方篩選器設定）',
    'Reward — get this many'                            => '贈品 — 送幾件',
    'Of the same triggering product'                    => '與觸發的同一商品',
    'Cheapest item in cart'                             => '購物車中最便宜的商品',
    'Specific products (enter IDs)'                     => '特定商品（透過下方篩選器設定）',
    'Reward discount'                                   => '贈品折扣',
    'Free'                                              => '免費',
    'Flat amount off'                                   => '固定金額折扣',
    'Recursive'                                         => '循環',
    'Apply the rule repeatedly while the cart allows it'
        => '只要購物車件數足夠就重複套用',

    // === Strategy: nth_item ===
    'Per-position discount'                             => '依順序設定折扣',
    'Nth item:'                                         => '第 N 件：',
    'Sort items by'                                     => '商品排序方式',
    'Price (high → low)'                                => '價格 高 → 低',
    'Price (low → high)'                                => '價格 低 → 高',
    'Cycle tiers every K items'                         => '每 K 件循環一次',

    // === Strategy: cross_category ===
    'Groups (all must be satisfied)'                    => '分類組合（所有組必須同時滿足）',
    'Group name'                                        => '組合名稱',
    'e.g. Tops'                                         => '例如：上衣',
    'Categories'                                        => '商品分類',
    'Select categories'                                 => '請選擇商品分類',
    'Min qty'                                           => '最少件數',
    'Add group'                                         => '新增分類組',
    'Need at least 2 groups.'                           => '至少需要 2 個分類組。',
    'Reward'                                            => '優惠內容',
    'Percentage off bundle'                             => '整組打折（百分比）',
    'Flat amount off bundle'                            => '整組扣固定金額',
    'Fixed bundle price'                                => '整組固定價格',
    'Form multiple bundles when possible'               => '可以組成多組時就重複套用',
    'Remove group'                                      => '移除這組',

    // === Strategy: free_shipping ===
    'Remove shipping entirely'                          => '完全免運',
    'Percentage off shipping cost'                      => '運費按百分比折扣',
    'Percentage off (1–100)'                            => '折扣百分比（1–100）',

    // === Strategy: gift_with_purchase ===
    'Spend threshold'                                   => '滿額門檻',
    'When cart subtotal reaches this amount, the gift becomes free.'
        => '購物車小計達到此金額後，贈品就會變免費。',
    'Gift products'                                     => '贈品商品',
    'Search gift products'                              => '搜尋贈品商品',
    'Customers must add the gift to the cart themselves; the plugin will discount it to NT$0 once the threshold is met. If multiple gifts are eligible, the most expensive one is freed.'
        => '顧客需自行將贈品加入購物車，系統會在達到門檻後自動把它折抵為 NT$0。若多個贈品都符合，會優先折抵最貴的那件。',
    'Gift quantity'                                     => '贈品數量',

    // === Filter builder ===
    'Which products in the cart should this rule apply to? Leave empty to apply to all products.'
        => '此規則要套用到購物車裡哪些商品？留空表示套用到全部商品。',
    'Add filter'                                        => '新增篩選條件',
    'All products'                                      => '全部商品',
    'Specific products'                                 => '特定商品',
    'Tags'                                              => '商品標籤',
    'Attributes'                                        => '商品屬性',
    'On sale'                                           => '特價中商品',
    'in list'                                           => '在清單內',
    'not in list'                                       => '不在清單內',
    'Search products'                                   => '搜尋商品',
    'Select tags'                                       => '請選擇商品標籤',

    // === Condition builder ===
    'When should this rule apply? Leave empty to apply always.'
        => '此規則何時觸發？留空表示永遠觸發。',
    'Logic'                                             => '組合邏輯',
    'AND (all)'                                         => 'AND（全部都要符合）',
    'OR (any)'                                          => 'OR（任一個符合即可）',
    'Add condition'                                     => '新增條件',
    'Cart subtotal'                                     => '購物車小計',
    'Cart total quantity'                               => '購物車總件數',
    'Number of line items'                              => '購物車品項數',
    'Customer total spent (lifetime)'                   => '顧客累計消費（歷史）',
    'User role'                                         => '使用者角色',
    'User logged in'                                    => '使用者登入狀態',
    'Payment method'                                    => '付款方式',
    'Shipping method'                                   => '運送方式',
    'Date range'                                        => '日期區間',
    'Day of week'                                       => '星期',
    'Time of day'                                       => '時段',
    'First order'                                       => '首次購買',
    'Birthday month'                                    => '生日月份',
    'Comma-separated role slugs'                        => '以逗號分隔的角色 slug',
    'Comma-separated method slugs'                      => '以逗號分隔的方式 slug',
    'Require logged in'                                 => '必須已登入',
    'Customer first order only'                         => '僅限首次購買',
    'Match current month'                               => '比對當前月份',

    // === Reports page ===
    'Power Discount Reports'                            => '折扣規則報表',
    'Manage Rules'                                      => '管理規則',
    'Total discount given'                              => '已給予總折扣',
    'Orders affected'                                   => '影響訂單數',
    'Active rules tracked'                              => '統計中規則數',
    'Rule performance'                                  => '規則使用情況',
    'No discount records yet. Reports populate as orders get placed.'
        => '尚無折扣紀錄，等訂單下單後就會有資料。',
    'Rule'                                              => '規則',
    'Times applied'                                     => '套用次數',
    'Total discount'                                    => '折抵總額',

    // === Frontend ===
    'You qualify for free shipping!'                    => '您已符合免運資格！',
    'Add %s more to qualify for free shipping'          => '再買 %s 即可享免運',
    'Free shipping promotions available — see checkout for details.'
        => '有免運優惠，詳情請見結帳頁。',
    'Quantity'                                          => '數量',
    '%d+'                                               => '%d 件以上',
    'Discount'                                          => '折扣',
];
