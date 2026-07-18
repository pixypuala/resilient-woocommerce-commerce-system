<?php
/**
 * Idempotent order-status transitions for a single order.
 *
 * Order webhooks arrive asynchronously and can be redelivered or reordered, so
 * applying a status change must be safe to repeat. This machine enforces two
 * rules: a transition to the status the order already holds is an idempotent
 * no-op (a redelivered event does no harm), and any transition not permitted by
 * the WooCommerce-aligned lifecycle is rejected loudly rather than silently
 * corrupting order state. The logic is framework-free so it can be unit-tested
 * without WooCommerce and reused behind the `resilient_commerce_webhook` action.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * Applies validated status transitions to one order.
 */
final class OrderStateMachine {

	/**
	 * @param OrderStatus $status The order's current status.
	 */
	public function __construct( private OrderStatus $status ) {}

	/**
	 * The order's current status.
	 *
	 * @return OrderStatus
	 */
	public function status(): OrderStatus {
		return $this->status;
	}

	/**
	 * Attempt to move the order to $target.
	 *
	 * @param OrderStatus $target Desired status.
	 *
	 * @return bool True when the status actually changed; false when the order was
	 *              already in $target (an idempotent no-op).
	 *
	 * @throws OrderException When the transition is not permitted.
	 */
	public function transition_to( OrderStatus $target ): bool {
		if ( $this->status === $target ) {
			return false;
		}
		if ( ! $this->status->can_transition_to( $target ) ) {
			throw new OrderException(
				sprintf(
					'Illegal order transition from "%s" to "%s".',
					$this->status->value,
					$target->value
				)
			);
		}
		$this->status = $target;
		return true;
	}

	/**
	 * Apply a status reported by a WooCommerce order webhook.
	 *
	 * @param string $wc_status WooCommerce status string (with or without `wc-`).
	 *
	 * @return bool True when the status changed; false on an idempotent redelivery.
	 *
	 * @throws OrderException On an unknown status or an illegal transition.
	 */
	public function apply_wc_status( string $wc_status ): bool {
		return $this->transition_to( OrderStatus::from_wc_status( $wc_status ) );
	}
}
