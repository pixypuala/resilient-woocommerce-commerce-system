<?php
/**
 * Raised on invalid shipping operations (bad rate bounds, no eligible rate).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Shipping;

/**
 * Invalid shipping operation.
 */
final class ShippingException extends \RuntimeException {}
