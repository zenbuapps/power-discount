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

            if ($price <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = new CartItem($productId, $name, $price, $quantity, $categoryIds);
        }
        return new CartContext($items);
    }
}
