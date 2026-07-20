<?php
/**
 * A candidate shipping rate with its eligibility conditions.
 *
 * One value object models the two rules that matter for rate selection: a
 * free-shipping threshold (`min_subtotal` with a zero cost) and per-weight
 * tiers (`min_weight`/`max_weight`). A rate is eligible only when the cart's
 * subtotal and weight fall inside its bounds; the calculator then picks the
 * cheapest eligible one. Cost, subtotal and weight bounds are integers (minor
 * units for money, grams for weight), so selection is fully deterministic.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Shipping;

/**
 * An immutable, framework-free shipping rate.
 */
final class ShippingRate {

	/**
	 * @param string   $label        Rate label shown to the customer.
	 * @param int      $cost         Rate cost in minor units; >= 0.
	 * @param int      $min_subtotal Eligible when cart subtotal >= this (minor units); >= 0.
	 * @param int      $min_weight   Eligible when cart weight >= this (grams); >= 0.
	 * @param int|null $max_weight   Eligible when cart weight <= this (grams); null = unbounded.
	 *
	 * @throws ShippingException On an empty label, negative values, or inverted weight bounds.
	 */
	public function __construct(
		private readonly string $label,
		private readonly int $cost,
		private readonly int $min_subtotal = 0,
		private readonly int $min_weight = 0,
		private readonly ?int $max_weight = null,
	) {
		if ( '' === $label ) {
			throw new ShippingException( 'Shipping rate label must not be empty.' );
		}
		if ( $cost < 0 || $min_subtotal < 0 || $min_weight < 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-free domain: this message is caught at the WordPress boundary and never reaches a response.
			throw new ShippingException( sprintf( 'Shipping rate "%s" has a negative value.', $label ) );
		}
		if ( null !== $max_weight && $max_weight < $min_weight ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-free domain: this message is caught at the WordPress boundary and never reaches a response.
			throw new ShippingException( sprintf( 'Shipping rate "%s" has max weight below min weight.', $label ) );
		}
	}

	/**
	 * The rate's label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * The rate's cost in minor units.
	 *
	 * @return int
	 */
	public function cost(): int {
		return $this->cost;
	}

	/**
	 * Whether this rate is available for a cart of the given subtotal and weight.
	 *
	 * Bounds are inclusive on both ends, so adjacent weight tiers must not
	 * overlap (e.g. 0..1000 then 1001..5000).
	 *
	 * @param int $subtotal Cart subtotal in minor units.
	 * @param int $weight   Cart weight in grams.
	 *
	 * @return bool
	 */
	public function is_eligible( int $subtotal, int $weight ): bool {
		if ( $subtotal < $this->min_subtotal ) {
			return false;
		}
		if ( $weight < $this->min_weight ) {
			return false;
		}
		if ( null !== $this->max_weight && $weight > $this->max_weight ) {
			return false;
		}
		return true;
	}
}
