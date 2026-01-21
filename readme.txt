=== ePay Payment Solutions - EPIC ===
Contributors: epaydk
Tags: payment, gateway, subscription, subscriptions, psp
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 7.0.10
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 6.0
WC tested up to: 9.8.2

Integrates ePay payment gateway into your WooCommerce installation.

== Description ==

Important: This module should only be installed if you are using ePay’s new  payment gateway. All Classic customers must remain on version 6.0.23.

With ePay Payment for WooCommerce, you are able to integrate the ePay payment window into your WooCommerce installation and start receiving secure online payments.

= Features =
* Receive payments securely through the ePay payment window
* Create and handle subscriptions
* Get an overview over the status for your payments directly from your WooCommerce order page.
* Capture your payments directly from your WooCommerce order page.
* Credit your payments directly from your WooCommerce order page.
* Delete your payments directly from your WooCommerce order page.
* Sign up, process, cancel, reactivate and change subscriptions
* Supports WooCommerce 6.0 and up.
* Supports WooCommerce Subscription 5.x, 6.x, 7.x
* Bulk Capture


== External Services ==
This plugin connects to external ePay payment services to process online payments securely.

When customers place an order, the plugin communicates with the ePay Payment Gateway API to create and manage payment sessions, authorize transactions, capture payments, and perform refunds or cancellations.
These connections are required for the plugin to function — without them, payments cannot be processed.

- What data is sent and when:
  - When a customer initiates a payment, the following information is securely transmitted to ePay’s servers:
    - Order info, total amount, and currency
    - Customer’s IP address and optional billing details (if configured to send)
    - Technical identifiers such as transaction/session IDs
- During refunds or captures, the plugin sends the transaction reference and amount to the API.
- No card data is stored or processed by WooCommerce or this plugin. All sensitive payment details are handled entirely by ePay’s secure payment window and infrastructure.

Why this data is sent:
This data is necessary to authorize and complete payments, to issue refunds, and to display payment statuses in WooCommerce.

Who provides the service:
All payment transactions are handled by ePay Payment Solutions.
ePay provides secure payment processing and tokenization infrastructure for merchants.

Service website: https://epay.eu
Terms of use: https://epay.eu/terms
Privacy policy: https://epay.eu/privacy
Service API endpoints : https://payments.epay.eu and https://ssl.ditonlinebetalingssystem.dk


== Changelog ==
= 7.0.10
* Fixed payment method description rendering issue.

= 7.0.9
* Plugin Name Updated

= 7.0.8
* This module should only be installed if you are using ePay’s new payment gateway. All Classic customers must remain on version 6.0.23

= 7.0.1
* Changed gateway to ePay EPIC
