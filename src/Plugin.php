<?php
declare(strict_types=1);

namespace PowerDiscount;

use PowerDiscount\Condition\BirthdayMonthCondition;
use PowerDiscount\Condition\CartLineItemsCondition;
use PowerDiscount\Condition\CartQuantityCondition;
use PowerDiscount\Condition\CartSubtotalCondition;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\DateRangeCondition;
use PowerDiscount\Condition\DayOfWeekCondition;
use PowerDiscount\Condition\Evaluator as ConditionEvaluator;
use PowerDiscount\Condition\FirstOrderCondition;
use PowerDiscount\Condition\PaymentMethodCondition;
use PowerDiscount\Condition\ShippingMethodCondition;
use PowerDiscount\Condition\TimeOfDayCondition;
use PowerDiscount\Condition\TotalSpentCondition;
use PowerDiscount\Condition\UserLoggedInCondition;
use PowerDiscount\Condition\UserRoleCondition;
use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Engine\ExclusivityResolver;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\AttributesFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;
use PowerDiscount\Filter\OnSaleFilter;
use PowerDiscount\Filter\ProductsFilter;
use PowerDiscount\Filter\TagsFilter;
use PowerDiscount\I18n\Loader as I18nLoader;
use PowerDiscount\Integration\CartContextBuilder;
use PowerDiscount\Integration\CartHooks;
use PowerDiscount\Integration\OrderDiscountLogger;
use PowerDiscount\Integration\ShippingHooks;
use PowerDiscount\Persistence\WpdbAdapter;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\RuleRepository;
use PowerDiscount\Strategy\BulkStrategy;
use PowerDiscount\Strategy\BuyXGetYStrategy;
use PowerDiscount\Strategy\CartStrategy;
use PowerDiscount\Strategy\CrossCategoryStrategy;
use PowerDiscount\Strategy\FreeShippingStrategy;
use PowerDiscount\Strategy\NthItemStrategy;
use PowerDiscount\Strategy\SetStrategy;
use PowerDiscount\Strategy\SimpleStrategy;
use PowerDiscount\Strategy\StrategyRegistry;

final class Plugin
{
    private static ?Plugin $instance = null;
    private bool $booted = false;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        (new I18nLoader())->register();

        if (!class_exists('WooCommerce')) {
            return;
        }

        $strategies = $this->buildStrategyRegistry();
        $conditions = $this->buildConditionRegistry();
        $filters = $this->buildFilterRegistry();

        /** @var \wpdb $wpdb */
        global $wpdb;
        $db = new WpdbAdapter($wpdb);
        $rulesRepo = new RuleRepository($db);
        $orderDiscountsRepo = new OrderDiscountRepository($db);

        $calculator = new Calculator(
            $strategies,
            new ConditionEvaluator($conditions),
            new Matcher($filters),
            new ExclusivityResolver()
        );
        $aggregator = new Aggregator();
        $builder = new CartContextBuilder();

        $cartHooks = new CartHooks($rulesRepo, $calculator, $aggregator, $builder);
        $cartHooks->register();
        (new OrderDiscountLogger($rulesRepo, $orderDiscountsRepo, $cartHooks))->register();
        (new ShippingHooks($rulesRepo, $calculator, $aggregator, $builder))->register();
    }

    private function buildStrategyRegistry(): StrategyRegistry
    {
        $registry = new StrategyRegistry();
        $registry->register(new SimpleStrategy());
        $registry->register(new BulkStrategy());
        $registry->register(new CartStrategy());
        $registry->register(new SetStrategy());
        $registry->register(new BuyXGetYStrategy());
        $registry->register(new NthItemStrategy());
        $registry->register(new CrossCategoryStrategy());
        $registry->register(new FreeShippingStrategy());

        $registry = apply_filters('power_discount_strategies', $registry);
        if (!$registry instanceof StrategyRegistry) {
            if (function_exists('error_log')) {
                error_log('Power Discount: power_discount_strategies filter returned non-registry type; falling back.');
            }
            return new StrategyRegistry();
        }
        return $registry;
    }

    private function buildConditionRegistry(): ConditionRegistry
    {
        $registry = new ConditionRegistry();
        $registry->register(new CartSubtotalCondition());
        $registry->register(new CartQuantityCondition());
        $registry->register(new CartLineItemsCondition());
        $registry->register(new DateRangeCondition());
        $registry->register(new DayOfWeekCondition());
        $registry->register(new TimeOfDayCondition());

        $registry->register(new UserRoleCondition(static function (): array {
            if (!function_exists('wp_get_current_user')) {
                return [];
            }
            $user = wp_get_current_user();
            return isset($user->roles) && is_array($user->roles) ? array_map('strval', $user->roles) : [];
        }));

        $registry->register(new UserLoggedInCondition(static function (): bool {
            return function_exists('is_user_logged_in') && is_user_logged_in();
        }));

        $registry->register(new PaymentMethodCondition(static function (): ?string {
            if (!function_exists('WC') || WC()->session === null) {
                return null;
            }
            $chosen = WC()->session->get('chosen_payment_method');
            return is_string($chosen) && $chosen !== '' ? $chosen : null;
        }));

        $registry->register(new ShippingMethodCondition(static function (): ?string {
            if (!function_exists('WC') || WC()->session === null) {
                return null;
            }
            $chosen = WC()->session->get('chosen_shipping_methods');
            if (!is_array($chosen) || $chosen === []) {
                return null;
            }
            $first = reset($chosen);
            return is_string($first) && $first !== '' ? $first : null;
        }));

        $currentUserId = static function (): int {
            return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        };

        $registry->register(new FirstOrderCondition(
            $currentUserId,
            static function (int $uid): int {
                if ($uid <= 0 || !function_exists('wc_get_customer_order_count')) {
                    return 0;
                }
                return (int) wc_get_customer_order_count($uid);
            }
        ));

        $registry->register(new TotalSpentCondition(
            $currentUserId,
            static function (int $uid): float {
                if ($uid <= 0 || !function_exists('wc_get_customer_total_spent')) {
                    return 0.0;
                }
                return (float) wc_get_customer_total_spent($uid);
            }
        ));

        $registry->register(new BirthdayMonthCondition(
            $currentUserId,
            static function (int $uid): ?int {
                if ($uid <= 0 || !function_exists('get_user_meta')) {
                    return null;
                }
                $raw = get_user_meta($uid, 'billing_birthday', true);
                if (!is_string($raw) || $raw === '') {
                    return null;
                }
                if (preg_match('/^(\d{4}-)?(\d{2})-\d{2}$/', $raw, $m)) {
                    $month = (int) $m[2];
                    return ($month >= 1 && $month <= 12) ? $month : null;
                }
                return null;
            },
            static function (): int { return time(); }
        ));

        $registry = apply_filters('power_discount_conditions', $registry);
        if (!$registry instanceof ConditionRegistry) {
            if (function_exists('error_log')) {
                error_log('Power Discount: power_discount_conditions filter returned non-registry type; falling back.');
            }
            return new ConditionRegistry();
        }
        return $registry;
    }

    private function buildFilterRegistry(): FilterRegistry
    {
        $registry = new FilterRegistry();
        $registry->register(new AllProductsFilter());
        $registry->register(new ProductsFilter());
        $registry->register(new CategoriesFilter());
        $registry->register(new TagsFilter());
        $registry->register(new AttributesFilter());
        $registry->register(new OnSaleFilter());

        $registry = apply_filters('power_discount_filters', $registry);
        if (!$registry instanceof FilterRegistry) {
            if (function_exists('error_log')) {
                error_log('Power Discount: power_discount_filters filter returned non-registry type; falling back.');
            }
            return new FilterRegistry();
        }
        return $registry;
    }
}
