<?php
/**
 * A single refund against an order, as an immutable value object.
 *
 * A refund is only ever a positive amount of money with an optional reason.
 * Validating that the amount is positive at construction means an invalid
 * refund can never exist to be applied — the amount is integer minor units
 * (e.g. cents), so no floating-point drift enters refund totals.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * An immutable refund amount.
 */
final class Refund {

	/**
	 * @param int    $amount Refund amount in minor units; must be > 0.
	 * @param string $reason Optional human-readable reason.
	 *
	 * @throws OrderException When the amount is not positive.
	 */
	public function __construct(
		private readonly int $amount,
		private readonly string $reason = '',
	) {
		if ( $amount <= 0 ) {
			throw new OrderException( 'Refund amount must be positive.' );
		}
	}

	/**
	 * The refund amount in minor units.
	 *
	 * @return int
	 */
	public function amount(): int {
		return $this->amount;
	}

	/**
	 * The refund reason (empty when none was given).
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}
}
