<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use InvalidArgumentException;
use PowerDiscount\Domain\AddonRule;

final class AddonRuleFormMapper
{
    /**
     * Build a validated AddonRule from form POST data.
     *
     * @param array<string, mixed> $post
     */
    public static function fromFormData(array $post): AddonRule
    {
        return self::build($post, true);
    }

    /**
     * Build an AddonRule from POST data without validation.
     * Used to repopulate the form after a validation error so the
     * user's input is preserved.
     *
     * @param array<string, mixed> $post
     */
    public static function fromFormDataLoose(array $post): AddonRule
    {
        return self::build($post, false);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function build(array $post, bool $validate): AddonRule
    {
        $title = trim((string) ($post['title'] ?? ''));
        if ($validate && $title === '') {
            throw new InvalidArgumentException(__('請輸入規則名稱。', 'power-discount'));
        }

        $rawItems = (array) ($post['addon_items'] ?? []);
        $items = [];
        $seen = [];
        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $pid = (int) ($raw['product_id'] ?? 0);
            $price = (float) ($raw['special_price'] ?? 0);
            if ($pid <= 0) {
                continue; // silently drop blank rows
            }
            if ($validate && $price < 0) {
                throw new InvalidArgumentException(__('加價購商品的特價必須 ≥ 0。', 'power-discount'));
            }
            if ($validate && isset($seen[$pid])) {
                throw new InvalidArgumentException(__('同一個加價購商品不能在規則中重複列出。', 'power-discount'));
            }
            $items[] = ['product_id' => $pid, 'special_price' => $price];
            $seen[$pid] = true;
        }
        if ($validate && $items === []) {
            throw new InvalidArgumentException(__('至少需要指定一個加價購商品。', 'power-discount'));
        }

        $targets = array_values(array_unique(array_filter(
            array_map('intval', (array) ($post['target_product_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($validate && $targets === []) {
            throw new InvalidArgumentException(__('至少需要指定一個目標商品。', 'power-discount'));
        }

        return new AddonRule([
            'id'                     => (int) ($post['id'] ?? 0),
            'title'                  => $title,
            'status'                 => isset($post['status']) ? (int) $post['status'] : 1,
            'priority'               => isset($post['priority']) ? (int) $post['priority'] : 10,
            'addon_items'            => $items,
            'target_product_ids'     => $targets,
            'exclude_from_discounts' => !empty($post['exclude_from_discounts']),
        ]);
    }
}
