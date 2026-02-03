/**
 * WC Estimated Delivery Pro - Frontend Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Update delivery estimate when checkout is updated
        $(document.body).on('updated_checkout', function() {
            refreshDeliveryEstimate();
        });

        // Update when shipping method changes
        $(document.body).on('change', 'input[name^="shipping_method"]', function() {
            refreshDeliveryEstimate();
        });
    });

    /**
     * Refresh delivery estimate via AJAX
     */
    function refreshDeliveryEstimate() {
        var $estimate = $('#wced-delivery-estimate');

        if (!$estimate.length) {
            return;
        }

        $estimate.addClass('wced-updating');

        $.ajax({
            url: wced_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wced_get_delivery_date',
                nonce: wced_vars.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var $message = $estimate.find('.wced-message strong');
                    var currentText = $message.text();

                    // Replace date pattern (handles various formats)
                    var datePattern = /\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{2,4}/;
                    var dayPattern = /(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s*/i;

                    var newText = currentText.replace(dayPattern, '').replace(datePattern, response.data.date);
                    $message.text(newText);

                    $estimate.removeClass('wced-updating').addClass('wced-updated');

                    setTimeout(function() {
                        $estimate.removeClass('wced-updated');
                    }, 500);
                }
            },
            error: function() {
                $estimate.removeClass('wced-updating');
            }
        });
    }

})(jQuery);
