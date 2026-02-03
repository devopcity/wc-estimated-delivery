/**
 * WC Estimated Delivery Pro - Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color pickers
        $('.wced-color-picker').wpColorPicker({
            change: function(event, ui) {
                updatePreview();
            }
        });

        // Tab navigation via URL hash
        handleTabs();

        // Icon type toggle
        $('input[name="wced_options[icon_type]"]').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom-icon-row').show();
            } else {
                $('#custom-icon-row').hide();
            }
            updatePreview();
        });

        // Badge icon selector visual feedback and custom emoji toggle
        $('.wced-badge-icon-option input[type="radio"]').on('change', function() {
            var $wrap = $(this).closest('.wced-icon-select-wrap');
            var $td = $(this).closest('td');
            var badgeNum = $(this).attr('name').match(/badge_(\d+)_icon/)[1];

            // Update visual styles
            $wrap.find('.wced-badge-icon-option').css({
                'border-color': '#ddd',
                'background': '#fff'
            });
            $(this).closest('.wced-badge-icon-option').css({
                'border-color': '#2271b1',
                'background': '#f0f6fc'
            });

            // Show/hide custom emoji input
            if ($(this).val() === 'custom') {
                $('#custom-emoji-' + badgeNum).show();
            } else {
                $('#custom-emoji-' + badgeNum).hide();
            }
        });

        // Media uploader for custom icon
        $('#upload_icon_btn').on('click', function(e) {
            e.preventDefault();

            var mediaUploader = wp.media({
                title: 'Choose Icon',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#custom_icon_url').val(attachment.url);
                updatePreview();
            });

            mediaUploader.open();
        });

        // API Holidays Sync
        $('#wced-sync-holidays').on('click', function() {
            var $btn = $(this);
            var $status = $('#wced-sync-status');
            var country = $('#wced-country-select').val();

            if (!confirm(wced_admin.strings.confirm_sync)) {
                return;
            }

            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span>' + wced_admin.strings.syncing);

            $.ajax({
                url: wced_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wced_sync_holidays',
                    nonce: wced_admin.nonce,
                    country: country
                },
                success: function(response) {
                    if (response.success) {
                        $('#wced-holidays-textarea').val(response.data.holidays);
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' +
                            response.data.count + ' ' + wced_admin.strings.sync_success);

                        // Update last sync display
                        var $lastSync = $('.wced-last-sync');
                        if ($lastSync.length === 0) {
                            $btn.closest('td').append('<p class="description wced-last-sync"></p>');
                            $lastSync = $('.wced-last-sync');
                        }
                        $lastSync.html('Last sync: <strong>' + response.data.last_sync + '</strong>');
                    } else {
                        $status.html('<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' +
                            wced_admin.strings.sync_error + ': ' + response.data);
                    }
                },
                error: function() {
                    $status.html('<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' +
                        wced_admin.strings.sync_error);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    setTimeout(function() {
                        $status.fadeOut(function() {
                            $(this).html('').show();
                        });
                    }, 5000);
                }
            });
        });

        // Live preview updates
        $('input, select, textarea').on('change input', function() {
            updatePreview();
        });

        // Form validation
        $('form.wced-settings-form').on('submit', function() {
            var minDays = parseInt($('input[name="wced_options[min_days]"]').val());
            var maxDays = parseInt($('input[name="wced_options[max_days]"]').val());

            if (maxDays < minDays) {
                alert('Days after cutoff cannot be less than days before cutoff.');
                return false;
            }

            return true;
        });

        // Export settings
        $('#wced-export-settings').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(wced_admin.strings.exporting);

            $.ajax({
                url: wced_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wced_export_settings',
                    nonce: wced_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var dataStr = JSON.stringify(response.data, null, 2);
                        var blob = new Blob([dataStr], { type: 'application/json' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'wced-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    } else {
                        alert(wced_admin.strings.export_error + ': ' + response.data);
                    }
                },
                error: function() {
                    alert(wced_admin.strings.export_error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Export Settings');
                }
            });
        });

        // Import settings
        $('#wced-import-settings').on('click', function() {
            $('#wced-import-file').click();
        });

        $('#wced-import-file').on('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;

            if (!file.name.endsWith('.json')) {
                alert(wced_admin.strings.invalid_file);
                return;
            }

            if (!confirm(wced_admin.strings.confirm_import)) {
                $(this).val('');
                return;
            }

            var reader = new FileReader();
            var $btn = $('#wced-import-settings');
            var $status = $('#wced-import-status');

            reader.onload = function(e) {
                var content = e.target.result;

                $btn.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span>' + wced_admin.strings.importing);

                $.ajax({
                    url: wced_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wced_import_settings',
                        nonce: wced_admin.nonce,
                        import_data: content
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' + wced_admin.strings.import_success);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $status.html('<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' + response.data);
                        }
                    },
                    error: function() {
                        $status.html('<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' + wced_admin.strings.import_error);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $('#wced-import-file').val('');
                    }
                });
            };

            reader.readAsText(file);
        });

        // Clear debug log
        $('#wced-clear-log').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(wced_admin.strings.clearing_log);

            $.ajax({
                url: wced_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wced_clear_log',
                    nonce: wced_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                error: function() {
                    alert('Error clearing log');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Clear Log');
                }
            });
        });
    });

    /**
     * Handle tabs
     */
    function handleTabs() {
        var urlParams = new URLSearchParams(window.location.search);
        var activeTab = urlParams.get('tab') || 'general';

        $('.wced-tab-content').removeClass('active');
        $('#tab-' + activeTab).addClass('active');
    }

    /**
     * Update preview
     */
    function updatePreview() {
        var $preview = $('#wced-preview .wced-delivery-estimate');

        var bgColor = $('input[name="wced_options[bg_color]"]').val();
        var borderColor = $('input[name="wced_options[border_color]"]').val();
        var textColor = $('input[name="wced_options[text_color]"]').val();
        var borderRadius = $('input[name="wced_options[border_radius]"]').val();
        var padding = $('input[name="wced_options[padding]"]').val();

        $preview.css({
            'background-color': bgColor,
            'border-color': borderColor,
            'color': textColor,
            'border-radius': borderRadius + 'px',
            'padding': padding + 'px'
        });

        var showIcon = $('input[name="wced_options[show_icon]"]').is(':checked');
        $preview.find('.wced-icon').toggle(showIcon);

        var iconType = $('input[name="wced_options[icon_type]"]:checked').val();
        var iconHtml = '';

        switch (iconType) {
            case 'emoji':
                iconHtml = '📦';
                break;
            case 'truck':
                iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>';
                break;
            case 'box':
                iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
                break;
            case 'calendar':
                iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
                break;
            case 'custom':
                var customUrl = $('input[name="wced_options[custom_icon]"]').val();
                if (customUrl) {
                    iconHtml = '<img src="' + customUrl + '" style="width:20px;height:20px;" />';
                }
                break;
        }

        if (showIcon && iconHtml) {
            var $iconSpan = $preview.find('.wced-icon');
            if ($iconSpan.length) {
                $iconSpan.html(iconHtml);
            } else {
                $preview.prepend('<span class="wced-icon" style="font-size: 18px; margin-right: 8px;">' + iconHtml + '</span>');
            }
        }
    }

})(jQuery);
