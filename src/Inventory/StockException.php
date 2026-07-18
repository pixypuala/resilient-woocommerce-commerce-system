<?php
/**
 * Raised on invalid stock operations (bad quantity, unknown reservation).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Inventory;

/**
 * Invalid inventory operation.
 */
final class StockException extends \RuntimeException {}
