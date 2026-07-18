<?php
/**
 * Raised on invalid order-state operations (unknown status, illegal transition).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * Invalid order-state operation.
 */
final class OrderException extends \RuntimeException {}
