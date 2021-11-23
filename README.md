# WooCommerce

## Instalacja

[LINK](docs/comfino.pl.md)

## Instalation

[LINK](docs/comfino.en.md)


## Generate language

 * install wp-cli on your development machine (https://github.com/wp-cli/wp-cli)
 * Run on docker
   * curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
   * chmod +x wp-cli.phar
   * ./wp-cli.phar --allow-root i18n make-pot wp-content/plugins/wc-comfino-payment-gateway/
 * rename wc-comfino-payment-gateway to comfino.pot
 * open poedit and edit file and generate mo file

=== WooCommerce - Comfino Payment Gateway ===
 * Contributors: Comperia.pl
 * Donate link: https://comfino.pl/
 * Tags: comfino, woocommerce, gateway, payment, bank
 * WC tested up to: 5.5.1
 * WC requires at least: 3.0
 * Tested up to: 5.7.2
 * Requires at least: 5.0
 * Requires PHP: 7.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
