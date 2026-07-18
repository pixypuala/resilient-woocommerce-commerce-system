<?php
/**
 * Raised on invalid tax operations (negative amount, negative rate, bad label).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tax;

/**
 * Invalid tax operation.
 */
final class TaxException extends \RuntimeException {}
