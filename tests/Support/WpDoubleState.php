<?php
/**
 * Per-test registry backing the WordPress/WooCommerce doubles.
 *
 * The domain is framework-free and tested directly. The glue that binds it onto
 * WooCommerce is not — and that glue is exactly where an uncaught domain
 * exception turns into a fatal REST response. This registry holds the orders
 * `wc_get_order()` returns and the actions `do_action()` records, so a test can
 * run the real glue and assert on what it did.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests\Support;

require_once __DIR__ . '/wc-order-double.php';
require_once __DIR__ . '/wp-function-doubles.php';

/**
 * Mutable state shared with the global WordPress doubles.
 */
final class WpDoubleState {

	/**
	 * Orders keyed by id.
	 *
	 * @var array<int, \WC_Order>
	 */
	public static array $orders = array();

	/**
	 * Actions fired, as [ hook, args ] pairs.
	 *
	 * @var array<int, array{0: string, 1: array<int, mixed>}>
	 */
	public static array $actions = array();

	/**
	 * Clear all recorded state. Call from setUp().
	 */
	public static function reset(): void {
		self::$orders  = array();
		self::$actions = array();
	}

	/**
	 * Register an order the glue can load.
	 *
	 * @param int    $id     Order id.
	 * @param string $status Current WooCommerce status.
	 *
	 * @return \WC_Order
	 */
	public static function add_order( int $id, string $status ): \WC_Order {
		$order               = new \WC_Order( $id, $status );
		self::$orders[ $id ] = $order;

		return $order;
	}

	/**
	 * Hook names fired so far.
	 *
	 * @return string[]
	 */
	public static function fired_hooks(): array {
		return array_map( static fn ( array $call ): string => $call[0], self::$actions );
	}
}
