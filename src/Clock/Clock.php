<?php
/**
 * A source of the current time.
 *
 * Injecting the clock instead of calling time() directly lets tests drive
 * replay windows and reservation expiry deterministically.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Clock;

/**
 * Current time provider.
 */
interface Clock {

	/**
	 * Current time as a Unix timestamp (seconds).
	 *
	 * @return int
	 */
	public function now(): int;
}
