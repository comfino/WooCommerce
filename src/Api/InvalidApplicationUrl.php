<?php

namespace Comfino\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thrown when the payment application URL received from the API fails the redirect validation. Its message is
 * translated and meant for the customer, so it is shown as is instead of the generic API error title.
 */
final class InvalidApplicationUrl extends \RuntimeException
{
}
