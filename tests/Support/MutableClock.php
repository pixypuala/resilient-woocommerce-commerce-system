<?php
/**
 * A test clock whose time can be advanced explicitly.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests\Support;

use Pixypuala\ResilientCommerce\Clock\Clock;

/**
 * Controllable clock for deterministic time-based tests.
 */
final class MutableClock implements Clock {

	public function __construct( private int $now = 1_000_000 ) {}

	public function now(): int {
		return $this->now;
	}

	/**
	 * Move time forward.
	 *
	 * @param int $seconds Seconds to advance.
	 */
	public function advance( int $seconds ): void {
		$this->now += $seconds;
	}
}
