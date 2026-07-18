<?php
/**
 * Oversell-safe stock ledger for a single SKU.
 *
 * The classic WooCommerce failure is two customers buying the last unit at the
 * same time. This ledger separates *reserved* stock (a hold during checkout,
 * with an expiry) from *committed* stock (a completed sale). Availability always
 * accounts for live reservations, so a reservation can never push availability
 * below zero — the invariant that prevents overselling. Expired reservations are
 * reclaimed lazily using an injected clock, so the logic stays pure and testable.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Inventory;

use Pixypuala\ResilientCommerce\Clock\Clock;
use Pixypuala\ResilientCommerce\Clock\SystemClock;

/**
 * Tracks on-hand, reserved, and committed stock for one SKU.
 */
final class StockLedger {

	/**
	 * Active reservations: reference => array{qty:int, expires_at:int}.
	 *
	 * @var array<string, array{qty:int, expires_at:int}>
	 */
	private array $reservations = array();

	/**
	 * @param int   $on_hand Physical units available to sell.
	 * @param Clock $clock   Time source for reservation expiry.
	 *
	 * @throws StockException When initial stock is negative.
	 */
	public function __construct(
		private int $on_hand,
		private readonly Clock $clock = new SystemClock(),
	) {
		if ( $on_hand < 0 ) {
			throw new StockException( 'Initial on-hand stock cannot be negative.' );
		}
	}

	/**
	 * Units available to reserve right now (on-hand minus live reservations).
	 *
	 * @return int Never negative.
	 */
	public function available(): int {
		$this->reclaim_expired();
		$reserved = 0;
		foreach ( $this->reservations as $reservation ) {
			$reserved += $reservation['qty'];
		}
		return max( 0, $this->on_hand - $reserved );
	}

	/**
	 * Place a hold on stock during checkout.
	 *
	 * @param string $reference Unique hold reference (e.g. cart/session id).
	 * @param int    $quantity  Units to hold (> 0).
	 * @param int    $ttl       Seconds until the hold expires.
	 *
	 * @return bool True if reserved; false if insufficient available stock.
	 *
	 * @throws StockException On non-positive quantity or duplicate reference.
	 */
	public function reserve( string $reference, int $quantity, int $ttl = 900 ): bool {
		if ( $quantity <= 0 ) {
			throw new StockException( 'Reservation quantity must be positive.' );
		}
		$this->reclaim_expired();
		if ( isset( $this->reservations[ $reference ] ) ) {
			throw new StockException( sprintf( 'Reservation "%s" already exists.', $reference ) );
		}
		// The oversell guard: never reserve more than is actually available.
		if ( $quantity > $this->available() ) {
			return false;
		}
		$this->reservations[ $reference ] = array(
			'qty'        => $quantity,
			'expires_at' => $this->clock->now() + $ttl,
		);
		return true;
	}

	/**
	 * Convert a reservation into a completed sale, decrementing on-hand.
	 *
	 * @param string $reference Reservation reference.
	 *
	 * @throws StockException When the reference is unknown or already expired.
	 */
	public function commit( string $reference ): void {
		$this->reclaim_expired();
		if ( ! isset( $this->reservations[ $reference ] ) ) {
			throw new StockException( sprintf( 'Cannot commit unknown or expired reservation "%s".', $reference ) );
		}
		$this->on_hand -= $this->reservations[ $reference ]['qty'];
		unset( $this->reservations[ $reference ] );
	}

	/**
	 * Release a hold without selling (cart abandoned, payment failed).
	 *
	 * Releasing an unknown reference is a no-op so retries and races are safe.
	 *
	 * @param string $reference Reservation reference.
	 */
	public function release( string $reference ): void {
		unset( $this->reservations[ $reference ] );
	}

	/**
	 * Current committed on-hand total (excludes live reservations).
	 *
	 * @return int
	 */
	public function on_hand(): int {
		return $this->on_hand;
	}

	/**
	 * Drop reservations whose TTL has passed, returning their stock to available.
	 */
	private function reclaim_expired(): void {
		$now = $this->clock->now();
		foreach ( $this->reservations as $reference => $reservation ) {
			if ( $reservation['expires_at'] <= $now ) {
				unset( $this->reservations[ $reference ] );
			}
		}
	}
}
