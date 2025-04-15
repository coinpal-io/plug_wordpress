=== Coinpal Payment Gateway ===
Contributors: Coinpal Team
Tags: Crypto，payment
Requires at least: 4.0
Tested up to: 6.7
Stable tag: 1.6.2
License: GPL-2.0-or-later

Official Coinpal module for WordPress WC.

== Description ==
Coinpal specializes in all-in-one payment solutions with customized products and services offered in Thailand, focusing on different vertical areas of the industry. It provides exclusive industry payment solutions tailored to specific business needs.

== Payment method ==
* Crypto
* Credit/Debit Card

== How to contact us ==
* Official website: <https://www.coinpal.io>
* Technical support team: <support@coinpal.com>

== Installation ==

1. Upload the Coinpal folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin `Coinpal Payment Gateway for WC` through the 'Plugins' screen in WordPress.
3. Click `Settings` under `Coinpal Payment Gateway for WC` plugin.
4. Fill in your `Merchant ID`,  and `Secret Key`, then click `Save changes`.

== Screenshots ==

1. Screenshot 1 - Coinpal Settings Page

== External Services ==

This plugin connects to Coinpal services to process cryptocurrency payments and manage merchant account authorizations.

The following services are used:

1. Coinpal Payment Gateway
- URL: https://pay.coinpal.io/gateway/pay/checkout
- Purpose: Used to process user payments at checkout.
- Data sent: Order ID, amount, and merchant authentication details.
- Terms: https://www.coinpal.io/terms.html
- Privacy Policy: https://www.coinpal.io/complaints-policy.html

2. Coinpal Merchant Portal
- URL: https://portal.coinpal.io/
- Purpose: Used to authorize and link the merchant’s Coinpal account with the plugin.
- Data sent: Redirect URI and merchant credentials during OAuth.
- Terms: https://www.coinpal.io/terms.html
- Privacy Policy: https://www.coinpal.io/complaints-policy.html

== Changelog ==

= 1.6.2 =
Fix order_desc length


