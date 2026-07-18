<?php
/**
 * Tracks refunds against a captured order total and enforces the core invariant.
 *
 * The revenue-critical rule is simple and absolute: you can never refund more
 * than was captured. This ledger holds the captured amount, accumulates applied
 * refunds, and rejects any refund that would push the cumulative total past the
 * capture — loudly, before the money moves. It is framework-free so the rule can
 * be unit-tested without WooCommerce, and drives the `Refunded` order status once
 * the full amount has been returned.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * A partial-refund validator for one captured order total.
 */
final class RefundLedger {

	/**
	 * Cumulative refunded amount in minor units.
	 *
	 * @var int
	 */
	private int $refunded = 0;

	/**
	 * @param int $captured Total captured amount in minor units; must be > 0.
	 *
	 * @throws OrderException When the captured amount is not positive.
	 */
	public function __construct( private readonly int $captured ) {
		if ( $captured <= 0 ) {
			throw new OrderException( 'Captured amount must be positive.' );
		}
	}

	/**
	 * Apply a refund, rejecting any amount beyond the remaining balance.
	 *
	 * @param Refund $refund The refund to apply.
	 *
	 * @throws OrderException When the refund exceeds the remaining refundable amount.
	 */
	public function apply( Refund $refund ): void {
		if ( $refund->amount() > $this->remaining() ) {
			throw new OrderException(
				sprintf(
					'Refund of %d exceeds the remaining refundable amount of %d.',
					$refund->amount(),
					$this->remaining()
				)
			);
		}
		$this->refunded += $refund->amount();
	}

	/**
	 * Total captured amount in minor units.
	 *
	 * @return int
	 */
	public function captured(): int {
		return $this->captured;
	}

	/**
	 * Cumulative refunded amount in minor units.
	 *
	 * @return int
	 */
	public function refunded(): int {
		return $this->refunded;
	}

	/**
	 * Amount still available to refund, in minor units.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return $this->captured - $this->refunded;
	}

	/**
	 * Whether the full captured amount has been refunded.
	 *
	 * @return bool
	 */
	public function is_fully_refunded(): bool {
		return 0 === $this->remaining();
	}
}
