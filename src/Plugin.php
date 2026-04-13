<?php
declare(strict_types=1);

namespace PowerDiscount;

use PowerDiscount\Condition\CartSubtotalCondition;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\DateRangeCondition;
use PowerDiscount\Condition\Evaluator as ConditionEvaluator;
use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Engine\ExclusivityResolver;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;
use PowerDiscount\I18n\Loader as I18nLoader;
use PowerDiscount\Integration\CartContextBuilder;
use PowerDiscount\Integration\CartHooks;
use PowerDiscount\Integration\OrderDiscountLogger;
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
        $registry->register(new DateRangeCondition());

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
        $registry->register(new CategoriesFilter());

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
