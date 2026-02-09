<?php
/**
 * Plugin Name: WC Estimated Delivery Pro
 * Plugin URI: https://devopcity.ro/wc-sla-timer
 * Description: Display estimated delivery date on checkout, cart and product pages with a comprehensive control panel.
 * Version: 3.0.1
 * Author: Devopcity
 * Author URI: https://devopcity.ro
 * Text Domain: wc-estimated-delivery
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * WC requires at least: 5.0
 * WC tested up to: 10.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('WCED_VERSION', '3.0.1');
define('WCED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCED_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class WC_Estimated_Delivery {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin options
     */
    private $options;

    /**
     * Cached holidays array
     */
    private $holidays_cache = null;

    /**
     * Default options
     */
    private $defaults = [
        'enabled' => 'yes',
        'cutoff_hour' => 14,
        'cutoff_minute' => 0,
        'min_days' => 1,
        'max_days' => 2,
                'holidays' => '',
        'message_template' => 'Estimated delivery: {date}',
        'message_before_cutoff' => '',
        'message_after_cutoff' => '',
        'show_icon' => 'yes',
        'icon_type' => 'emoji',
        'custom_icon' => '',
        'bg_color' => '#f8f9fa',
        'border_color' => '#e5e5e5',
        'text_color' => '#333333',
        'border_radius' => 8,
        'padding' => 12,
        'position' => 'before_payment',
        'show_on_product' => 'no',
        'show_on_cart' => 'no',
        'date_format' => 'j F Y',
        'show_day_name' => 'yes',
        'holidays_last_sync' => '',
        'holidays_auto_sync' => 'yes',
        'holidays_country' => 'US',
        'work_saturday' => 'no',
        'work_sunday' => 'no',
        'debug_mode' => 'no',
        // Trust badges
        'badges_enabled' => 'no',
        'badge_1_enabled' => 'yes',
        'badge_1_icon' => 'truck',
        'badge_1_custom_icon' => '',
        'badge_1_text' => 'Free shipping over 350 lei',
        'badge_1_style' => 'normal',
        'badge_2_enabled' => 'yes',
        'badge_2_icon' => 'trophy',
        'badge_2_custom_icon' => '',
        'badge_2_text' => 'Internationally awarded wines',
        'badge_2_style' => 'normal',
        'badge_3_enabled' => 'yes',
        'badge_3_icon' => 'flag',
        'badge_3_custom_icon' => '',
        'badge_3_text' => 'Produced in Romania',
        'badge_3_style' => 'normal',
        'badge_4_enabled' => 'no',
        'badge_4_icon' => 'star',
        'badge_4_custom_icon' => '',
        'badge_4_text' => 'Customer rating',
        'badge_4_style' => 'bold',
        'badges_bg_color' => '#ffffff',
        'badges_border_color' => '#e5e5e5',
        'badges_text_color' => '#333333',
        'badges_icon_color' => '#333333',
    ];

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \RuntimeException('Cannot unserialize singleton');
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->options = wp_parse_args(
            get_option('wced_options', []),
            $this->defaults
        );

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu'], 99);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
            add_filter('plugin_action_links_' . WCED_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        }

        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'frontend_enqueue_scripts']);
        $this->register_display_hooks();

        // AJAX
        add_action('wp_ajax_wced_get_delivery_date', [$this, 'ajax_get_delivery_date']);
        add_action('wp_ajax_nopriv_wced_get_delivery_date', [$this, 'ajax_get_delivery_date']);
        add_action('wp_ajax_wced_sync_holidays', [$this, 'ajax_sync_holidays']);
        add_action('wp_ajax_wced_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_wced_import_settings', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_wced_clear_log', [$this, 'ajax_clear_log']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Cron for automatic holiday sync
        add_action('wced_sync_holidays_cron', [$this, 'cron_sync_holidays']);

        // WPML/Polylang compatibility
        add_action('init', [$this, 'register_translatable_strings']);

        // HPOS compatibility
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

        // WooCommerce Blocks support
        add_action('woocommerce_blocks_loaded', [$this, 'register_blocks_support']);
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Register WooCommerce Blocks support
     */
    public function register_blocks_support() {
        if ($this->options['enabled'] !== 'yes') return;

        // Block Checkout support
        add_filter('render_block_woocommerce/checkout-order-summary-block', [$this, 'render_block_checkout_estimate'], 10, 2);

        // Block Cart support
        if ($this->options['show_on_cart'] === 'yes') {
            add_filter('render_block_woocommerce/cart-order-summary-block', [$this, 'render_block_cart_estimate'], 10, 2);
        }
    }

    /**
     * Render delivery estimate in Block Checkout
     */
    public function render_block_checkout_estimate($content, $block) {
        return $content . $this->get_delivery_estimate_html();
    }

    /**
     * Render delivery estimate in Block Cart
     */
    public function render_block_cart_estimate($content, $block) {
        return $content . $this->get_delivery_estimate_html();
    }

    /**
     * Get delivery estimate HTML (for blocks and reuse)
     */
    public function get_delivery_estimate_html() {
        if ($this->options['enabled'] !== 'yes') return '';

        $delivery = $this->calculate_delivery_date();

        // Allow filtering of the delivery date
        $delivery['date'] = apply_filters('wced_delivery_date', $delivery['date'], $delivery['is_before_cutoff']);
        $delivery['formatted_date'] = $this->format_date($delivery['date']);

        $message = $this->get_translated_message($this->options['message_template'], 'message_template');

        if ($delivery['is_before_cutoff'] && !empty($this->options['message_before_cutoff'])) {
            $message = $this->get_translated_message($this->options['message_before_cutoff'], 'message_before_cutoff');
        } elseif (!$delivery['is_before_cutoff'] && !empty($this->options['message_after_cutoff'])) {
            $message = $this->get_translated_message($this->options['message_after_cutoff'], 'message_after_cutoff');
        }

        $message = str_replace('{date}', $delivery['formatted_date'], $message);

        // Allow filtering of the display message
        $message = apply_filters('wced_delivery_message', $message, $delivery['formatted_date']);

        $icon = $this->get_icon_html();

        $this->log(sprintf('Displayed delivery estimate (blocks): %s (before_cutoff: %s)',
            $delivery['formatted_date'],
            $delivery['is_before_cutoff'] ? 'yes' : 'no'
        ));

        ob_start();
        do_action('wced_before_delivery_estimate');
        $before = ob_get_clean();

        ob_start();
        do_action('wced_after_delivery_estimate');
        $after = ob_get_clean();

        $html = $before;
        $html .= '<div class="wced-delivery-estimate" id="wced-delivery-estimate">';
        $html .= $icon;
        $html .= '<span class="wced-message"><strong>' . esc_html($message) . '</strong></span>';
        $html .= '</div>';
        $html .= $after;

        return $html;
    }

    /**
     * Check rate limit for AJAX actions
     */
    private function check_rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        $key = 'wced_rl_' . $action . '_' . ($user_id ?: md5($ip));
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Get client IP address (proxy/CDN aware)
     */
    private function get_client_ip() {
        // Check trusted proxy headers (order matters)
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs; take the first (client)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                $ip = filter_var($ip, FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Register display hooks based on settings
     */
    private function register_display_hooks() {
        if ($this->options['enabled'] !== 'yes') return;

        // Checkout position
        $position = $this->options['position'];
        $checkout_hooks = [
            'before_payment' => ['woocommerce_review_order_before_payment', 25],
            'after_payment' => ['woocommerce_review_order_after_payment', 25],
            'before_order_review' => ['woocommerce_checkout_before_order_review', 10],
            'after_order_review' => ['woocommerce_checkout_after_order_review', 10],
            'before_customer_details' => ['woocommerce_checkout_before_customer_details', 10],
        ];

        if (isset($checkout_hooks[$position])) {
            add_action($checkout_hooks[$position][0], [$this, 'display_delivery_estimate'], $checkout_hooks[$position][1]);
        }

        // Product page
        if ($this->options['show_on_product'] === 'yes') {
            // Trust badges (before delivery estimate)
            if ($this->options['badges_enabled'] === 'yes') {
                add_action('woocommerce_single_product_summary', [$this, 'display_trust_badges'], 24);
            }
            add_action('woocommerce_single_product_summary', [$this, 'display_delivery_estimate'], 25);
        }

        // Cart page
        if ($this->options['show_on_cart'] === 'yes') {
            add_action('woocommerce_cart_totals_before_order_total', [$this, 'display_delivery_estimate']);
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Estimated Delivery', 'wc-estimated-delivery'),
            __('Estimated Delivery', 'wc-estimated-delivery'),
            'manage_woocommerce',
            'wc-estimated-delivery',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wced_options_group', 'wced_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = [];

        $sanitized['enabled'] = isset($input['enabled']) ? 'yes' : 'no';
        $sanitized['cutoff_hour'] = min(23, max(0, absint($input['cutoff_hour'] ?? 14)));
        $sanitized['cutoff_minute'] = min(59, max(0, absint($input['cutoff_minute'] ?? 0)));
        $sanitized['min_days'] = max(1, absint($input['min_days'] ?? 1));
        $sanitized['max_days'] = max($sanitized['min_days'], absint($input['max_days'] ?? 2));
                $sanitized['holidays'] = sanitize_textarea_field($input['holidays'] ?? '');
        $sanitized['message_template'] = sanitize_text_field($input['message_template'] ?? '');
        $sanitized['message_before_cutoff'] = sanitize_text_field($input['message_before_cutoff'] ?? '');
        $sanitized['message_after_cutoff'] = sanitize_text_field($input['message_after_cutoff'] ?? '');
        $sanitized['show_icon'] = isset($input['show_icon']) ? 'yes' : 'no';
        $sanitized['icon_type'] = in_array($input['icon_type'] ?? '', ['emoji', 'truck', 'box', 'calendar', 'custom'])
            ? $input['icon_type'] : 'emoji';
        $sanitized['custom_icon'] = esc_url_raw($input['custom_icon'] ?? '');
        $sanitized['bg_color'] = sanitize_hex_color($input['bg_color'] ?? '#f8f9fa') ?: '#f8f9fa';
        $sanitized['border_color'] = sanitize_hex_color($input['border_color'] ?? '#e5e5e5') ?: '#e5e5e5';
        $sanitized['text_color'] = sanitize_hex_color($input['text_color'] ?? '#333333') ?: '#333333';
        $sanitized['border_radius'] = min(50, max(0, absint($input['border_radius'] ?? 8)));
        $sanitized['padding'] = min(50, max(0, absint($input['padding'] ?? 12)));
        $sanitized['position'] = in_array($input['position'] ?? '', array_keys($this->get_checkout_positions()))
            ? $input['position'] : 'before_payment';
        $sanitized['show_on_product'] = isset($input['show_on_product']) ? 'yes' : 'no';
        $sanitized['show_on_cart'] = isset($input['show_on_cart']) ? 'yes' : 'no';
        $valid_date_formats = ['j F Y', 'F j, Y', 'M j, Y', 'd F Y', 'm/d/Y', 'd/m/Y', 'd.m.Y', 'Y-m-d'];
        $sanitized['date_format'] = in_array($input['date_format'] ?? '', $valid_date_formats, true)
            ? $input['date_format'] : 'j F Y';
        $sanitized['show_day_name'] = isset($input['show_day_name']) ? 'yes' : 'no';
        $country_input = strtoupper(sanitize_text_field($input['holidays_country'] ?? 'US'));
        $sanitized['holidays_country'] = preg_match('/^[A-Z]{2}$/', $country_input) ? $country_input : 'US';
        $sanitized['work_saturday'] = isset($input['work_saturday']) ? 'yes' : 'no';
        $sanitized['work_sunday'] = isset($input['work_sunday']) ? 'yes' : 'no';
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';
        $sanitized['holidays_auto_sync'] = isset($input['holidays_auto_sync']) ? 'yes' : 'no';

        // Trust badges
        $sanitized['badges_enabled'] = isset($input['badges_enabled']) ? 'yes' : 'no';
        $valid_icons = ['truck', 'trophy', 'flag', 'star', 'heart', 'shield', 'check', 'gift', 'leaf', 'clock', 'custom'];
        $valid_styles = ['normal', 'bold', 'italic', 'bold-italic'];

        for ($i = 1; $i <= 4; $i++) {
            $sanitized["badge_{$i}_enabled"] = isset($input["badge_{$i}_enabled"]) ? 'yes' : 'no';
            $sanitized["badge_{$i}_icon"] = in_array($input["badge_{$i}_icon"] ?? '', $valid_icons)
                ? $input["badge_{$i}_icon"] : 'star';
            $sanitized["badge_{$i}_custom_icon"] = sanitize_text_field($input["badge_{$i}_custom_icon"] ?? '');
            $sanitized["badge_{$i}_text"] = sanitize_text_field($input["badge_{$i}_text"] ?? '');
            $sanitized["badge_{$i}_style"] = in_array($input["badge_{$i}_style"] ?? '', $valid_styles)
                ? $input["badge_{$i}_style"] : 'normal';
        }

        $sanitized['badges_bg_color'] = sanitize_hex_color($input['badges_bg_color'] ?? '#ffffff') ?: '#ffffff';
        $sanitized['badges_border_color'] = sanitize_hex_color($input['badges_border_color'] ?? '#e5e5e5') ?: '#e5e5e5';
        $sanitized['badges_text_color'] = sanitize_hex_color($input['badges_text_color'] ?? '#333333') ?: '#333333';
        $sanitized['badges_icon_color'] = sanitize_hex_color($input['badges_icon_color'] ?? '#333333') ?: '#333333';

        return $sanitized;
    }

    /**
     * Get checkout positions
     */
    private function get_checkout_positions() {
        return [
            'before_payment' => __('Before payment methods', 'wc-estimated-delivery'),
            'after_payment' => __('After payment methods', 'wc-estimated-delivery'),
            'before_order_review' => __('Before order review', 'wc-estimated-delivery'),
            'after_order_review' => __('After order review', 'wc-estimated-delivery'),
            'before_customer_details' => __('Before customer details', 'wc-estimated-delivery'),
        ];
    }

    /**
     * Admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-estimated-delivery') return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style(
            'wced-admin',
            WCED_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WCED_VERSION
        );

        wp_enqueue_script(
            'wced-admin',
            WCED_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            WCED_VERSION,
            true
        );

        wp_localize_script('wced-admin', 'wced_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wced_admin_nonce'),
            'strings' => [
                'syncing' => __('Syncing...', 'wc-estimated-delivery'),
                'sync_success' => __('holidays synced successfully!', 'wc-estimated-delivery'),
                'sync_error' => __('Sync error', 'wc-estimated-delivery'),
                'confirm_sync' => __('This will replace the current holiday list. Continue?', 'wc-estimated-delivery'),
                'exporting' => __('Exporting...', 'wc-estimated-delivery'),
                'export_error' => __('Export error', 'wc-estimated-delivery'),
                'importing' => __('Importing...', 'wc-estimated-delivery'),
                'import_success' => __('Settings imported successfully!', 'wc-estimated-delivery'),
                'import_error' => __('Import error', 'wc-estimated-delivery'),
                'confirm_import' => __('This will replace all current settings. Continue?', 'wc-estimated-delivery'),
                'invalid_file' => __('Please select a valid JSON file', 'wc-estimated-delivery'),
                'clearing_log' => __('Clearing...', 'wc-estimated-delivery'),
                'log_cleared' => __('Log cleared', 'wc-estimated-delivery'),
            ],
        ]);
    }

    /**
     * Frontend scripts
     */
    public function frontend_enqueue_scripts() {
        if ($this->options['enabled'] !== 'yes') return;
        if (!is_checkout() && !is_cart() && !is_product()) return;

        wp_enqueue_style(
            'wced-frontend',
            WCED_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            WCED_VERSION
        );

        // Inline custom styles
        $custom_css = $this->get_custom_css();
        wp_add_inline_style('wced-frontend', $custom_css);

        // Detect if current page uses WooCommerce Blocks
        $uses_blocks = false;
        if ((is_checkout() && has_block('woocommerce/checkout')) ||
            (is_cart() && has_block('woocommerce/cart'))) {
            $uses_blocks = true;
        }

        $localized_vars = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wced_nonce'),
        ];

        // Classic checkout/cart: load jQuery-based script
        if (!$uses_blocks) {
            wp_enqueue_script(
                'wced-frontend',
                WCED_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                WCED_VERSION,
                true
            );
            wp_localize_script('wced-frontend', 'wced_vars', $localized_vars);
        }

        // Block checkout/cart: load vanilla JS script
        if ($uses_blocks) {
            wp_enqueue_script(
                'wced-frontend-blocks',
                WCED_PLUGIN_URL . 'assets/js/frontend-blocks.js',
                [],
                WCED_VERSION,
                true
            );
            wp_localize_script('wced-frontend-blocks', 'wced_blocks_vars', $localized_vars);
        }

        // Product pages always use classic hooks
        if (is_product() && !wp_script_is('wced-frontend', 'enqueued')) {
            wp_enqueue_script(
                'wced-frontend',
                WCED_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                WCED_VERSION,
                true
            );
            wp_localize_script('wced-frontend', 'wced_vars', $localized_vars);
        }
    }

    /**
     * Get custom CSS
     */
    private function get_custom_css() {
        return sprintf(
            '.wced-delivery-estimate {
                background-color: %s;
                border-color: %s;
                color: %s;
                border-radius: %dpx;
                padding: %dpx;
            }',
            esc_attr($this->options['bg_color']),
            esc_attr($this->options['border_color']),
            esc_attr($this->options['text_color']),
            absint($this->options['border_radius']),
            absint($this->options['padding'])
        );
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-estimated-delivery'),
            __('Settings', 'wc-estimated-delivery')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Calculate delivery date
     */
    public function calculate_delivery_date() {
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);

        $cutoff = (clone $now)->setTime(
            (int) $this->options['cutoff_hour'],
            (int) $this->options['cutoff_minute'],
            0
        );

        // Determine days to add based on cutoff
        $is_before_cutoff = $now < $cutoff;
        $days_to_add = $is_before_cutoff
            ? (int) $this->options['min_days']
            : (int) $this->options['max_days'];

        // Get holidays array
        $holidays = $this->get_holidays_array();

        // Calculate delivery date
        $delivery_date = clone $now;
        $work_saturday = $this->options['work_saturday'] === 'yes';
        $work_sunday = $this->options['work_sunday'] === 'yes';

        while ($days_to_add > 0) {
            $delivery_date->modify('+1 day');
            $dow = (int) $delivery_date->format('N'); // 1=Monday, 7=Sunday
            $date_str = $delivery_date->format('Y-m-d');

            // Skip Sunday (7) unless enabled
            if ($dow === 7 && !$work_sunday) continue;

            // Skip Saturday (6) unless enabled
            if ($dow === 6 && !$work_saturday) continue;

            // Skip holidays
            if (in_array($date_str, $holidays)) continue;

            $days_to_add--;
        }

        return [
            'date' => $delivery_date,
            'is_before_cutoff' => $is_before_cutoff,
            'formatted_date' => $this->format_date($delivery_date),
        ];
    }

    /**
     * Get holidays array
     */
    private function get_holidays_array() {
        if ($this->holidays_cache !== null) {
            return $this->holidays_cache;
        }

        $holidays_text = $this->options['holidays'];
        if (empty($holidays_text)) {
            $this->holidays_cache = [];
            return [];
        }

        $lines = explode("\n", $holidays_text);
        $holidays = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Support formats: 2024-12-25, 25.12.2024, 25/12/2024, 12/25/2024
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $line, $m)) {
                $holidays[] = $line;
            } elseif (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $line, $m)) {
                $holidays[] = "{$m[3]}-{$m[2]}-{$m[1]}";
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $line, $m)) {
                // Assume MM/DD/YYYY format
                $holidays[] = "{$m[3]}-{$m[1]}-{$m[2]}";
            }
        }

        $this->holidays_cache = $holidays;
        return $holidays;
    }

    /**
     * Format date
     */
    private function format_date($date) {
        $format = $this->options['date_format'];

        if ($this->options['show_day_name'] === 'yes') {
            return wp_date('l, ' . $format, $date->getTimestamp());
        }

        return wp_date($format, $date->getTimestamp());
    }

    /**
     * Get icon HTML
     */
    private function get_icon_html() {
        if ($this->options['show_icon'] !== 'yes') return '';

        $icon_type = $this->options['icon_type'];

        switch ($icon_type) {
            case 'emoji':
                return '<span class="wced-icon wced-icon-emoji" aria-hidden="true">📦</span>';
            case 'truck':
                return '<span class="wced-icon wced-icon-svg" aria-hidden="true">' . $this->get_truck_svg() . '</span>';
            case 'box':
                return '<span class="wced-icon wced-icon-svg" aria-hidden="true">' . $this->get_box_svg() . '</span>';
            case 'calendar':
                return '<span class="wced-icon wced-icon-svg" aria-hidden="true">' . $this->get_calendar_svg() . '</span>';
            case 'custom':
                $url = $this->options['custom_icon'];
                if (!empty($url)) {
                    return '<img class="wced-icon wced-icon-custom" src="' . esc_url($url) . '" alt="" />';
                }
                break;
        }

        return '';
    }

    /**
     * SVG Icons
     */
    private function get_truck_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>';
    }

    private function get_box_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
    }

    private function get_calendar_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    }

    /**
     * Display delivery estimate
     */
    public function display_delivery_estimate() {
        if ($this->options['enabled'] !== 'yes') return;

        $delivery = $this->calculate_delivery_date();

        // Allow filtering of the delivery date
        $delivery['date'] = apply_filters('wced_delivery_date', $delivery['date'], $delivery['is_before_cutoff']);
        $delivery['formatted_date'] = $this->format_date($delivery['date']);

        // Build message with translation support
        $message = $this->get_translated_message($this->options['message_template'], 'message_template');

        // Check for before/after cutoff specific messages
        if ($delivery['is_before_cutoff'] && !empty($this->options['message_before_cutoff'])) {
            $message = $this->get_translated_message($this->options['message_before_cutoff'], 'message_before_cutoff');
        } elseif (!$delivery['is_before_cutoff'] && !empty($this->options['message_after_cutoff'])) {
            $message = $this->get_translated_message($this->options['message_after_cutoff'], 'message_after_cutoff');
        }

        // Replace placeholders
        $message = str_replace('{date}', $delivery['formatted_date'], $message);

        // Allow filtering of the display message
        $message = apply_filters('wced_delivery_message', $message, $delivery['formatted_date']);

        $icon = $this->get_icon_html();

        $this->log(sprintf('Displayed delivery estimate: %s (before_cutoff: %s)',
            $delivery['formatted_date'],
            $delivery['is_before_cutoff'] ? 'yes' : 'no'
        ));

        do_action('wced_before_delivery_estimate');

        echo '<div class="wced-delivery-estimate" id="wced-delivery-estimate">';
        echo $icon;
        echo '<span class="wced-message"><strong>' . esc_html($message) . '</strong></span>';
        echo '</div>';

        do_action('wced_after_delivery_estimate');
    }

    /**
     * Display trust badges on product page
     */
    public function display_trust_badges() {
        if ($this->options['badges_enabled'] !== 'yes') return;

        global $product;

        $badges = [];

        for ($i = 1; $i <= 4; $i++) {
            if ($this->options["badge_{$i}_enabled"] === 'yes') {
                $text = $this->options["badge_{$i}_text"];

                // Badge 4 is special - rating badge
                if ($i === 4 && $product) {
                    $rating = $product->get_average_rating();
                    $review_count = $product->get_review_count();

                    // Skip if no reviews
                    if ($review_count === 0) continue;

                    // Format rating as 5.00/5.00
                    $rating_formatted = number_format((float)$rating, 2) . '/5.00';

                    // Replace {rating} placeholder or prepend rating
                    if (strpos($text, '{rating}') !== false) {
                        $text = str_replace('{rating}', $rating_formatted, $text);
                    } else {
                        $text = $rating_formatted . ' ' . $text;
                    }
                }

                if (!empty($text)) {
                    $badges[] = [
                        'icon' => $this->options["badge_{$i}_icon"],
                        'custom_icon' => $this->options["badge_{$i}_custom_icon"] ?? '',
                        'text' => $text,
                        'style' => $this->options["badge_{$i}_style"] ?? 'normal',
                    ];
                }
            }
        }

        if (empty($badges)) return;

        $bg_color = esc_attr($this->options['badges_bg_color']);
        $border_color = esc_attr($this->options['badges_border_color']);
        $text_color = esc_attr($this->options['badges_text_color']);
        $icon_color = esc_attr($this->options['badges_icon_color'] ?? $text_color);

        echo '<div class="wced-trust-badges" style="
            display: grid;
            grid-template-columns: repeat(' . intval(count($badges)) . ', 1fr);
            gap: 10px;
            margin: 20px 0 10px 0;
        ">';

        foreach ($badges as $badge) {
            $text_style = $this->get_text_style_css($badge['style']);

            echo '<div class="wced-trust-badge" style="
                background-color: ' . $bg_color . ';
                border: 1px solid ' . $border_color . ';
                color: ' . $text_color . ';
                border-radius: 8px;
                padding: 12px 10px;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
            ">';

            // Icon - show custom emoji or SVG icon
            echo '<span class="wced-badge-icon" style="color: ' . $icon_color . ';">';
            if ($badge['icon'] === 'custom' && !empty($badge['custom_icon'])) {
                echo '<span style="font-size: 24px; line-height: 1;">' . esc_html($badge['custom_icon']) . '</span>';
            } else {
                echo $this->get_badge_icon_svg($badge['icon'], $icon_color);
            }
            echo '</span>';

            // Text
            echo '<span class="wced-badge-text" style="font-size: 12px; line-height: 1.3; ' . $text_style . '">';
            echo esc_html($badge['text']);
            echo '</span>';

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Get text style CSS
     */
    private function get_text_style_css($style) {
        switch ($style) {
            case 'bold':
                return 'font-weight: 700;';
            case 'italic':
                return 'font-style: italic;';
            case 'bold-italic':
                return 'font-weight: 700; font-style: italic;';
            default:
                return 'font-weight: 500;';
        }
    }

    /**
     * Get badge icon SVG
     */
    private function get_badge_icon_svg($icon, $color = '#333333') {
        $color = esc_attr($color);
        $icons = [
            'truck' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
            'trophy' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
            'flag' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
            'star' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="' . $color . '" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'heart' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="' . $color . '" stroke="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            'shield' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            'check' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'gift' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
            'leaf' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>',
            'clock' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        ];

        return $icons[$icon] ?? $icons['star'];
    }

    /**
     * AJAX handler for delivery date
     */
    public function ajax_get_delivery_date() {
        check_ajax_referer('wced_nonce', 'nonce');

        $delivery = $this->calculate_delivery_date();

        wp_send_json_success([
            'date' => $delivery['formatted_date'],
            'is_before_cutoff' => $delivery['is_before_cutoff'],
        ]);
    }

    /**
     * AJAX handler for holiday sync
     */
    public function ajax_sync_holidays() {
        check_ajax_referer('wced_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'wc-estimated-delivery'));
        }

        if (!$this->check_rate_limit('sync_holidays', 5, 300)) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'wc-estimated-delivery'));
        }

        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'US';

        // Validate country code (2 uppercase letters)
        if (!preg_match('/^[A-Z]{2}$/', strtoupper($country))) {
            wp_send_json_error(__('Invalid country code', 'wc-estimated-delivery'));
        }

        $result = $this->sync_holidays_from_api(strtoupper($country));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Cron job for automatic sync
     */
    public function cron_sync_holidays() {
        if ($this->options['holidays_auto_sync'] !== 'yes') {
            return;
        }

        $country = $this->options['holidays_country'] ?? 'US';
        $this->sync_holidays_from_api($country);
    }

    /**
     * Sync holidays from Nager.Date API
     */
    public function sync_holidays_from_api($country = 'US') {
        $current_year = (int) wp_date('Y');
        $next_year = $current_year + 1;

        $holidays_current = $this->fetch_holidays_from_api($current_year, $country);
        $holidays_next = $this->fetch_holidays_from_api($next_year, $country);

        if (is_wp_error($holidays_current)) {
            return $holidays_current;
        }

        if (is_wp_error($holidays_next)) {
            // Not critical if next year fails
            $holidays_next = [];
        }

        $all_holidays = array_merge($holidays_current, $holidays_next);

        if (empty($all_holidays)) {
            return new WP_Error('no_holidays', __('No holidays found for this country.', 'wc-estimated-delivery'));
        }

        // Format dates
        $formatted_dates = [];
        $holidays_details = [];

        foreach ($all_holidays as $holiday) {
            $date = $holiday['date'];
            $formatted_dates[] = wp_date('d.m.Y', strtotime($date));
            $holidays_details[] = [
                'date' => wp_date('d.m.Y', strtotime($date)),
                'name' => $holiday['localName'] ?? $holiday['name'],
            ];
        }

        // Remove duplicates and sort
        $formatted_dates = array_unique($formatted_dates);
        usort($formatted_dates, function($a, $b) {
            $date_a = DateTime::createFromFormat('d.m.Y', $a);
            $date_b = DateTime::createFromFormat('d.m.Y', $b);
            return $date_a <=> $date_b;
        });

        // Save to options
        $options = get_option('wced_options', []);
        $options['holidays'] = implode("\n", $formatted_dates);
        $options['holidays_last_sync'] = wp_date('Y-m-d H:i:s');
        $options['holidays_country'] = $country;
        update_option('wced_options', $options);

        // Update options and invalidate cache
        $this->options = wp_parse_args($options, $this->defaults);
        $this->holidays_cache = null;

        return [
            'count' => count($formatted_dates),
            'holidays' => implode("\n", $formatted_dates),
            'details' => $holidays_details,
            'last_sync' => $options['holidays_last_sync'],
        ];
    }

    /**
     * Fetch holidays from Nager.Date API
     */
    private function fetch_holidays_from_api($year, $country = 'US') {
        $url = sprintf(
            'https://date.nager.at/api/v3/PublicHolidays/%d/%s',
            absint($year),
            strtoupper(sanitize_text_field($country))
        );

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('API connection error: %s', 'wc-estimated-delivery'), $response->get_error_message())
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(__('API returned error: %d', 'wc-estimated-delivery'), $status_code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $holidays = json_decode($body, true);

        if (!is_array($holidays)) {
            return new WP_Error(
                'parse_error',
                __('Could not parse API response', 'wc-estimated-delivery')
            );
        }

        return $holidays;
    }

    /**
     * Get available countries
     */
    public function get_available_countries() {
        $transient_key = 'wced_available_countries';
        $countries = get_transient($transient_key);

        if ($countries !== false) {
            return $countries;
        }

        $url = 'https://date.nager.at/api/v3/AvailableCountries';

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return $this->get_default_countries();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return $this->get_default_countries();
        }

        $countries = [];
        foreach ($data as $country) {
            if (isset($country['countryCode'], $country['name'])) {
                $countries[sanitize_text_field($country['countryCode'])] = sanitize_text_field($country['name']);
            }
        }

        // Cache for 1 week
        set_transient($transient_key, $countries, WEEK_IN_SECONDS);

        return $countries;
    }

    /**
     * Default countries list
     */
    private function get_default_countries() {
        return [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HU' => 'Hungary',
            'GR' => 'Greece',
            'PT' => 'Portugal',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
        ];
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to access this page.', 'wc-estimated-delivery'));
        }

        include WCED_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Get option
     */
    public function get_option($key, $default = null) {
        return $this->options[$key] ?? $default;
    }

    /**
     * Get all options
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wced/v1', '/delivery-date', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_delivery_date'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wced/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_settings'],
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            },
        ]);
    }

    /**
     * REST API: Get delivery date
     */
    public function rest_get_delivery_date($request) {
        if (!$this->check_rate_limit('rest_delivery', 30, 60)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Too many requests. Please try again later.', 'wc-estimated-delivery'),
            ], 429);
        }

        $this->log('REST API: delivery-date endpoint called');

        $delivery = $this->calculate_delivery_date();

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'date' => $delivery['date']->format('Y-m-d'),
                'formatted_date' => $delivery['formatted_date'],
                'is_before_cutoff' => $delivery['is_before_cutoff'],
                'cutoff_time' => sprintf('%02d:%02d', $this->options['cutoff_hour'], $this->options['cutoff_minute']),
            ],
        ], 200);
    }

    /**
     * REST API: Get settings (authenticated)
     */
    public function rest_get_settings($request) {
        $this->log('REST API: settings endpoint called');

        // Remove sensitive data if any
        $safe_options = $this->options;
        unset($safe_options['holidays_last_sync']);

        return new WP_REST_Response([
            'success' => true,
            'data' => $safe_options,
        ], 200);
    }

    /**
     * AJAX: Export settings
     */
    public function ajax_export_settings() {
        check_ajax_referer('wced_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'wc-estimated-delivery'));
        }

        $this->log('Settings exported');

        $export_data = [
            'plugin' => 'wc-estimated-delivery',
            'version' => WCED_VERSION,
            'exported_at' => wp_date('Y-m-d H:i:s'),
            'settings' => $this->options,
        ];

        wp_send_json_success($export_data);
    }

    /**
     * AJAX: Import settings
     */
    public function ajax_import_settings() {
        check_ajax_referer('wced_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'wc-estimated-delivery'));
        }

        if (!$this->check_rate_limit('import_settings', 10, 60)) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'wc-estimated-delivery'));
        }

        $import_data = isset($_POST['import_data']) ? wp_unslash($_POST['import_data']) : '';

        if (empty($import_data)) {
            wp_send_json_error(__('No import data provided', 'wc-estimated-delivery'));
        }

        // Limit import size to 100KB to prevent DoS
        if (strlen($import_data) > 102400) {
            wp_send_json_error(__('Import data too large (max 100KB)', 'wc-estimated-delivery'));
        }

        // Decode JSON
        $data = json_decode($import_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Import failed: Invalid JSON');
            wp_send_json_error(__('Invalid JSON format', 'wc-estimated-delivery'));
        }

        // Validate structure
        if (!isset($data['plugin']) || $data['plugin'] !== 'wc-estimated-delivery') {
            $this->log('Import failed: Invalid plugin identifier');
            wp_send_json_error(__('Invalid settings file. This file was not exported from WC Estimated Delivery.', 'wc-estimated-delivery'));
        }

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            $this->log('Import failed: No settings in file');
            wp_send_json_error(__('No settings found in import file', 'wc-estimated-delivery'));
        }

        // Sanitize and save settings
        $sanitized = $this->sanitize_options($data['settings']);
        update_option('wced_options', $sanitized);

        // Update instance and invalidate cache
        $this->options = wp_parse_args($sanitized, $this->defaults);
        $this->holidays_cache = null;

        $this->log('Settings imported successfully from version ' . ($data['version'] ?? 'unknown'));

        wp_send_json_success([
            'message' => __('Settings imported successfully!', 'wc-estimated-delivery'),
            'imported_from_version' => $data['version'] ?? 'unknown',
        ]);
    }

    /**
     * AJAX: Clear log
     */
    public function ajax_clear_log() {
        check_ajax_referer('wced_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied', 'wc-estimated-delivery'));
        }

        delete_option('wced_debug_log');

        wp_send_json_success(__('Log cleared', 'wc-estimated-delivery'));
    }

    /**
     * Log message (if debug mode enabled)
     */
    public function log($message, $level = 'info') {
        if ($this->options['debug_mode'] !== 'yes') {
            return;
        }

        $log = get_option('wced_debug_log', []);

        // Keep only last 100 entries
        if (count($log) >= 100) {
            $log = array_slice($log, -99);
        }

        $log[] = [
            'time' => wp_date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
        ];

        update_option('wced_debug_log', $log, false);
    }

    /**
     * Get debug log
     */
    public function get_log() {
        return get_option('wced_debug_log', []);
    }

    /**
     * Register translatable strings for WPML/Polylang
     */
    public function register_translatable_strings() {
        // Skip if no translation plugin is active
        if (!function_exists('icl_register_string') && !function_exists('pll_register_string')) {
            return;
        }

        // WPML String Translation
        if (function_exists('icl_register_string')) {
            icl_register_string('wc-estimated-delivery', 'Message Template', $this->options['message_template']);
            icl_register_string('wc-estimated-delivery', 'Message Before Cutoff', $this->options['message_before_cutoff']);
            icl_register_string('wc-estimated-delivery', 'Message After Cutoff', $this->options['message_after_cutoff']);
        }

        // Polylang
        if (function_exists('pll_register_string')) {
            pll_register_string('wced_message_template', $this->options['message_template'], 'WC Estimated Delivery');
            pll_register_string('wced_message_before_cutoff', $this->options['message_before_cutoff'], 'WC Estimated Delivery');
            pll_register_string('wced_message_after_cutoff', $this->options['message_after_cutoff'], 'WC Estimated Delivery');
        }
    }

    /**
     * Get translated message
     */
    private function get_translated_message($message, $context = 'message_template') {
        // WPML
        if (function_exists('icl_t')) {
            $contexts = [
                'message_template' => 'Message Template',
                'message_before_cutoff' => 'Message Before Cutoff',
                'message_after_cutoff' => 'Message After Cutoff',
            ];
            return icl_t('wc-estimated-delivery', $contexts[$context] ?? 'Message Template', $message);
        }

        // Polylang
        if (function_exists('pll__')) {
            return pll__($message);
        }

        return $message;
    }

    /**
     * Get defaults (for export reference)
     */
    public function get_defaults() {
        return $this->defaults;
    }
}

/**
 * Get icon SVG for admin display
 */
function wced_get_icon_svg_admin($icon, $color = '#333333') {
    $icons = [
        'truck' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
        'trophy' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
        'flag' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        'star' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="' . esc_attr($color) . '" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'heart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="' . esc_attr($color) . '" stroke="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'shield' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        'check' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'gift' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
        'leaf' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>',
        'clock' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'custom' => '<span style="font-size: 20px;">✏️</span>',
    ];

    return $icons[$icon] ?? $icons['star'];
}

/**
 * Initialize plugin
 */
function wced_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            esc_html_e('WC Estimated Delivery requires WooCommerce to be installed and active.', 'wc-estimated-delivery');
            echo '</p></div>';
        });
        return;
    }

    return WC_Estimated_Delivery::get_instance();
}
add_action('plugins_loaded', 'wced_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    if (!get_option('wced_options')) {
        add_option('wced_options', []);
    }

    // Schedule daily holiday sync cron
    if (!wp_next_scheduled('wced_sync_holidays_cron')) {
        wp_schedule_event(time(), 'daily', 'wced_sync_holidays_cron');
    }
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clear transients
    delete_transient('wced_available_countries');

    // Unschedule cron
    $timestamp = wp_next_scheduled('wced_sync_holidays_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wced_sync_holidays_cron');
    }
});
