<?php
/**
 * Deterministic, framework-free tax calculator.
 *
 * Money is handled as integer minor units (e.g. cents) and rates as integer
 * `rate_e4` values, so the only place a fraction appears is the single rounding
 * step per rate — which uses explicit half-up rounding on integers. There is no
 * float arithmetic anywhere, so the same inputs always produce the same tax.
 *
 * Non-compound rates are charged on the summed net base. Compound rates are then
 * charged on the base plus the total non-compound tax (the standard "tax on tax"
 * rule), so their order in the argument list does not change the result.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tax;

/**
 * Computes tax over line items for a set of rates.
 */
final class TaxCalculator {

	/**
	 * The divisor implied by `rate_e4` units (1/10000).
	 */
	private const RATE_DIVISOR = 10000;

	/**
	 * Calculate tax for the given net line amounts under the given rates.
	 *
	 * @param list<int> $line_amounts Net (pre-tax) line totals in minor units; each >= 0.
	 * @param TaxRate   ...$rates     The rates to apply.
	 *
	 * @return TaxBreakdown
	 *
	 * @throws TaxException On a negative line amount or duplicate rate labels.
	 */
	public function calculate( array $line_amounts, TaxRate ...$rates ): TaxBreakdown {
		$base = 0;
		foreach ( $line_amounts as $amount ) {
			if ( $amount < 0 ) {
				throw new TaxException( 'Line amount cannot be negative.' );
			}
			$base += $amount;
		}

		$per_rate = array();
		$total    = 0;

		// Pass 1: non-compound rates charge on the net base.
		$non_compound_tax = 0;
		foreach ( $rates as $rate ) {
			if ( $rate->is_compound() ) {
				continue;
			}
			$per_rate          = $this->record( $per_rate, $rate, $this->apply_rate( $base, $rate->rate_e4() ) );
			$non_compound_tax += $per_rate[ $rate->label() ];
		}

		// Pass 2: compound rates charge on the base plus non-compound tax.
		$compound_base = $base + $non_compound_tax;
		foreach ( $rates as $rate ) {
			if ( ! $rate->is_compound() ) {
				continue;
			}
			$per_rate = $this->record( $per_rate, $rate, $this->apply_rate( $compound_base, $rate->rate_e4() ) );
		}

		foreach ( $per_rate as $tax ) {
			$total += $tax;
		}

		return new TaxBreakdown( $total, $per_rate );
	}

	/**
	 * Record a rate's tax, rejecting a duplicate label loudly.
	 *
	 * @param array<string, int> $per_rate Accumulator keyed by label.
	 * @param TaxRate            $rate     The rate just calculated.
	 * @param int                $tax      Its tax in minor units.
	 *
	 * @return array<string, int>
	 *
	 * @throws TaxException When two rates share a label.
	 */
	private function record( array $per_rate, TaxRate $rate, int $tax ): array {
		if ( array_key_exists( $rate->label(), $per_rate ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-free domain: this message is caught at the WordPress boundary and never reaches a response.
			throw new TaxException( sprintf( 'Duplicate tax rate label "%s".', $rate->label() ) );
		}
		$per_rate[ $rate->label() ] = $tax;
		return $per_rate;
	}

	/**
	 * Apply one rate to an amount with half-up rounding to the minor unit.
	 *
	 * Both operands are non-negative, so `(numerator + half) / divisor` via
	 * integer division rounds ties away from zero (half-up) exactly.
	 *
	 * @param int $amount  Taxable amount in minor units (>= 0).
	 * @param int $rate_e4 Rate in units of 1/10000 (>= 0).
	 *
	 * @return int Tax in minor units.
	 */
	private function apply_rate( int $amount, int $rate_e4 ): int {
		$numerator = $amount * $rate_e4;
		$half      = intdiv( self::RATE_DIVISOR, 2 );
		return intdiv( $numerator + $half, self::RATE_DIVISOR );
	}
}
