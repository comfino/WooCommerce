=== Comfino Payment Gateway ===
Contributors: comfino
Donate link: https://comfino.pl/
Tags: comfino, woocommerce, gateway, payment, bank
WC tested up to: 10.7.0
WC requires at least: 3.0
Stable tag: 4.3.1
Tested up to: 7.0
Requires at least: 5.0
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Comfino is an innovative payment method for customers of e-commerce stores! These are installment payments, deferred (buy now, pay later) and more.

### Why Comfino?
* Payment Marketplace, thanks to which your customers will be able to choose the most convenient and safe installment payment.
* Fast and secure verification process.
* Possibility to conduct advertising campaigns with the largest financial institutions in Poland.
* You will reach new customers.

=== Changelog ===

4.3.1
 * Multisite: network activation is now refused with a clear message, and installations that are already network activated show a warning in the admin. Comfino only configures the site it is activated from, so it must be activated individually on each site in a network.
 * Added shop environment metadata to diagnostic reports sent to Comfino, improving support and troubleshooting.
 * Improved billing/shipping address fallback handling for shops using third-party checkout plugins that delay saving address fields on the order (e.g., FunnelKit Checkout).
 * Randomized (jittered) the daily GitHub version check interval and added a short-lived lock to prevent duplicate/bursted checks under concurrent admin requests.
 * Added an option to disable the custom payment method text and instead select up to two financial product types whose names are shown in the checkout payment method label.
 * Added per-product-type cart value limits: admins can now define a minimum and maximum cart value for each financial product type separately (in addition to the global minimum cart amount), with a new management UI in the sale settings.

4.3.0
 * Paywall frontend migrated to V3 API and new frontend Comfino SDK — faster loading, improved stability.
 * Fixed: paywall invisible when Cloudflare RocketLoader is active (added data-cfasync="false" to prevent async script deferral).
 * Fixed: paywall invisible or broken with JS optimization plugins (PhastPress, Autoptimize, WP Rocket) that bundle or defer scripts.
 * Fixed: paywall rendered inside hidden Elementor builder wrapper instead of the visible checkout — only the first visible paywall container is now used.
 * Fixed: paywall invisible or malfunctioning when Google Consent Management Platform (Google CMP) is active.
 * Fixed: paywall loan amount now updates correctly when cart items or shipping costs change.
 * Added support for strict Content Security Policy environments: shops using a nonce-based CSP can now propagate the nonce to the dynamically injected SDK script via the comfino_csp_script_nonce WordPress filter.
 * Added per-product-type installment term limits (allowedProductsConfig): admins can now restrict available installment terms per financial product type in the sale settings. Limits are enforced on both the paywall (financial products listing) and order creation.
 * Added direct redirect mode: when enabled, the full paywall offer browser is skipped and the customer is redirected straight to the Comfino payment gateway with the default financial product.
 * Added a custom paywall CSS style option: admins can inject a custom CSS file into the paywall iframe (only URLs within the store domain are accepted).
 * Added a custom payment method text option, and centralized default logo handling for the Comfino payment method.
 * Added a widget price attribute setting to fix cases where the installment widget showed a stale or incorrect price on pages that render the price asynchronously.
 * Improved delivery/shipping tax handling in cart calculations for more accurate offer amounts.
 * Payment tracking is now more reliable across the checkout flow (tracking ID kept consistent from cart to order), improving diagnostics when investigating payment issues.
 * Enhanced error logging sent to Comfino with more context (severity level, error category, shop environment details), speeding up support and troubleshooting.
 * Improved compatibility with WooCommerce Blocks checkout and block-based themes.
 * Added product-level exclusion: admins can now exclude specific products by ID from Comfino availability, in addition to existing category-based filtering.
