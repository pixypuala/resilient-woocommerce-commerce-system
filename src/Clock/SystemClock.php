<?php
/**
 * Real wall-clock implementation of Clock.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Clock;

/**
 * Uses the system clock.
 */
final class SystemClock implements Clock {

	public function now(): int {
		return time();
	}
}
