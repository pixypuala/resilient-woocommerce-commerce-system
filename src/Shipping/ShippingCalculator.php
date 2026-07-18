<?php
/**
 * Selects the applicable shipping rate for a cart, framework-free.
 *
 * Given a cart's subtotal and weight, this filters the offered rates down to
 * those eligible (free-shipping thresholds and weight tiers are encoded on each
 * rate) and returns the cheapest. Ties keep the first rate in argument order, so
 * the outcome is deterministic. When nothing is eligible it fails loudly rather
 * than silently returning "no shipping", which would hide a misconfiguration.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Shipping;

/**
 * Chooses a shipping rate for a cart.
 */
final class ShippingCalculator {

	/**
	 * Select the cheapest eligible rate for the given cart.
	 *
	 * @param int          $subtotal Cart subtotal in minor units; >= 0.
	 * @param int          $weight   Cart weight in grams; >= 0.
	 * @param ShippingRate ...$rates The offered rates.
	 *
	 * @return ShippingRate The cheapest eligible rate.
	 *
	 * @throws ShippingException On a negative cart value, no rates, or no eligible rate.
	 */
	public function select( int $subtotal, int $weight, ShippingRate ...$rates ): ShippingRate {
		if ( $subtotal < 0 || $weight < 0 ) {
			throw new ShippingException( 'Cart subtotal and weight cannot be negative.' );
		}
		if ( array() === $rates ) {
			throw new ShippingException( 'No shipping rates were offered.' );
		}

		$selected = null;
		foreach ( $rates as $rate ) {
			if ( ! $rate->is_eligible( $subtotal, $weight ) ) {
				continue;
			}
			// Strict less-than keeps the first rate on a cost tie (stable choice).
			if ( null === $selected || $rate->cost() < $selected->cost() ) {
				$selected = $rate;
			}
		}

		if ( null === $selected ) {
			throw new ShippingException( 'No shipping rate is eligible for this cart.' );
		}
		return $selected;
	}
}
