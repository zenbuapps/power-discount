<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use InvalidArgumentException;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;

final class RuleFormMapper
{
    private const VALID_TYPES = [
        'simple', 'bulk', 'cart', 'set',
        'buy_x_get_y', 'nth_item', 'cross_category', 'free_shipping',
        'gift_with_purchase',
    ];

    /**
     * Build a Rule from form POST data and fully validate it.
     *
     * @param array<string, mixed> $post
     */
    public static function fromFormData(array $post): Rule
    {
        return self::build($post, true);
    }

    /**
     * Build a Rule from form POST data without validation. Used to repopulate
     * the edit form after a validation error so the user doesn't lose their
     * input.
     *
     * @param array<string, mixed> $post
     */
    public static function fromFormDataLoose(array $post): Rule
    {
        return self::build($post, false);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function build(array $post, bool $validate): Rule
    {
        $title = trim((string) ($post['title'] ?? ''));
        if ($validate && $title === '') {
            throw new InvalidArgumentException(__('請輸入規則名稱。', 'power-discount'));
        }

        $type = (string) ($post['type'] ?? 'simple');
        if ($validate && !in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(__('折扣類型無效：%s', 'power-discount'), $type));
        }
        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'simple';
        }

        $configByType = (array) ($post['config_by_type'] ?? []);
        $config = isset($configByType[$type]) && is_array($configByType[$type])
            ? self::normaliseConfig($type, $configByType[$type])
            : [];
        if ($validate) {
            self::validateConfig($type, $config);
        }

        $filters = self::normaliseFilters((array) ($post['filters'] ?? []));
        $conditions = self::normaliseConditions((array) ($post['conditions'] ?? []));

        $startsAt = trim((string) ($post['starts_at'] ?? ''));
        $endsAt = trim((string) ($post['ends_at'] ?? ''));
        if ($validate && $startsAt !== '' && !self::isValidDateString($startsAt)) {
            throw new InvalidArgumentException(__('開始時間格式錯誤，請使用 YYYY-MM-DD HH:MM:SS。', 'power-discount'));
        }
        if ($validate && $endsAt !== '' && !self::isValidDateString($endsAt)) {
            throw new InvalidArgumentException(__('結束時間格式錯誤，請使用 YYYY-MM-DD HH:MM:SS。', 'power-discount'));
        }

        $usageLimitRaw = trim((string) ($post['usage_limit'] ?? ''));
        $usageLimit = $usageLimitRaw === '' ? null : (int) $usageLimitRaw;

        $scheduleMode = (string) ($post['schedule_mode'] ?? 'once');
        $scheduleMeta = [];
        if ($scheduleMode === 'monthly') {
            $dayFrom = isset($post['schedule_day_from']) ? (int) $post['schedule_day_from'] : 1;
            $dayTo = isset($post['schedule_day_to']) ? (int) $post['schedule_day_to'] : 31;
            if ($dayFrom < 1) $dayFrom = 1;
            if ($dayFrom > 31) $dayFrom = 31;
            if ($dayTo < 1) $dayTo = 1;
            if ($dayTo > 31) $dayTo = 31;
            $scheduleMeta = [
                'type'     => 'monthly',
                'day_from' => $dayFrom,
                'day_to'   => $dayTo,
            ];
        }

        return new Rule([
            'id'          => (int) ($post['id'] ?? 0),
            'title'       => $title,
            'type'        => $type,
            'status'      => isset($post['status']) ? (int) $post['status'] : RuleStatus::ENABLED,
            'priority'    => isset($post['priority']) ? (int) $post['priority'] : 10,
            'exclusive'   => !empty($post['exclusive']),
            'starts_at'   => $startsAt === '' ? null : $startsAt,
            'ends_at'     => $endsAt === '' ? null : $endsAt,
            'usage_limit' => $usageLimit,
            'used_count'  => 0,
            'filters'     => $filters,
            'conditions'  => $conditions,
            'config'      => $config,
            'label'       => isset($post['label']) && $post['label'] !== '' ? (string) $post['label'] : null,
            'notes'       => null,
            'schedule_meta' => $scheduleMeta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normaliseConfig(string $type, array $raw): array
    {
        switch ($type) {
            case 'simple':
                return [
                    'method' => (string) ($raw['method'] ?? ''),
                    'value'  => isset($raw['value']) ? (float) $raw['value'] : 0.0,
                ];
            case 'bulk':
                $ranges = [];
                foreach ((array) ($raw['ranges'] ?? []) as $r) {
                    if (!is_array($r)) continue;
                    $from = isset($r['from']) && $r['from'] !== '' ? (int) $r['from'] : 0;
                    $toRaw = $r['to'] ?? '';
                    $to = ($toRaw === '' || $toRaw === null) ? null : (int) $toRaw;
                    $ranges[] = [
                        'from'   => $from,
                        'to'     => $to,
                        'method' => (string) ($r['method'] ?? 'percentage'),
                        'value'  => isset($r['value']) ? (float) $r['value'] : 0.0,
                    ];
                }
                return [
                    'count_scope' => (string) ($raw['count_scope'] ?? 'cumulative'),
                    'ranges'      => $ranges,
                ];
            case 'cart':
                return [
                    'method' => (string) ($raw['method'] ?? ''),
                    'value'  => isset($raw['value']) ? (float) $raw['value'] : 0.0,
                ];
            case 'set':
                return [
                    'bundle_size' => isset($raw['bundle_size']) ? (int) $raw['bundle_size'] : 0,
                    'method'      => (string) ($raw['method'] ?? ''),
                    'value'       => isset($raw['value']) ? (float) $raw['value'] : 0.0,
                    'repeat'      => !empty($raw['repeat']),
                ];
            case 'buy_x_get_y':
                $trigger = (array) ($raw['trigger'] ?? []);
                $reward = (array) ($raw['reward'] ?? []);
                return [
                    'trigger' => [
                        'source'      => (string) ($trigger['source'] ?? 'filter'),
                        'qty'         => isset($trigger['qty']) ? (int) $trigger['qty'] : 0,
                        'product_ids' => array_map('intval', (array) ($trigger['product_ids'] ?? [])),
                    ],
                    'reward' => [
                        'target'      => (string) ($reward['target'] ?? 'same'),
                        'qty'         => isset($reward['qty']) ? (int) $reward['qty'] : 0,
                        'method'      => (string) ($reward['method'] ?? 'free'),
                        'value'       => isset($reward['value']) ? (float) $reward['value'] : 0.0,
                        'product_ids' => array_map('intval', (array) ($reward['product_ids'] ?? [])),
                    ],
                    'recursive' => !empty($raw['recursive']),
                ];
            case 'nth_item':
                $tiers = [];
                foreach ((array) ($raw['tiers'] ?? []) as $t) {
                    if (!is_array($t)) continue;
                    $tiers[] = [
                        'nth'    => isset($t['nth']) ? (int) $t['nth'] : 0,
                        'method' => (string) ($t['method'] ?? 'percentage'),
                        'value'  => isset($t['value']) ? (float) $t['value'] : 0.0,
                    ];
                }
                return [
                    'tiers'     => $tiers,
                    'sort_by'   => (string) ($raw['sort_by'] ?? 'price_desc'),
                    'recursive' => !empty($raw['recursive']),
                ];
            case 'cross_category':
                $groups = [];
                foreach ((array) ($raw['groups'] ?? []) as $g) {
                    if (!is_array($g)) continue;
                    $groups[] = [
                        'name'    => (string) ($g['name'] ?? ''),
                        'filter'  => [
                            'type'  => 'categories',
                            'value' => array_map('intval', (array) ($g['category_ids'] ?? [])),
                        ],
                        'min_qty' => isset($g['min_qty']) ? (int) $g['min_qty'] : 1,
                    ];
                }
                $reward = (array) ($raw['reward'] ?? []);
                return [
                    'groups' => $groups,
                    'reward' => [
                        'method' => (string) ($reward['method'] ?? 'percentage'),
                        'value'  => isset($reward['value']) ? (float) $reward['value'] : 0.0,
                    ],
                    'repeat' => !empty($raw['repeat']),
                ];
            case 'free_shipping':
                return [
                    'method'              => (string) ($raw['method'] ?? ''),
                    'value'               => isset($raw['value']) ? (float) $raw['value'] : 0.0,
                    'shipping_method_ids' => array_values(array_filter(
                        array_map('strval', (array) ($raw['shipping_method_ids'] ?? [])),
                        static function (string $id): bool { return $id !== ''; }
                    )),
                ];
            case 'gift_with_purchase':
                return [
                    'threshold'        => isset($raw['threshold']) ? (float) $raw['threshold'] : 0.0,
                    'gift_product_ids' => array_values(array_filter(
                        array_map('intval', (array) ($raw['gift_product_ids'] ?? [])),
                        static function (int $id): bool { return $id > 0; }
                    )),
                    'gift_qty'         => max(1, isset($raw['gift_qty']) ? (int) $raw['gift_qty'] : 1),
                ];
        }
        return [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function validateConfig(string $type, array $config): void
    {
        switch ($type) {
            case 'simple':
            case 'cart':
                if (!in_array($config['method'] ?? '', $type === 'simple'
                        ? ['percentage', 'flat', 'fixed_price']
                        : ['percentage', 'flat_total', 'flat_per_item'], true)) {
                    throw new InvalidArgumentException(__('請選擇折扣方式。', 'power-discount'));
                }
                if (($config['value'] ?? 0) <= 0) {
                    throw new InvalidArgumentException(__('折扣的數值必須大於 0。', 'power-discount'));
                }
                return;
            case 'bulk':
                if (empty($config['ranges'])) {
                    throw new InvalidArgumentException(__('數量階梯折扣至少需要一個級別。', 'power-discount'));
                }
                foreach ($config['ranges'] as $i => $r) {
                    if (($r['from'] ?? 0) < 1) {
                        throw new InvalidArgumentException(sprintf(
                            __('第 %d 個級別的起始數量必須 ≥ 1。', 'power-discount'),
                            $i + 1
                        ));
                    }
                    if (($r['value'] ?? 0) <= 0) {
                        throw new InvalidArgumentException(sprintf(
                            __('第 %d 個級別的折扣值必須 > 0。', 'power-discount'),
                            $i + 1
                        ));
                    }
                }
                return;
            case 'set':
                if (($config['bundle_size'] ?? 0) < 2) {
                    throw new InvalidArgumentException(__('任選 N 件的組合件數必須 ≥ 2。', 'power-discount'));
                }
                if (!in_array($config['method'] ?? '', ['set_price', 'set_percentage', 'set_flat_off'], true)) {
                    throw new InvalidArgumentException(__('請選擇任選 N 件的折扣方式。', 'power-discount'));
                }
                if (($config['value'] ?? -1) < 0) {
                    throw new InvalidArgumentException(__('任選 N 件的數值必須 ≥ 0。', 'power-discount'));
                }
                return;
            case 'buy_x_get_y':
                if (($config['trigger']['qty'] ?? 0) < 1) {
                    throw new InvalidArgumentException(__('買 X 送 Y 的觸發數量必須 ≥ 1。', 'power-discount'));
                }
                if (($config['reward']['qty'] ?? 0) < 1) {
                    throw new InvalidArgumentException(__('買 X 送 Y 的贈品數量必須 ≥ 1。', 'power-discount'));
                }
                return;
            case 'nth_item':
                if (empty($config['tiers'])) {
                    throw new InvalidArgumentException(__('第 N 件 X 折至少需要一個級別設定。', 'power-discount'));
                }
                return;
            case 'cross_category':
                if (count($config['groups'] ?? []) < 2) {
                    throw new InvalidArgumentException(__('紅配綠至少需要 2 個分類群組。', 'power-discount'));
                }
                return;
            case 'free_shipping':
                if (!in_array($config['method'] ?? '', ['remove_shipping', 'percentage_off_shipping', 'flat_off_shipping'], true)) {
                    throw new InvalidArgumentException(__('請選擇免運的方式。', 'power-discount'));
                }
                return;
            case 'gift_with_purchase':
                if (($config['threshold'] ?? 0) <= 0) {
                    throw new InvalidArgumentException(__('滿額贈的門檻金額必須 > 0。', 'power-discount'));
                }
                if (empty($config['gift_product_ids'])) {
                    throw new InvalidArgumentException(__('滿額贈至少需要指定一個贈品商品。', 'power-discount'));
                }
                return;
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normaliseFilters(array $raw): array
    {
        $items = [];
        foreach ((array) ($raw['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $type = (string) ($item['type'] ?? '');
            if ($type === '') continue;
            $normalised = ['type' => $type];
            switch ($type) {
                case 'all_products':
                case 'on_sale':
                    break;
                case 'products':
                case 'categories':
                case 'tags':
                    $normalised['method'] = (string) ($item['method'] ?? 'in');
                    $normalised['ids'] = array_values(array_filter(
                        array_map('intval', (array) ($item['ids'] ?? [])),
                        static fn (int $id): bool => $id > 0
                    ));
                    if ($type === 'categories' && !empty($item['include_subcategories'])) {
                        $normalised['include_subcategories'] = true;
                    }
                    break;
                case 'attributes':
                    $normalised['method'] = (string) ($item['method'] ?? 'in');
                    $normalised['attribute'] = (string) ($item['attribute'] ?? '');
                    $values = (array) ($item['values'] ?? []);
                    $normalised['values'] = array_values(array_filter(
                        array_map('strval', $values),
                        static fn (string $v): bool => $v !== ''
                    ));
                    break;
                default:
                    continue 2;
            }
            $items[] = $normalised;
        }
        return $items === [] ? [] : ['items' => $items];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normaliseConditions(array $raw): array
    {
        $items = [];
        foreach ((array) ($raw['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $type = (string) ($item['type'] ?? '');
            if ($type === '') continue;
            $normalised = ['type' => $type];
            switch ($type) {
                case 'cart_subtotal':
                case 'cart_quantity':
                case 'cart_line_items':
                case 'total_spent':
                    $normalised['operator'] = (string) ($item['operator'] ?? '>=');
                    $normalised['value'] = isset($item['value']) ? (float) $item['value'] : 0.0;
                    break;
                case 'user_role':
                    $normalised['roles'] = array_values(array_filter(
                        array_map('strval', (array) ($item['roles'] ?? [])),
                        static fn (string $r): bool => $r !== ''
                    ));
                    break;
                case 'user_logged_in':
                    $normalised['is_logged_in'] = !empty($item['is_logged_in']);
                    break;
                case 'payment_method':
                case 'shipping_method':
                    $normalised['methods'] = array_values(array_filter(
                        array_map('strval', (array) ($item['methods'] ?? [])),
                        static fn (string $m): bool => $m !== ''
                    ));
                    break;
                case 'date_range':
                    $normalised['from'] = (string) ($item['from'] ?? '');
                    $normalised['to'] = (string) ($item['to'] ?? '');
                    break;
                case 'day_of_week':
                    $normalised['days'] = array_values(array_filter(
                        array_map('intval', (array) ($item['days'] ?? [])),
                        static fn (int $d): bool => $d >= 1 && $d <= 7
                    ));
                    break;
                case 'time_of_day':
                    $normalised['from'] = (string) ($item['from'] ?? '');
                    $normalised['to'] = (string) ($item['to'] ?? '');
                    break;
                case 'first_order':
                    $normalised['is_first_order'] = !empty($item['is_first_order']);
                    break;
                case 'birthday_month':
                    $normalised['match_current_month'] = !empty($item['match_current_month']);
                    break;
                default:
                    continue 2;
            }
            $items[] = $normalised;
        }
        if ($items === []) {
            return [];
        }
        return [
            'logic' => (string) ($raw['logic'] ?? 'and') === 'or' ? 'or' : 'and',
            'items' => $items,
        ];
    }

    private static function isValidDateString(string $value): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt !== false && $dt->format('Y-m-d H:i:s') === $value;
    }
}
