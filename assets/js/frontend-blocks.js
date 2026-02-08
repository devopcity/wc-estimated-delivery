/**
 * WC Estimated Delivery Pro - Block Checkout/Cart Support
 * Vanilla JS (no jQuery dependency) for WooCommerce Blocks compatibility
 */

(function() {
    'use strict';

    // Only run on pages with WooCommerce Block Checkout or Block Cart
    function init() {
        var blockCheckout = document.querySelector('.wp-block-woocommerce-checkout');
        var blockCart = document.querySelector('.wp-block-woocommerce-cart');

        if (!blockCheckout && !blockCart) {
            return;
        }

        // Observe shipping method changes in Block Checkout
        if (blockCheckout) {
            observeBlockChanges(blockCheckout);
        }

        if (blockCart) {
            observeBlockChanges(blockCart);
        }
    }

    /**
     * Observe DOM changes in block checkout/cart for shipping method updates
     */
    function observeBlockChanges(container) {
        var debounceTimer = null;
        var lastShippingMethod = getSelectedShippingMethod(container);

        var observer = new MutationObserver(function() {
            var currentMethod = getSelectedShippingMethod(container);

            // Only refresh if shipping method actually changed
            if (currentMethod !== lastShippingMethod) {
                lastShippingMethod = currentMethod;

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    refreshDeliveryEstimate();
                }, 300);
            }
        });

        observer.observe(container, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['checked', 'value']
        });
    }

    /**
     * Get currently selected shipping method text
     */
    function getSelectedShippingMethod(container) {
        var checked = container.querySelector('input[name*="shipping"]:checked');
        return checked ? checked.value : '';
    }

    /**
     * Refresh delivery estimate via AJAX (fetch API)
     */
    function refreshDeliveryEstimate() {
        var estimate = document.getElementById('wced-delivery-estimate');
        if (!estimate) return;

        if (typeof wced_blocks_vars === 'undefined') return;

        estimate.classList.add('wced-updating');

        var body = new URLSearchParams();
        body.append('action', 'wced_get_delivery_date');
        body.append('nonce', wced_blocks_vars.nonce);

        fetch(wced_blocks_vars.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data) {
                var message = estimate.querySelector('.wced-message strong');
                if (message) {
                    var currentText = message.textContent;
                    var datePattern = /\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{2,4}/;
                    var dayPattern = /(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s*/i;
                    var wordDatePattern = /\d{1,2}\s+\w+\s+\d{4}/;

                    var newText = currentText
                        .replace(dayPattern, '')
                        .replace(datePattern, data.data.date)
                        .replace(wordDatePattern, data.data.date);

                    message.textContent = newText;
                }

                estimate.classList.remove('wced-updating');
                estimate.classList.add('wced-updated');

                setTimeout(function() {
                    estimate.classList.remove('wced-updated');
                }, 500);
            }
        })
        .catch(function() {
            estimate.classList.remove('wced-updating');
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
