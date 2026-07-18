<?php
/**
 * A single tax rate applied to a taxable amount.
 *
 * The rate is stored as an integer in units of 1/10000 (`rate_e4`) rather than a
 * float, so `7.25%` is `725` and no floating-point drift can enter the rate
 * itself. That keeps the whole tax calculation deterministic and reproducible.
 * A compound rate is applied on top of the base plus any non-compound tax
 * (the standard "tax on tax" case), which the calculator handles.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tax;

/**
 * An immutable, framework-free tax rate.
 */
final class TaxRate {

	/**
	 * @param string $label    Human/report label, unique within a calculation.
	 * @param int    $rate_e4  Rate in units of 1/10000 (e.g. 725 = 7.25%); >= 0.
	 * @param bool   $compound Whether this rate applies on top of prior tax.
	 *
	 * @throws TaxException On an empty label or a negative rate.
	 */
	public function __construct(
		private readonly string $label,
		private readonly int $rate_e4,
		private readonly bool $compound = false,
	) {
		if ( '' === $label ) {
			throw new TaxException( 'Tax rate label must not be empty.' );
		}
		if ( $rate_e4 < 0 ) {
			throw new TaxException( sprintf( 'Tax rate "%s" cannot be negative.', $label ) );
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
	 * The rate in units of 1/10000.
	 *
	 * @return int
	 */
	public function rate_e4(): int {
		return $this->rate_e4;
	}

	/**
	 * Whether the rate compounds on top of non-compound tax.
	 *
	 * @return bool
	 */
	public function is_compound(): bool {
		return $this->compound;
	}
}
