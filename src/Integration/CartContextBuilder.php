<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use WC_Cart;

final class CartContextBuilder
{
    public function fromWcCart(WC_Cart $cart): CartContext
    {
        $items = [];
        foreach ($cart->get_cart() as $cartItem) {
            // Skip addon items that the rule explicitly excluded from the discount engine.
            // Set by AddonCartHandler when the matching AddonRule has exclude_from_discounts = true.
            if (!empty($cartItem['_pd_addon_excluded_from_discounts'])) {
                continue;
            }
            $product = $cartItem['data'] ?? null;
            if ($product === null || !is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }

            $productId = (int) $product->get_id();
            $name = method_exists($product, 'get_name') ? (string) $product->get_name() : '';
            $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
            $quantity = (int) ($cartItem['quantity'] ?? 0);
            $categoryIds = [];

            $categorySource = $product;
            if (method_exists($product, 'get_parent_id') && (int) $product->get_parent_id() > 0) {
                $parent = wc_get_product((int) $product->get_parent_id());
                if ($parent) {
                    $categorySource = $parent;
                }
            }
            if (method_exists($categorySource, 'get_category_ids')) {
                $categoryIds = array_map('intval', (array) $categorySource->get_category_ids());
            }

            $tagIds = [];
            $attributes = [];
            $onSale = false;
            if (method_exists($categorySource, 'get_tag_ids')) {
                $tagIds = array_map('intval', (array) $categorySource->get_tag_ids());
            }
            // Attributes: read from the actual product (variation), NOT the parent.
            // Variations store the selected single value; parents store all configured options.
            if (method_exists($product, 'get_attributes')) {
                $attrsRaw = $product->get_attributes();
                if (is_array($attrsRaw)) {
                    foreach ($attrsRaw as $attrKey => $attrValue) {
                        if (is_object($attrValue) && method_exists($attrValue, 'get_options')) {
                            $attributes[(string) $attrKey] = array_map('strval', (array) $attrValue->get_options());
                        } elseif (is_string($attrValue)) {
                            $attributes[(string) $attrKey] = [$attrValue];
                        }
                    }
                }
            }
            if (method_exists($product, 'is_on_sale')) {
                $onSale = (bool) $product->is_on_sale();
            }

            if ($price <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = new CartItem($productId, $name, $price, $quantity, $categoryIds, $tagIds, $attributes, $onSale);
        }
        return new CartContext($items);
    }
}
