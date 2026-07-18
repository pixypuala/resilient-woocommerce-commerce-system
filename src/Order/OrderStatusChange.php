<?php
/**
 * A resolved intent to move one order to a target status.
 *
 * This is the framework-free output of interpreting an order webhook: which
 * order, and which status it should move to. The live WooCommerce adapter takes
 * this value object and applies it to a real `WC_Order`; the mapping that
 * produces it is fully unit-tested without WooCommerce loaded.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * An immutable order-id + target-status pair.
 */
final class OrderStatusChange {

	/**
	 * @param int         $order_id Target order id; must be > 0.
	 * @param OrderStatus $target   Status the order should move to.
	 */
	public function __construct(
		private readonly int $order_id,
		private readonly OrderStatus $target,
	) {}

	/**
	 * The target order id.
	 *
	 * @return int
	 */
	public function order_id(): int {
		return $this->order_id;
	}

	/**
	 * The status the order should move to.
	 *
	 * @return OrderStatus
	 */
	public function target(): OrderStatus {
		return $this->target;
	}
}
