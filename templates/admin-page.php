<?php
/**
 * Admin settings page template
 */

if (!defined('ABSPATH')) exit;

$options = WC_Estimated_Delivery::get_instance()->get_options();
$valid_tabs = ['general', 'schedule', 'messages', 'style', 'badges', 'holidays', 'tools'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs, true)
    ? $_GET['tab'] : 'general';

// Preview delivery date
$preview_delivery = WC_Estimated_Delivery::get_instance()->calculate_delivery_date();
?>

<div class="wrap wced-admin-wrap">
    <!-- Header -->
    <div class="wced-header">
        <div class="wced-header-left">
            <h1>
                <span class="dashicons dashicons-car"></span>
                <?php esc_html_e('WC Estimated Delivery Pro', 'wc-estimated-delivery'); ?>
            </h1>
            <span class="wced-version-badge">v<?php echo esc_html(WCED_VERSION); ?></span>
        </div>
        <p class="wced-description">
            <?php esc_html_e('Configure estimated delivery date display on checkout, cart and product pages.', 'wc-estimated-delivery'); ?>
        </p>
    </div>

    <!-- Preview Box -->
    <div class="wced-preview-box">
        <h3><?php esc_html_e('Preview', 'wc-estimated-delivery'); ?></h3>
        <div class="wced-preview-content" id="wced-preview">
            <div class="wced-delivery-estimate" style="
                background-color: <?php echo esc_attr($options['bg_color']); ?>;
                border: 1px solid <?php echo esc_attr($options['border_color']); ?>;
                color: <?php echo esc_attr($options['text_color']); ?>;
                border-radius: <?php echo absint($options['border_radius']); ?>px;
                padding: <?php echo absint($options['padding']); ?>px;
            ">
                <?php if ($options['show_icon'] === 'yes'): ?>
                    <span class="wced-icon" style="font-size: 18px; margin-right: 8px;">📦</span>
                <?php endif; ?>
                <strong>
                    <?php
                    $msg = $options['message_template'];
                    echo esc_html(str_replace('{date}', $preview_delivery['formatted_date'], $msg));
                    ?>
                </strong>
            </div>
            <p class="wced-preview-info">
                <?php if ($preview_delivery['is_before_cutoff']): ?>
                    <span class="wced-badge wced-badge-success"><?php esc_html_e('Before cutoff', 'wc-estimated-delivery'); ?></span>
                <?php else: ?>
                    <span class="wced-badge wced-badge-warning"><?php esc_html_e('After cutoff', 'wc-estimated-delivery'); ?></span>
                <?php endif; ?>
                <span class="wced-time">
                    <?php printf(esc_html__('Current time: %s', 'wc-estimated-delivery'), wp_date('H:i')); ?>
                </span>
            </p>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wced-tabs">
        <a href="?page=wc-estimated-delivery&tab=general"
           class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('General', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=schedule"
           class="nav-tab <?php echo $active_tab === 'schedule' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-clock"></span>
            <?php esc_html_e('Schedule & Days', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=messages"
           class="nav-tab <?php echo $active_tab === 'messages' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-format-chat"></span>
            <?php esc_html_e('Messages', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=style"
           class="nav-tab <?php echo $active_tab === 'style' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-appearance"></span>
            <?php esc_html_e('Style & Design', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=badges"
           class="nav-tab <?php echo $active_tab === 'badges' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-awards"></span>
            <?php esc_html_e('Trust Badges', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=holidays"
           class="nav-tab <?php echo $active_tab === 'holidays' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php esc_html_e('Holidays', 'wc-estimated-delivery'); ?>
        </a>
        <a href="?page=wc-estimated-delivery&tab=tools"
           class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php esc_html_e('Tools', 'wc-estimated-delivery'); ?>
        </a>
    </nav>

    <form method="post" action="options.php" class="wced-settings-form">
        <?php settings_fields('wced_options_group'); ?>

        <!-- General Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="tab-general">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable plugin', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label class="wced-switch">
                            <input type="checkbox" name="wced_options[enabled]" value="yes"
                                <?php checked($options['enabled'], 'yes'); ?> />
                            <span class="wced-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Enable or disable delivery date display.', 'wc-estimated-delivery'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Checkout position', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <select name="wced_options[position]" class="regular-text">
                            <option value="before_payment" <?php selected($options['position'], 'before_payment'); ?>>
                                <?php esc_html_e('Before payment methods', 'wc-estimated-delivery'); ?>
                            </option>
                            <option value="after_payment" <?php selected($options['position'], 'after_payment'); ?>>
                                <?php esc_html_e('After payment methods', 'wc-estimated-delivery'); ?>
                            </option>
                            <option value="before_order_review" <?php selected($options['position'], 'before_order_review'); ?>>
                                <?php esc_html_e('Before order review', 'wc-estimated-delivery'); ?>
                            </option>
                            <option value="after_order_review" <?php selected($options['position'], 'after_order_review'); ?>>
                                <?php esc_html_e('After order review', 'wc-estimated-delivery'); ?>
                            </option>
                            <option value="before_customer_details" <?php selected($options['position'], 'before_customer_details'); ?>>
                                <?php esc_html_e('Before customer details', 'wc-estimated-delivery'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Display on other pages', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wced_options[show_on_product]" value="yes"
                                <?php checked($options['show_on_product'], 'yes'); ?> />
                            <?php esc_html_e('Product page', 'wc-estimated-delivery'); ?>
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" name="wced_options[show_on_cart]" value="yes"
                                <?php checked($options['show_on_cart'], 'yes'); ?> />
                            <?php esc_html_e('Cart page', 'wc-estimated-delivery'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Date format', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <select name="wced_options[date_format]">
                            <option value="j F Y" <?php selected($options['date_format'], 'j F Y'); ?>>
                                <?php echo wp_date('j F Y'); ?> (j F Y)
                            </option>
                            <option value="F j, Y" <?php selected($options['date_format'], 'F j, Y'); ?>>
                                <?php echo wp_date('F j, Y'); ?> (F j, Y)
                            </option>
                            <option value="M j, Y" <?php selected($options['date_format'], 'M j, Y'); ?>>
                                <?php echo wp_date('M j, Y'); ?> (M j, Y)
                            </option>
                            <option value="d F Y" <?php selected($options['date_format'], 'd F Y'); ?>>
                                <?php echo wp_date('d F Y'); ?> (d F Y)
                            </option>
                            <option value="m/d/Y" <?php selected($options['date_format'], 'm/d/Y'); ?>>
                                <?php echo wp_date('m/d/Y'); ?> (m/d/Y)
                            </option>
                            <option value="d/m/Y" <?php selected($options['date_format'], 'd/m/Y'); ?>>
                                <?php echo wp_date('d/m/Y'); ?> (d/m/Y)
                            </option>
                            <option value="d.m.Y" <?php selected($options['date_format'], 'd.m.Y'); ?>>
                                <?php echo wp_date('d.m.Y'); ?> (d.m.Y)
                            </option>
                            <option value="Y-m-d" <?php selected($options['date_format'], 'Y-m-d'); ?>>
                                <?php echo wp_date('Y-m-d'); ?> (Y-m-d)
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Date will be displayed in your site\'s language.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Show day name', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wced_options[show_day_name]" value="yes"
                                <?php checked($options['show_day_name'], 'yes'); ?> />
                            <?php esc_html_e('E.g.: "Monday, Feb 3, 2025"', 'wc-estimated-delivery'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Schedule Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>" id="tab-schedule">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Cutoff time', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="number" name="wced_options[cutoff_hour]" value="<?php echo esc_attr($options['cutoff_hour']); ?>"
                               min="0" max="23" class="small-text" />
                        :
                        <input type="number" name="wced_options[cutoff_minute]" value="<?php echo esc_attr($options['cutoff_minute']); ?>"
                               min="0" max="59" class="small-text" />
                        <p class="description">
                            <?php esc_html_e('Orders placed before this time will be delivered sooner.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Delivery days (before cutoff)', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="number" name="wced_options[min_days]" value="<?php echo esc_attr($options['min_days']); ?>"
                               min="1" max="30" class="small-text" />
                        <span><?php esc_html_e('business days', 'wc-estimated-delivery'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Number of business days for orders placed before cutoff time.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Delivery days (after cutoff)', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="number" name="wced_options[max_days]" value="<?php echo esc_attr($options['max_days']); ?>"
                               min="1" max="30" class="small-text" />
                        <span><?php esc_html_e('business days', 'wc-estimated-delivery'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Number of business days for orders placed after cutoff time.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Weekend delivery', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" name="wced_options[work_saturday]" value="yes"
                                <?php checked($options['work_saturday'] ?? 'no', 'yes'); ?> />
                            <?php esc_html_e('Saturday is a delivery day', 'wc-estimated-delivery'); ?>
                        </label>
                        <label style="display: block;">
                            <input type="checkbox" name="wced_options[work_sunday]" value="yes"
                                <?php checked($options['work_sunday'] ?? 'no', 'yes'); ?> />
                            <?php esc_html_e('Sunday is a delivery day', 'wc-estimated-delivery'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('By default, weekends are excluded from delivery calculations.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

            </table>
        </div>

        <!-- Messages Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'messages' ? 'active' : ''; ?>" id="tab-messages">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Main message', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="text" name="wced_options[message_template]"
                               value="<?php echo esc_attr($options['message_template']); ?>"
                               class="large-text" />
                        <p class="description">
                            <?php esc_html_e('Use {date} to display the delivery date. E.g.: "Estimated delivery: {date}"', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Message (before cutoff)', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="text" name="wced_options[message_before_cutoff]"
                               value="<?php echo esc_attr($options['message_before_cutoff']); ?>"
                               class="large-text"
                               placeholder="<?php esc_attr_e('Leave empty to use main message', 'wc-estimated-delivery'); ?>" />
                        <p class="description">
                            <?php esc_html_e('Custom message for orders placed before cutoff time.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Message (after cutoff)', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="text" name="wced_options[message_after_cutoff]"
                               value="<?php echo esc_attr($options['message_after_cutoff']); ?>"
                               class="large-text"
                               placeholder="<?php esc_attr_e('Leave empty to use main message', 'wc-estimated-delivery'); ?>" />
                        <p class="description">
                            <?php esc_html_e('Custom message for orders placed after cutoff time.', 'wc-estimated-delivery'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Style Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'style' ? 'active' : ''; ?>" id="tab-style">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Show icon', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label class="wced-switch">
                            <input type="checkbox" name="wced_options[show_icon]" value="yes"
                                <?php checked($options['show_icon'], 'yes'); ?> />
                            <span class="wced-slider"></span>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Icon type', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <div class="wced-icon-options">
                            <label class="wced-icon-option">
                                <input type="radio" name="wced_options[icon_type]" value="emoji"
                                    <?php checked($options['icon_type'], 'emoji'); ?> />
                                <span class="wced-icon-preview">📦</span>
                                <span class="wced-icon-label"><?php esc_html_e('Emoji', 'wc-estimated-delivery'); ?></span>
                            </label>

                            <label class="wced-icon-option">
                                <input type="radio" name="wced_options[icon_type]" value="truck"
                                    <?php checked($options['icon_type'], 'truck'); ?> />
                                <span class="wced-icon-preview">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                </span>
                                <span class="wced-icon-label"><?php esc_html_e('Truck', 'wc-estimated-delivery'); ?></span>
                            </label>

                            <label class="wced-icon-option">
                                <input type="radio" name="wced_options[icon_type]" value="box"
                                    <?php checked($options['icon_type'], 'box'); ?> />
                                <span class="wced-icon-preview">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                </span>
                                <span class="wced-icon-label"><?php esc_html_e('Box', 'wc-estimated-delivery'); ?></span>
                            </label>

                            <label class="wced-icon-option">
                                <input type="radio" name="wced_options[icon_type]" value="calendar"
                                    <?php checked($options['icon_type'], 'calendar'); ?> />
                                <span class="wced-icon-preview">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                                <span class="wced-icon-label"><?php esc_html_e('Calendar', 'wc-estimated-delivery'); ?></span>
                            </label>

                            <label class="wced-icon-option">
                                <input type="radio" name="wced_options[icon_type]" value="custom"
                                    <?php checked($options['icon_type'], 'custom'); ?> />
                                <span class="wced-icon-preview">🖼️</span>
                                <span class="wced-icon-label"><?php esc_html_e('Custom', 'wc-estimated-delivery'); ?></span>
                            </label>
                        </div>
                    </td>
                </tr>

                <tr id="custom-icon-row" style="<?php echo $options['icon_type'] !== 'custom' ? 'display:none;' : ''; ?>">
                    <th scope="row"><?php esc_html_e('Custom icon', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <input type="text" name="wced_options[custom_icon]" id="custom_icon_url"
                               value="<?php echo esc_url($options['custom_icon']); ?>"
                               class="regular-text" />
                        <button type="button" class="button wced-upload-btn" id="upload_icon_btn">
                            <?php esc_html_e('Choose image', 'wc-estimated-delivery'); ?>
                        </button>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Colors', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <div class="wced-color-grid">
                            <div class="wced-color-item">
                                <label><?php esc_html_e('Background', 'wc-estimated-delivery'); ?></label>
                                <input type="text" name="wced_options[bg_color]"
                                       value="<?php echo esc_attr($options['bg_color']); ?>"
                                       class="wced-color-picker" data-default-color="#f8f9fa" />
                            </div>
                            <div class="wced-color-item">
                                <label><?php esc_html_e('Border', 'wc-estimated-delivery'); ?></label>
                                <input type="text" name="wced_options[border_color]"
                                       value="<?php echo esc_attr($options['border_color']); ?>"
                                       class="wced-color-picker" data-default-color="#e5e5e5" />
                            </div>
                            <div class="wced-color-item">
                                <label><?php esc_html_e('Text', 'wc-estimated-delivery'); ?></label>
                                <input type="text" name="wced_options[text_color]"
                                       value="<?php echo esc_attr($options['text_color']); ?>"
                                       class="wced-color-picker" data-default-color="#333333" />
                            </div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Dimensions', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <div class="wced-dimension-row">
                            <label>
                                <?php esc_html_e('Border radius', 'wc-estimated-delivery'); ?>
                                <input type="number" name="wced_options[border_radius]"
                                       value="<?php echo absint($options['border_radius']); ?>"
                                       min="0" max="50" class="small-text" /> px
                            </label>
                            <label>
                                <?php esc_html_e('Padding', 'wc-estimated-delivery'); ?>
                                <input type="number" name="wced_options[padding]"
                                       value="<?php echo absint($options['padding']); ?>"
                                       min="0" max="50" class="small-text" /> px
                            </label>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Holidays Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'holidays' ? 'active' : ''; ?>" id="tab-holidays">
            <?php
            $countries = WC_Estimated_Delivery::get_instance()->get_available_countries();
            $selected_country = $options['holidays_country'] ?? 'US';
            $last_sync = $options['holidays_last_sync'] ?? '';
            ?>

            <!-- API Sync Section -->
            <div class="wced-card wced-card-blue">
                <h3>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Automatic Holiday Sync', 'wc-estimated-delivery'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('Automatically fetch public holidays from the Nager.Date API for the current and next year.', 'wc-estimated-delivery'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Country', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <select name="wced_options[holidays_country]" id="wced-country-select" class="regular-text">
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($selected_country, $code); ?>>
                                        <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-sync', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <label class="wced-switch">
                                <input type="checkbox" name="wced_options[holidays_auto_sync]" value="yes"
                                    <?php checked($options['holidays_auto_sync'] ?? 'yes', 'yes'); ?> />
                                <span class="wced-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically sync holidays daily via cron job.', 'wc-estimated-delivery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Manual sync', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <div class="wced-button-row">
                                <button type="button" class="button button-primary" id="wced-sync-holidays">
                                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                                    <?php esc_html_e('Sync Now', 'wc-estimated-delivery'); ?>
                                </button>
                                <span id="wced-sync-status" class="wced-sync-status"></span>
                            </div>

                            <?php if (!empty($last_sync)): ?>
                                <p class="description wced-last-sync">
                                    <?php
                                    printf(
                                        esc_html__('Last sync: %s', 'wc-estimated-delivery'),
                                        '<strong>' . esc_html(wp_date('M j, Y H:i', strtotime($last_sync))) . '</strong>'
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>

                            <p class="description" style="margin-top: 8px;">
                                <?php
                                printf(
                                    esc_html__('Using %s (free, no API key required).', 'wc-estimated-delivery'),
                                    '<a href="https://date.nager.at/" target="_blank" rel="noopener">Nager.Date API</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Manual Holidays -->
            <div class="wced-card">
                <h3>
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Holiday List', 'wc-estimated-delivery'); ?>
                </h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Non-delivery days', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <textarea name="wced_options[holidays]" id="wced-holidays-textarea" rows="12" class="large-text code"
                                      placeholder="12/25/2025&#10;12/26/2025&#10;01/01/2026"><?php echo esc_textarea($options['holidays']); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('One date per line. Formats: 12/25/2025, 25.12.2025, 2025-12-25', 'wc-estimated-delivery'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Badges Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'badges' ? 'active' : ''; ?>" id="tab-badges">
            <?php
            $badge_icons = [
                'truck' => __('Truck', 'wc-estimated-delivery'),
                'trophy' => __('Trophy', 'wc-estimated-delivery'),
                'flag' => __('Flag', 'wc-estimated-delivery'),
                'star' => __('Star', 'wc-estimated-delivery'),
                'heart' => __('Heart', 'wc-estimated-delivery'),
                'shield' => __('Shield', 'wc-estimated-delivery'),
                'check' => __('Check', 'wc-estimated-delivery'),
                'gift' => __('Gift', 'wc-estimated-delivery'),
                'leaf' => __('Leaf', 'wc-estimated-delivery'),
                'clock' => __('Clock', 'wc-estimated-delivery'),
                'custom' => __('Custom', 'wc-estimated-delivery'),
            ];
            $text_styles = [
                'normal' => __('Normal', 'wc-estimated-delivery'),
                'bold' => __('Bold', 'wc-estimated-delivery'),
                'italic' => __('Italic', 'wc-estimated-delivery'),
                'bold-italic' => __('Bold + Italic', 'wc-estimated-delivery'),
            ];
            ?>

            <p class="description" style="margin-bottom: 20px;">
                <?php esc_html_e('Display trust badges above the delivery estimate on product pages. These help build customer confidence.', 'wc-estimated-delivery'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable trust badges', 'wc-estimated-delivery'); ?></th>
                    <td>
                        <label class="wced-switch">
                            <input type="checkbox" name="wced_options[badges_enabled]" value="yes"
                                <?php checked($options['badges_enabled'] ?? 'no', 'yes'); ?> />
                            <span class="wced-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Show trust badges on product pages (requires "Product page" display to be enabled).', 'wc-estimated-delivery'); ?></p>
                    </td>
                </tr>
            </table>

            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="wced-card wced-badge-card">
                    <h3>
                        <?php
                        if ($i === 4) {
                            esc_html_e('Badge 4 - Product Rating', 'wc-estimated-delivery');
                        } else {
                            printf(esc_html__('Badge %d', 'wc-estimated-delivery'), $i);
                        }
                        ?>
                    </h3>

                    <?php if ($i === 4): ?>
                        <p class="wced-notice wced-notice-info">
                            <?php esc_html_e('This badge displays the actual product rating from reviews. Use {rating} in the text to place the rating value, or it will be prepended automatically. Only shows on products with reviews.', 'wc-estimated-delivery'); ?>
                        </p>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled', 'wc-estimated-delivery'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wced_options[badge_<?php echo $i; ?>_enabled]" value="yes"
                                        <?php checked($options["badge_{$i}_enabled"] ?? ($i === 4 ? 'no' : 'yes'), 'yes'); ?> />
                                    <?php esc_html_e('Show this badge', 'wc-estimated-delivery'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Icon', 'wc-estimated-delivery'); ?></th>
                            <td>
                                <div class="wced-icon-select-wrap" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php
                                    $default_icons = ['truck', 'trophy', 'flag', 'star'];
                                    $current_icon = $options["badge_{$i}_icon"] ?? $default_icons[$i - 1];
                                    foreach ($badge_icons as $icon_key => $icon_label):
                                        if ($icon_key === 'custom') continue;
                                    ?>
                                        <label class="wced-badge-icon-option" style="
                                            display: flex;
                                            flex-direction: column;
                                            align-items: center;
                                            padding: 10px 12px;
                                            border: 2px solid <?php echo $current_icon === $icon_key ? '#2271b1' : '#ddd'; ?>;
                                            border-radius: 6px;
                                            cursor: pointer;
                                            background: <?php echo $current_icon === $icon_key ? '#f0f6fc' : '#fff'; ?>;
                                            min-width: 60px;
                                        ">
                                            <input type="radio" name="wced_options[badge_<?php echo $i; ?>_icon]"
                                                   value="<?php echo esc_attr($icon_key); ?>"
                                                   <?php checked($current_icon, $icon_key); ?>
                                                   style="display: none;" />
                                            <span style="margin-bottom: 4px;">
                                                <?php echo wced_get_icon_svg_admin($icon_key); ?>
                                            </span>
                                            <span style="font-size: 10px; color: #666;"><?php echo esc_html($icon_label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <!-- Custom emoji option -->
                                    <label class="wced-badge-icon-option" style="
                                        display: flex;
                                        flex-direction: column;
                                        align-items: center;
                                        padding: 10px 12px;
                                        border: 2px solid <?php echo $current_icon === 'custom' ? '#2271b1' : '#ddd'; ?>;
                                        border-radius: 6px;
                                        cursor: pointer;
                                        background: <?php echo $current_icon === 'custom' ? '#f0f6fc' : '#fff'; ?>;
                                        min-width: 60px;
                                    ">
                                        <input type="radio" name="wced_options[badge_<?php echo $i; ?>_icon]"
                                               value="custom"
                                               <?php checked($current_icon, 'custom'); ?>
                                               style="display: none;"
                                               class="wced-custom-icon-radio" />
                                        <span style="margin-bottom: 4px; font-size: 20px;">✏️</span>
                                        <span style="font-size: 10px; color: #666;"><?php esc_html_e('Custom', 'wc-estimated-delivery'); ?></span>
                                    </label>
                                </div>
                                <!-- Custom emoji input -->
                                <div class="wced-custom-emoji-wrap" id="custom-emoji-<?php echo $i; ?>" style="margin-top: 10px; <?php echo $current_icon !== 'custom' ? 'display: none;' : ''; ?>">
                                    <input type="text" name="wced_options[badge_<?php echo $i; ?>_custom_icon]"
                                           value="<?php echo esc_attr($options["badge_{$i}_custom_icon"] ?? ''); ?>"
                                           class="small-text"
                                           style="font-size: 24px; width: 60px; text-align: center;"
                                           placeholder="🎉" />
                                    <span class="description" style="margin-left: 10px;"><?php esc_html_e('Enter any emoji', 'wc-estimated-delivery'); ?></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Text', 'wc-estimated-delivery'); ?></th>
                            <td>
                                <?php
                                $default_texts = [
                                    'Free shipping over 350 lei',
                                    'Internationally awarded wines',
                                    'Produced in Romania',
                                    'Customer rating'
                                ];
                                ?>
                                <input type="text" name="wced_options[badge_<?php echo $i; ?>_text]"
                                       value="<?php echo esc_attr($options["badge_{$i}_text"] ?? $default_texts[$i - 1]); ?>"
                                       class="regular-text" />
                                <?php if ($i === 4): ?>
                                    <p class="description"><?php esc_html_e('Use {rating} placeholder to position the rating value.', 'wc-estimated-delivery'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Text style', 'wc-estimated-delivery'); ?></th>
                            <td>
                                <select name="wced_options[badge_<?php echo $i; ?>_style]">
                                    <?php foreach ($text_styles as $style_key => $style_label): ?>
                                        <option value="<?php echo esc_attr($style_key); ?>"
                                            <?php selected($options["badge_{$i}_style"] ?? 'normal', $style_key); ?>>
                                            <?php echo esc_html($style_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endfor; ?>

            <div class="wced-card">
                <h3><?php esc_html_e('Badge Style', 'wc-estimated-delivery'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Colors', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <div class="wced-color-grid">
                                <div class="wced-color-item">
                                    <label><?php esc_html_e('Background', 'wc-estimated-delivery'); ?></label>
                                    <input type="text" name="wced_options[badges_bg_color]"
                                           value="<?php echo esc_attr($options['badges_bg_color'] ?? '#ffffff'); ?>"
                                           class="wced-color-picker" data-default-color="#ffffff" />
                                </div>
                                <div class="wced-color-item">
                                    <label><?php esc_html_e('Border', 'wc-estimated-delivery'); ?></label>
                                    <input type="text" name="wced_options[badges_border_color]"
                                           value="<?php echo esc_attr($options['badges_border_color'] ?? '#e5e5e5'); ?>"
                                           class="wced-color-picker" data-default-color="#e5e5e5" />
                                </div>
                                <div class="wced-color-item">
                                    <label><?php esc_html_e('Text', 'wc-estimated-delivery'); ?></label>
                                    <input type="text" name="wced_options[badges_text_color]"
                                           value="<?php echo esc_attr($options['badges_text_color'] ?? '#333333'); ?>"
                                           class="wced-color-picker" data-default-color="#333333" />
                                </div>
                                <div class="wced-color-item">
                                    <label><?php esc_html_e('Icon', 'wc-estimated-delivery'); ?></label>
                                    <input type="text" name="wced_options[badges_icon_color]"
                                           value="<?php echo esc_attr($options['badges_icon_color'] ?? '#333333'); ?>"
                                           class="wced-color-picker" data-default-color="#333333" />
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Tools Tab -->
        <div class="wced-tab-content <?php echo $active_tab === 'tools' ? 'active' : ''; ?>" id="tab-tools">

            <!-- Import/Export Section - buttons on same row -->
            <div class="wced-card">
                <h3>
                    <span class="dashicons dashicons-migrate"></span>
                    <?php esc_html_e('Import / Export', 'wc-estimated-delivery'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('Backup or restore your plugin settings as a JSON file.', 'wc-estimated-delivery'); ?>
                </p>
                <div class="wced-button-row" style="margin-top: 15px;">
                    <button type="button" class="button button-primary" id="wced-export-settings">
                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Export Settings', 'wc-estimated-delivery'); ?>
                    </button>
                    <input type="file" id="wced-import-file" accept=".json" style="display: none;" />
                    <button type="button" class="button" id="wced-import-settings">
                        <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Import Settings', 'wc-estimated-delivery'); ?>
                    </button>
                    <span id="wced-import-status"></span>
                </div>
            </div>

            <!-- Debug Mode Section -->
            <div class="wced-card">
                <h3>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('Debug Mode', 'wc-estimated-delivery'); ?>
                </h3>
                <table class="form-table" style="margin-top: 0;">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable debug mode', 'wc-estimated-delivery'); ?></th>
                        <td>
                            <label class="wced-switch">
                                <input type="checkbox" name="wced_options[debug_mode]" value="yes"
                                    <?php checked($options['debug_mode'] ?? 'no', 'yes'); ?> />
                                <span class="wced-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Enable logging for troubleshooting. Logs are stored in the database.', 'wc-estimated-delivery'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php
                $debug_log = WC_Estimated_Delivery::get_instance()->get_log();
                ?>

                <?php if (!empty($debug_log)): ?>
                    <div class="wced-log-header">
                        <h4><?php esc_html_e('Debug Log', 'wc-estimated-delivery'); ?></h4>
                        <button type="button" class="button button-small" id="wced-clear-log">
                            <span class="dashicons dashicons-trash" style="margin-top: 3px; font-size: 14px;"></span>
                            <?php esc_html_e('Clear', 'wc-estimated-delivery'); ?>
                        </button>
                    </div>
                    <div class="wced-log-viewer">
                        <?php foreach (array_reverse($debug_log) as $entry): ?>
                            <div class="wced-log-entry">
                                <span class="wced-log-time">[<?php echo esc_html($entry['time']); ?>]</span>
                                <span class="wced-log-level wced-log-level-<?php echo esc_attr($entry['level']); ?>">
                                    [<?php echo esc_html(strtoupper($entry['level'])); ?>]
                                </span>
                                <?php echo esc_html($entry['message']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="description">
                        <?php esc_html_e('No log entries yet. Enable debug mode and visit your store to generate logs.', 'wc-estimated-delivery'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- REST API Info -->
            <div class="wced-card">
                <h3>
                    <span class="dashicons dashicons-rest-api"></span>
                    <?php esc_html_e('REST API', 'wc-estimated-delivery'); ?>
                </h3>
                <p class="description" style="margin-bottom: 12px;">
                    <?php esc_html_e('REST API endpoints for headless WooCommerce setups.', 'wc-estimated-delivery'); ?>
                </p>
                <table class="widefat wced-api-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Endpoint', 'wc-estimated-delivery'); ?></th>
                            <th><?php esc_html_e('Method', 'wc-estimated-delivery'); ?></th>
                            <th><?php esc_html_e('Auth', 'wc-estimated-delivery'); ?></th>
                            <th><?php esc_html_e('Description', 'wc-estimated-delivery'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>/wp-json/wced/v1/delivery-date</code></td>
                            <td><span class="wced-method-badge wced-method-get">GET</span></td>
                            <td><?php esc_html_e('Public', 'wc-estimated-delivery'); ?></td>
                            <td><?php esc_html_e('Get calculated delivery date', 'wc-estimated-delivery'); ?></td>
                        </tr>
                        <tr>
                            <td><code>/wp-json/wced/v1/settings</code></td>
                            <td><span class="wced-method-badge wced-method-get">GET</span></td>
                            <td><?php esc_html_e('Admin only', 'wc-estimated-delivery'); ?></td>
                            <td><?php esc_html_e('Get plugin settings', 'wc-estimated-delivery'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- WPML/Polylang Info -->
            <div class="wced-card">
                <h3>
                    <span class="dashicons dashicons-translation"></span>
                    <?php esc_html_e('Translations (WPML/Polylang)', 'wc-estimated-delivery'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('The following strings are automatically registered for translation:', 'wc-estimated-delivery'); ?>
                </p>
                <ul class="wced-feature-list">
                    <li><?php esc_html_e('Main message template', 'wc-estimated-delivery'); ?></li>
                    <li><?php esc_html_e('Message before cutoff', 'wc-estimated-delivery'); ?></li>
                    <li><?php esc_html_e('Message after cutoff', 'wc-estimated-delivery'); ?></li>
                </ul>
                <?php if (function_exists('icl_register_string')): ?>
                    <p class="wced-status-ok">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('WPML detected! Strings are registered for translation.', 'wc-estimated-delivery'); ?>
                    </p>
                <?php elseif (function_exists('pll_register_string')): ?>
                    <p class="wced-status-ok">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Polylang detected! Strings are registered for translation.', 'wc-estimated-delivery'); ?>
                    </p>
                <?php else: ?>
                    <p class="wced-status-muted">
                        <?php esc_html_e('No translation plugin detected. Install WPML or Polylang to translate messages.', 'wc-estimated-delivery'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php submit_button(__('Save Settings', 'wc-estimated-delivery')); ?>
    </form>
</div>
