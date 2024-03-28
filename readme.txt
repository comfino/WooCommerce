=== Comfino Payment Gateway ===
Contributors: comfino.pl
Donate link: https://comfino.pl/
Tags: comfino, woocommerce, gateway, payment, bank, deferred payments, installments
WC tested up to: 8.1.1
WC requires at least: 3.0
Stable tag: 3.4.0
Tested up to: 6.3.1
Requires at least: 5.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

3.4.0
 * New paywall frontend architecture based on iframe. Improved filtering of financial products by cart item categories.

3.3.2
 * Fix bugs in the plugin REST endpoints and core logic.

3.3.1
 *  Fix bug on product page.

3.3.0
 * New functionality: filtering of financial products (offers) by cart item category.
 * Add new widget init option productId dynamically inserted into ComfinoProductWidget.init() call.
 * Add new endpoint availableoffertypes and new init option availOffersUrl dynamically inserted into ComfinoProductWidget.init() call.
 * Add new widget init options: productId, productPrice, platform, platformVersion, platformDomain.

3.2.5
 * Add dynamically loaded list of widget types from Comfino API in configuration form (widget settings), fix bug in loading product types from API.

3.2.4
 * Add cart rescue.

3.2.3
 * Fix bug in cart total value calculation if discount is present.

3.2.2
 * Fix bug in communication with Comfino API.

3.2.1
 * Update documentation.
 * Error logger: ignore all errors outside the plugin code.
 * Extend error information - add HTTP request headers in API request body field.

3.2.0
 * Add dynamically loaded list of offer types from Comfino API in configuration form (widget settings).

3.1.1
 * Fix bug in total amount rounding.

3.1.0
* Add support for a new payment method - pay later for companies (COMPANY_BNPL), completely rewrite frontend logic.

3.0.0
 * Complete plugin refactoring: new admin GUI design.
 * Improved errors handling and configuration management.
 * Code clean up.

2.4.0
 * Remove unnecessary elements from paywall for BLIK payment option.
 * Add new classes (Api_Client, Config_Manager), change REST API endpoint for notifications, add new endpoints for remote configuration management.
 * Complete rewrite of plugin configuration management.
 * Add a new widget initialization script, add debug mode for plugin frontend.

2.3.1
 * Update language.
 * Fix verify algorithms.

2.3.0
 * Fix product type
 * Add get and verify algorithms
 * Tested up WC 7.5.1
 * Tested up WP 6.2

2.2.13
 * Improve errors handling in Comfino_Gateway::get_widget_key()

2.2.12
 * Fix bug in saving settings with wrong API key - add checking if API key is valid
 * Update translations, add a new configuration option "priceObserverLevel" for widget in settings form

2.2.11
 * Remove SKU

2.2.10
 * Add sync repo
 * Fix name

2.2.9
 * Improve input data sanitization, validation and escaping

2.2.8
 * Improve errors handling - automatic errors reporting
 * Improve input data sanitization, validation and escaping
 * Fix bug in order data initialization - add deliveryCost

2.2.7
 * Improve errors handling

2.2.6
 * Tested up WC 6.7.0
 * Tested up WP 6.0.1
 * Fix widget enabled/disabled

2.2.5
 * Improve errors handling
 * Add errors logging and preview of errors log in the plugin settings form
 * Documentation update

2.2.4
 * Update contact information (support e-mail and phone number), add a new offer type in Comfino widget: Pay later (PAY_LATER)
 * Add automatic retrieving of widget key from Comfino API
 * Link all images (Comfino logo, icons) to the external host to avoid problems with hosting configurations at some websites (403 error)

2.2.3
 * Tested WP 5.9.3 or 5.4.2

2.2.2
 * Fix styles (widget and paywall)

2.2.1
 * fix extracting customer phone number from order billing data if phone is stored in order metadata

2.2.0
 * Fix modal
 * Add widget

2.1.1
 * Update to WP 5.9.0
 * Update to WC 6.2.0

2.1.0
 * Add cancel
 * Add resign
 * Fix rsso

2.0.3
 * Fix statuses

2.0.2
 * Fix loan term and product type
 * Fix logo
 * Add image product photo

2.0.1
 * Add header info

2.0.0
 * New layout products

1.2.0
 * Remove empty "Representative example"
 * Remove "Loan Term"

1.1.0
 * Change host api sandbox
 * Fix documentation

1.0.0
 * First release Comfino Payment Gateway
