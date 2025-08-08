=== GB BGN + EUR for WooCommerce ===
Contributors: jorobogdanov
Tags: woocommerce, currency, eur, dual currency
Requires at least: 5.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display WooCommerce product prices in both Bulgarian Lev (BGN) and Euro (EUR) on the frontend.

== Description ==

**GB Woo BGN EUR** is a lightweight plugin that allows your WooCommerce store to display product prices in Bulgarian Lev (BGN) and their equivalent in Euro (EUR), using a fixed or dynamically calculated conversion rate.

This plugin is ideal for stores preparing for Euro adoption in Bulgaria or serving customers across the EU.

=== Features ===

* Show prices in BGN and EUR on the shop and product pages
* Fixed or dynamic conversion rate (if configured)
* Customizable display format
* Clean integration with WooCommerce templates
* Works with simple and variable products

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gb-woo-bgn-eur` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the ‘Plugins’ screen in WordPress
3. Go to **WooCommerce > Settings > GB Woo BGN EUR** to configure currency display options and exchange rate.

== Frequently Asked Questions ==

= Can I set a custom exchange rate? =  
Yes. The plugin allows you to set a fixed exchange rate between BGN and EUR.

= Will this plugin work with variable products? =  
Yes. Prices for variations are also displayed in both currencies.

== Screenshots ==

1. WooCommerce product page showing price in BGN and EUR
2. Plugin settings page

== Changelog ==

= 1.1 =
* Removed parentheses around the EUR price and added a slash (/) as a separator.
* Removed all styling adjustments between the BGN and EUR price.

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1 =
Improved formatting of EUR prices and removed style dependencies.

= 1.0.0 =
Initial release of the plugin for dual BGN and EUR price display in WooCommerce.

== Arbitrary section ==

If you want to programmatically customize the EUR price calculation or styling, hooks and filters are available. Refer to the plugin code for examples.

== License ==

This plugin is licensed under the GPLv2 or later.
