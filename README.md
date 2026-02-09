# WC Estimated Delivery Pro

A WooCommerce plugin that displays estimated delivery dates on checkout, cart, and product pages with a comprehensive admin control panel.

## Features

- **Dynamic Delivery Date Calculation** - Calculates delivery based on cutoff time, business days, weekends, and holidays
- **Multiple Display Locations** - Show on checkout, cart, and/or product pages
- **Customizable Messages** - Different messages for before/after cutoff time
- **Style Customization** - Colors, border radius, padding, and icon selection
- **Holiday Management** - Manual entry or automatic sync from Nager.Date API
- **Trust Badges** - Display customizable trust badges on product pages
- **Product Rating Badge** - Show real WooCommerce product ratings
- **Import/Export** - Backup and restore all settings
- **REST API** - Endpoints for headless WooCommerce
- **WPML/Polylang Compatible** - Full translation support
- **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
- **AJAX Updates** - Real-time updates when checkout form changes

## Installation

1. Upload the `wc-estimated-delivery` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under WooCommerce → Estimated Delivery

## Configuration

### General Settings
- Enable/disable the plugin
- Choose checkout position (5 options)
- Enable display on product and cart pages
- Select date format and day name display

### Schedule & Days
- Set cutoff time (orders before this time ship faster)
- Configure business days before and after cutoff
- Enable/disable Saturday delivery
- Enable/disable Sunday delivery

### Messages
- Customize the main delivery message
- Set specific messages for before/after cutoff
- Use `{date}` placeholder for the calculated date

### Style & Design
- Show/hide icon
- Choose icon type (emoji, truck, box, calendar, or custom)
- Customize colors (background, border, text)
- Adjust border radius and padding

### Trust Badges
Display up to 4 customizable badges on product pages:

- **10 SVG Icons**: truck, trophy, flag, star, heart, shield, check, gift, leaf, clock
- **Custom Emoji**: Use any emoji as badge icon
- **Text Formatting**: Normal, Bold, Italic, Bold+Italic
- **Rating Badge**: Automatically displays product rating (e.g., "4.50/5.00")
- **Color Customization**: Background, border, text, and icon colors

### Holidays
- Automatic sync from [Nager.Date API](https://date.nager.at/) (free, no API key required)
- Support for 100+ countries
- Manual holiday entry in multiple date formats (YYYY-MM-DD, DD.MM.YYYY, MM/DD/YYYY)

### Tools
- **Export Settings**: Download all settings as JSON file
- **Import Settings**: Restore settings from JSON backup
- **Debug Mode**: Enable logging for troubleshooting
- **REST API Info**: View available endpoints

## REST API

### Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/wp-json/wced/v1/delivery-date` | GET | Public | Get calculated delivery date |
| `/wp-json/wced/v1/settings` | GET | Admin | Get plugin settings |

### Example Response (delivery-date)
```json
{
  "success": true,
  "data": {
    "date": "2025-02-07",
    "formatted_date": "Friday, 7 February 2025",
    "is_before_cutoff": true,
    "cutoff_time": "14:00"
  }
}
```

## Cache Exclusion

Since the delivery date is time-sensitive, you may need to exclude certain pages from caching.

### WP Super Cache
Add to **Rejected Strings**:
```
checkout
cart
```

### W3 Total Cache
**Never cache the following pages**:
```
checkout/*
cart/*
```

### WP Rocket
**Never Cache URLs**:
```
/checkout/(.*)
/cart/(.*)
```

### LiteSpeed Cache
**Do Not Cache URIs**:
```
/checkout/
/cart/
```

### Cloudflare
Create Page Rules with Cache Level → Bypass for:
- `*yourdomain.com/checkout*`
- `*yourdomain.com/cart*`

## Delivery Calculation Logic

1. Check current time against cutoff time
2. If before cutoff: use `min_days` (faster delivery)
3. If after cutoff: use `max_days` (standard delivery)
4. Starting from current date, count forward
5. Skip:
   - Sundays (unless Sunday delivery enabled)
   - Saturdays (unless Saturday delivery enabled)
   - Holidays (from the holiday list)
6. Stop when required business days are reached

### Example

**Settings:**
- Cutoff: 2:00 PM
- Before cutoff: 1 business day
- After cutoff: 2 business days
- Saturday/Sunday delivery: No

**Scenario 1:** Order placed Tuesday at 10:00 AM
- Before cutoff → 1 business day needed
- Wednesday = delivery date

**Scenario 2:** Order placed Tuesday at 4:00 PM
- After cutoff → 2 business days needed
- Thursday = delivery date

**Scenario 3:** Order placed Friday at 4:00 PM
- After cutoff → 2 business days needed
- Skip Saturday, Sunday
- Tuesday = delivery date

## Hooks & Filters

### Actions
```php
// Before delivery estimate is displayed
do_action('wced_before_delivery_estimate');

// After delivery estimate is displayed
do_action('wced_after_delivery_estimate');
```

### Filters
```php
// Modify calculated delivery date
add_filter('wced_delivery_date', function($date, $is_before_cutoff) {
    // Modify $date (DateTime object)
    return $date;
}, 10, 2);

// Modify the display message
add_filter('wced_delivery_message', function($message, $formatted_date) {
    return $message;
}, 10, 2);
```

## Translation (WPML/Polylang)

The following strings are automatically registered for translation:
- Main message template
- Message before cutoff
- Message after cutoff

The plugin uses the `wc-estimated-delivery` text domain for all translatable strings.

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 8.0+

## Support

For issues and feature requests, please visit:
https://devopcity.ro/wc-estimated-delivery

## License

GPL v2 or later

## Changelog

### 3.0.1
- **Security**: Singleton protection - added `__clone()` and `__wakeup()` to prevent cloning and unserialization
- **Security**: `date_format` option now validated against strict whitelist of 8 allowed formats
- **Security**: `holidays_country` validated with regex (`/^[A-Z]{2}$/`) in `sanitize_options()`
- **Security**: Client IP detection now proxy/CDN-aware (Cloudflare, X-Forwarded-For, X-Real-IP) with `FILTER_VALIDATE_IP`

### 3.0.0
- **Security**: Fixed XSS vulnerabilities in admin.js - 4 injection points where `.html()` was used with unescaped server responses
- **Security**: Added `escapeHtml()` helper in admin JS for safe HTML rendering
- **Security**: Tab parameter in admin page now validated against strict whitelist
- **Security**: SVG color parameter in `get_badge_icon_svg()` now internally escaped with `esc_attr()`
- **Security**: Trust badges grid-template-columns uses `intval()` for safe output
- **Security**: REST API `/delivery-date` endpoint now rate-limited (30 req/min) with HTTP 429 response
- **Performance**: Holidays array parsed once per request and cached in class property
- **Performance**: Conditional script loading - classic `frontend.js` only on classic checkout, `frontend-blocks.js` only on block checkout
- **Performance**: Single `wp_localize_script` call per page (eliminated duplicate nonce/ajax_url output)
- **Performance**: Translation string registration skipped early when no WPML/Polylang is active
- **Performance**: Holidays cache invalidated on sync and import operations
- **Compatibility**: Minimum PHP version raised to 8.0 (aligned with WordPress 6.9 recommendation)

### 2.5.0
- **WooCommerce Blocks support**: Delivery estimate now displays in Block Checkout and Block Cart (render_block integration)
- **Security**: Import data sanitized with `wp_unslash()` and 100KB size limit to prevent DoS
- **Security**: Rate limiting on holiday sync (5 req/5min) and import (10 req/min) AJAX endpoints
- **Security**: Debug log stored with autoload disabled to reduce memory usage
- **Fix**: Cron job for automatic holiday sync now properly scheduled on activation and unscheduled on deactivation
- **Fix**: `holidays_auto_sync` option now has UI toggle in Holidays tab and is properly sanitized
- **Fix**: Replaced deprecated `current_time('mysql')` with `wp_date('Y-m-d H:i:s')`
- **Fix**: Implemented documented hooks (`wced_before_delivery_estimate`, `wced_after_delivery_estimate` actions and `wced_delivery_date`, `wced_delivery_message` filters)
- **Compatibility**: Updated `WC tested up to: 10.5` for WooCommerce 10.5
- **New**: `frontend-blocks.js` - vanilla JS script for Block Checkout/Cart with fetch API (no jQuery dependency)

### 2.4.0
- Rating badge now shows selected icon (not stars)
- Rating format changed to 5.00/5.00
- Added custom emoji option for all badges
- Code cleanup and bug fixes

### 2.3.0
- Added dynamic Product Rating badge (shows actual WooCommerce product rating)
- Added 10 SVG icon options for badges (truck, trophy, flag, star, heart, shield, check, gift, leaf, clock)
- Added text formatting options (normal, bold, italic, bold+italic) per badge
- Added icon color customization

### 2.2.0
- Added Trust Badges feature for product pages (customizable icons & text)
- Added Import/Export settings functionality
- Added Debug Mode with logging
- Added REST API endpoints (`/wced/v1/delivery-date`, `/wced/v1/settings`)
- Added WPML/Polylang compatibility for message translations
- New Tools tab in admin panel

### 2.1.0
- Added manual Saturday/Sunday delivery toggle options
- Weekend days can now be individually enabled as delivery days

### 2.0.0
- Complete rewrite with admin control panel
- Added Nager.Date API integration for automatic holiday sync
- Multiple icon options
- Color customization
- HPOS compatibility
- AJAX checkout updates
- Multilingual support via text domain

### 1.0.0
- Initial release
