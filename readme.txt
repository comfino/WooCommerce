=== Comfino Payment Gateway ===
Contributors: comfino
Donate link: https://comfino.pl/
Tags: comfino, woocommerce, gateway, payment, bank
WC tested up to: 10.5.0
WC requires at least: 3.0
Stable tag: 4.3.0
Tested up to: 6.9
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
