(function ($) {
    'use strict';

    // --- Status toggle switch on rule list ---
    $(document).on('change', '.pd-toggle-status-input', function () {
        var $checkbox = $(this);
        if ($checkbox.prop('disabled')) {
            return;
        }
        var id = $checkbox.data('id');
        var nonce = $checkbox.data('nonce');
        var ajaxAction = $checkbox.data('ajax-action') || 'pd_toggle_rule_status';
        if (!id) {
            return;
        }
        $checkbox.prop('disabled', true);
        $.post(PowerDiscountAdmin.ajaxUrl, {
            action: ajaxAction,
            id: id,
            nonce: nonce
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            $checkbox.prop('disabled', false).prop('checked', !$checkbox.prop('checked'));
            var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Toggle failed';
            window.alert(msg);
        });
    });

    // --- Strategy type swap (show matching section, hide others) + description swap ---
    function updateStrategyDescription() {
        var $sel = $('#pd-type');
        if (!$sel.length) {
            return;
        }
        var $opt = $sel.find('option:selected');
        var desc = $opt.attr('data-description') || '';
        $('#pd-type-description').text(desc);
    }
    $(document).on('change', '#pd-type', function () {
        var selected = $(this).val();
        $('.pd-strategy-section').each(function () {
            $(this).toggle($(this).data('type') === selected);
        });
        updateStrategyDescription();
    });
    $(updateStrategyDescription);

    // --- Condition type field toggler ---
    $(document).on('change', '.pd-condition-type', function () {
        var type = $(this).val();
        var $row = $(this).closest('.pd-condition-row');
        $row.find('.pd-cond-fields').each(function () {
            var types = ($(this).data('for') || '').toString().split(',');
            $(this).toggle(types.indexOf(type) !== -1);
        });
    });

    // --- Filter type field toggler ---
    $(document).on('change', '.pd-filter-type', function () {
        var type = $(this).val();
        var $row = $(this).closest('.pd-filter-row');
        $row.find('.pd-filter-value').hide();
        $row.find('.pd-filter-value-' + type).show();
        // method is irrelevant for all_products / on_sale
        if (type === 'all_products' || type === 'on_sale') {
            $row.find('.pd-filter-method').hide();
        } else {
            $row.find('.pd-filter-method').show();
        }
    });

    // --- Generic repeater (add/remove rows) ---
    // Uses <template class="pd-repeater-template"> INSIDE the target container for row cloning,
    // OR an ad-hoc build function for special types.
    function nextIndex($container) {
        var max = -1;
        $container.find('.pd-repeater-row').each(function (idx) {
            max = Math.max(max, idx);
        });
        return max + 1;
    }

    function reindexNames($container) {
        $container.find('.pd-repeater-row').each(function (newIdx) {
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name');
                // Replace the first [N] occurrence with [newIdx]
                name = name.replace(/\[(\d+)\]/, '[' + newIdx + ']');
                $(this).attr('name', name);
            });
        });
    }

    function addFilterRow($container) {
        var idx = nextIndex($container);
        var t = (PowerDiscountAdmin && PowerDiscountAdmin.i18n) || {};
        var html = ''
            + '<div class="pd-repeater-row pd-filter-row">'
            + '<select name="filters[items][' + idx + '][type]" class="pd-filter-type">'
            + '<option value="all_products">' + (t.allProducts || 'All products') + '</option>'
            + '<option value="products">' + (t.specificProducts || 'Specific products') + '</option>'
            + '<option value="categories">' + (t.categories || 'Categories') + '</option>'
            + '<option value="tags">' + (t.tags || 'Tags') + '</option>'
            + '<option value="attributes">' + (t.attributes || 'Attributes') + '</option>'
            + '<option value="on_sale">' + (t.onSale || 'On sale') + '</option>'
            + '</select>'
            + '<select name="filters[items][' + idx + '][method]" class="pd-filter-method" style="display:none">'
            + '<option value="in">' + (t.inList || 'in list') + '</option>'
            + '<option value="not_in">' + (t.notInList || 'not in list') + '</option>'
            + '</select>'
            + '<span class="pd-filter-value pd-filter-value-products" style="display:none">'
            + '<select name="filters[items][' + idx + '][ids][]" class="wc-product-search" multiple data-placeholder="' + (t.searchProducts || 'Search products') + '" data-action="woocommerce_json_search_products_and_variations" style="min-width:300px;"></select>'
            + '</span>'
            + '<span class="pd-filter-value pd-filter-value-categories" style="display:none">'
            + '<select name="filters[items][' + idx + '][ids][]" class="pd-category-select" multiple data-placeholder="' + (t.selectCategories || 'Select categories') + '" style="min-width:300px;"></select>'
            + '</span>'
            + '<span class="pd-filter-value pd-filter-value-tags" style="display:none">'
            + '<select name="filters[items][' + idx + '][ids][]" class="pd-tag-select" multiple data-placeholder="' + (t.selectTags || 'Select tags') + '" style="min-width:300px;"></select>'
            + '</span>'
            + '<button type="button" class="button button-small pd-repeater-remove">×</button>'
            + '</div>';
        $container.append(html);
        initEnhancedSelects($container);
    }

    function addConditionRow($container) {
        var idx = nextIndex($container);
        var t = (PowerDiscountAdmin && PowerDiscountAdmin.i18n) || {};
        var dayLabels = [
            t.dayMon || 'Mon', t.dayTue || 'Tue', t.dayWed || 'Wed',
            t.dayThu || 'Thu', t.dayFri || 'Fri', t.daySat || 'Sat', t.daySun || 'Sun'
        ];
        var html = ''
            + '<div class="pd-repeater-row pd-condition-row">'
            + '<select name="conditions[items][' + idx + '][type]" class="pd-condition-type">'
            + '<option value="cart_subtotal">' + (t.cartSubtotal || 'Cart subtotal') + '</option>'
            + '<option value="cart_quantity">' + (t.cartQuantity || 'Cart total quantity') + '</option>'
            + '<option value="cart_line_items">' + (t.cartLineItems || 'Number of line items') + '</option>'
            + '<option value="total_spent">' + (t.totalSpent || 'Customer total spent (lifetime)') + '</option>'
            + '<option value="user_role">' + (t.userRole || 'User role') + '</option>'
            + '<option value="user_logged_in">' + (t.userLoggedIn || 'User logged in') + '</option>'
            + '<option value="payment_method">' + (t.paymentMethod || 'Payment method') + '</option>'
            + '<option value="shipping_method">' + (t.shippingMethod || 'Shipping method') + '</option>'
            + '<option value="date_range">' + (t.dateRange || 'Date range') + '</option>'
            + '<option value="day_of_week">' + (t.dayOfWeek || 'Day of week') + '</option>'
            + '<option value="time_of_day">' + (t.timeOfDay || 'Time of day') + '</option>'
            + '<option value="first_order">' + (t.firstOrder || 'First order') + '</option>'
            + '<option value="birthday_month">' + (t.birthdayMonth || 'Birthday month') + '</option>'
            + '</select>'
            + '<span class="pd-cond-fields" data-for="cart_subtotal,cart_quantity,cart_line_items,total_spent">'
            + '<select name="conditions[items][' + idx + '][operator]">'
            + '<option value=">=">&ge;</option><option value=">">&gt;</option><option value="=">=</option><option value="<=">&le;</option><option value="<">&lt;</option><option value="!=">&ne;</option>'
            + '</select>'
            + '<input type="number" step="0.01" name="conditions[items][' + idx + '][value]" class="small-text">'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="user_role" style="display:none">'
            + '<input type="text" name="conditions[items][' + idx + '][roles_csv]" class="regular-text" placeholder="customer, subscriber">'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="user_logged_in" style="display:none">'
            + '<label><input type="checkbox" name="conditions[items][' + idx + '][is_logged_in]" value="1"> ' + (t.requireLoggedIn || 'Require logged in') + '</label>'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="payment_method,shipping_method" style="display:none">'
            + '<input type="text" name="conditions[items][' + idx + '][methods_csv]" class="regular-text" placeholder="cod, bacs, stripe">'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="date_range" style="display:none">'
            + '<input type="text" name="conditions[items][' + idx + '][from]" placeholder="YYYY-MM-DD HH:MM:SS">&nbsp;→&nbsp;'
            + '<input type="text" name="conditions[items][' + idx + '][to]" placeholder="YYYY-MM-DD HH:MM:SS">'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="day_of_week" style="display:none">'
            + dayLabels.map(function(l, k){
                return '<label><input type="checkbox" name="conditions[items][' + idx + '][days][]" value="' + (k+1) + '"> ' + l + '</label>';
            }).join(' ')
            + '</span>'
            + '<span class="pd-cond-fields" data-for="time_of_day" style="display:none">'
            + '<input type="text" name="conditions[items][' + idx + '][from]" placeholder="HH:MM" style="width:70px;">&nbsp;→&nbsp;'
            + '<input type="text" name="conditions[items][' + idx + '][to]" placeholder="HH:MM" style="width:70px;">'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="first_order" style="display:none">'
            + '<label><input type="checkbox" name="conditions[items][' + idx + '][is_first_order]" value="1"> ' + (t.firstOrderOnly || 'First order only') + '</label>'
            + '</span>'
            + '<span class="pd-cond-fields" data-for="birthday_month" style="display:none">'
            + '<label><input type="checkbox" name="conditions[items][' + idx + '][match_current_month]" value="1"> ' + (t.matchCurrentMonth || 'Match current month') + '</label>'
            + '</span>'
            + '<button type="button" class="button button-small pd-repeater-remove">×</button>'
            + '</div>';
        $container.append(html);
    }

    function addTemplateRow($container) {
        var $tpl = $container.find('.pd-repeater-template').first();
        if (!$tpl.length) {
            return;
        }
        var idx = nextIndex($container);
        var html = $tpl.html().replace(/__INDEX__/g, idx);
        $container.append(html);
    }

    function addXCatGroupRow($container) {
        var idx = nextIndex($container);
        var t = (PowerDiscountAdmin && PowerDiscountAdmin.i18n) || {};
        var html = ''
            + '<div class="pd-repeater-row pd-group-row">'
            + '<button type="button" class="button button-small pd-repeater-remove pd-group-remove" title="' + (t.removeGroup || 'Remove group') + '">×</button>'
            + '<div class="pd-group-field">'
            + '<label>' + (t.groupName || 'Group name') + '</label>'
            + '<input type="text" name="config_by_type[cross_category][groups][' + idx + '][name]" class="regular-text" placeholder="' + (t.groupNameTops || 'e.g. Tops') + '">'
            + '</div>'
            + '<div class="pd-group-field">'
            + '<label>' + (t.categories || 'Categories') + '</label>'
            + '<select name="config_by_type[cross_category][groups][' + idx + '][category_ids][]" class="pd-category-select" multiple style="min-width:300px;" data-placeholder="' + (t.selectCategories || 'Select categories') + '"></select>'
            + '</div>'
            + '<div class="pd-group-field">'
            + '<label>' + (t.minQty || 'Min qty') + '</label>'
            + '<input type="number" name="config_by_type[cross_category][groups][' + idx + '][min_qty]" value="1" min="1" class="small-text">'
            + '</div>'
            + '</div>';
        $container.append(html);
        initEnhancedSelects($container);
    }

    $(document).on('click', '.pd-repeater-add', function () {
        var kind = $(this).data('pd-add');
        var $container = $(this).closest('.pd-section, td, div').find('.pd-repeater[data-pd-repeater="' + kind + '"]').first();
        if (!$container.length) {
            return;
        }
        if (kind === 'filter-row') {
            addFilterRow($container);
        } else if (kind === 'condition-row') {
            addConditionRow($container);
        } else if (kind === 'xcat-group') {
            addXCatGroupRow($container);
        } else {
            addTemplateRow($container);
        }
    });

    $(document).on('click', '.pd-repeater-remove', function () {
        var $row = $(this).closest('.pd-repeater-row');
        var $container = $row.closest('.pd-repeater');
        $row.remove();
        reindexNames($container);
    });

    // --- Enhanced selects (WC categories/tags/products) ---
    function initEnhancedSelects($scope) {
        if (typeof $.fn.selectWoo === 'undefined' && typeof $.fn.select2 === 'undefined') {
            return;
        }
        $scope = $scope || $(document);
        // Categories
        $scope.find('.pd-category-select:not(.enhanced)').each(function () {
            var $sel = $(this);
            $sel.addClass('enhanced');
            $sel.selectWoo({
                placeholder: $sel.data('placeholder') || 'Select',
                minimumInputLength: 0,
                ajax: {
                    url: PowerDiscountAdmin.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { action: 'pd_search_terms', taxonomy: 'product_cat', q: params.term, nonce: PowerDiscountAdmin.nonce };
                    },
                    processResults: function (data) {
                        return { results: data.data || [] };
                    }
                }
            });
        });
        // Tags
        $scope.find('.pd-tag-select:not(.enhanced)').each(function () {
            var $sel = $(this);
            $sel.addClass('enhanced');
            $sel.selectWoo({
                placeholder: $sel.data('placeholder') || 'Select',
                minimumInputLength: 0,
                ajax: {
                    url: PowerDiscountAdmin.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { action: 'pd_search_terms', taxonomy: 'product_tag', q: params.term, nonce: PowerDiscountAdmin.nonce };
                    },
                    processResults: function (data) {
                        return { results: data.data || [] };
                    }
                }
            });
        });
        // Products (use WC's own handler)
        if (typeof $.fn.selectWoo !== 'undefined') {
            $scope.find('.wc-product-search:not(.enhanced)').each(function () {
                $(this).addClass('enhanced');
                // Let WC's own init pick it up if available
                if (typeof wc_enhanced_select_params !== 'undefined' && $(this).data('action')) {
                    $(this).selectWoo({
                        minimumInputLength: 2,
                        ajax: {
                            url: PowerDiscountAdmin.ajaxUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return { action: $(this).data('action'), term: params.term, security: wc_enhanced_select_params.search_products_nonce };
                            }.bind(this),
                            processResults: function (data) {
                                var results = [];
                                $.each(data, function (id, text) { results.push({ id: id, text: text }); });
                                return { results: results };
                            }
                        }
                    });
                }
            });
        }
    }

    // --- Shipping method chip picker (free shipping strategy) ---
    $(document).on('change', '.pd-shipping-chip input[type="checkbox"]', function () {
        $(this).closest('.pd-shipping-chip').toggleClass('is-selected', this.checked);
    });

    // --- Schedule mode toggle (once vs monthly) ---
    $(document).on('change', '.pd-schedule-mode', function () {
        var mode = $(this).val();
        var $wrap = $(this).closest('td');
        $wrap.find('.pd-schedule-once').toggle(mode === 'once');
        $wrap.find('.pd-schedule-monthly').toggle(mode === 'monthly');
    });

    // --- Drag & drop reorder on rules list (generic) ---
    function initSortableTable(selector, reorderAction) {
        var $tbody = $(selector + ' .wp-list-table tbody');
        if (!$tbody.length || typeof $tbody.sortable !== 'function') {
            return;
        }
        $tbody.sortable({
            items: 'tr[data-id]',
            handle: '.pd-drag-handle',
            cursor: 'move',
            axis: 'y',
            helper: function (e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            placeholder: 'pd-sortable-placeholder',
            forcePlaceholderSize: true,
            update: function () {
                var ids = $tbody.find('tr[data-id]').map(function () {
                    return $(this).data('id');
                }).get();
                $tbody.css('opacity', 0.5);
                $.post(PowerDiscountAdmin.ajaxUrl, {
                    action: reorderAction,
                    nonce: PowerDiscountAdmin.nonce,
                    ids: ids
                }).done(function () {
                    window.location.reload();
                }).fail(function () {
                    $tbody.css('opacity', 1);
                    window.alert((PowerDiscountAdmin.i18n && PowerDiscountAdmin.i18n.reorderFailed) || 'Reorder failed');
                });
            }
        });
    }

    $(function () {
        initEnhancedSelects();
        // Main rules list uses .pd-rules-list; addon list has both classes
        // for shared CSS, so exclude it here to avoid double-binding.
        initSortableTable('.pd-rules-list:not(.pd-addons-list)', 'pd_reorder_rules');
        initSortableTable('.pd-addons-list', 'pd_reorder_addon_rules');
    });

})(jQuery);
