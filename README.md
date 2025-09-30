# WooCommerce

## Instalacja

[LINK](docs/comfino.pl.md)

## Instalation

[LINK](docs/comfino.en.md)


## Generate language

 * Install wp-cli on your development machine (https://github.com/wp-cli/wp-cli).
 * Run on docker:
   * curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
   * chmod +x wp-cli.phar
   * ./wp-cli.phar --allow-root i18n make-pot wp-content/plugins/comfino-payment-gateway/
 * Open Poedit and edit file and generate mo file.
