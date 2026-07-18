<?php
/**
 * The result of a tax calculation: the total plus a per-rate breakdown.
 *
 * Keeping the per-rate amounts (not just the total) makes compound and
 * multi-jurisdiction results auditable — a reviewer can see exactly how much
 * each rate contributed. All amounts are integer minor units (e.g. cents).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tax;

/**
 * An immutable tax result.
 */
final class TaxBreakdown {

	/**
	 * @param int                $total    Total tax in minor units.
	 * @param array<string, int> $per_rate Tax per rate label, in minor units.
	 */
	public function __construct(
		private readonly int $total,
		private readonly array $per_rate,
	) {}

	/**
	 * Total tax across all rates, in minor units.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Tax contributed by a single rate, in minor units.
	 *
	 * @param string $label The rate label.
	 *
	 * @return int
	 *
	 * @throws TaxException When the label was not part of the calculation.
	 */
	public function for_rate( string $label ): int {
		if ( ! array_key_exists( $label, $this->per_rate ) ) {
			throw new TaxException( sprintf( 'No tax was calculated for rate "%s".', $label ) );
		}
		return $this->per_rate[ $label ];
	}

	/**
	 * The full per-rate breakdown, keyed by label, in minor units.
	 *
	 * @return array<string, int>
	 */
	public function per_rate(): array {
		return $this->per_rate;
	}
}
