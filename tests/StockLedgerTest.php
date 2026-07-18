<?php
/**
 * Tests for the oversell-safe stock ledger.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Inventory\StockException;
use Pixypuala\ResilientCommerce\Inventory\StockLedger;
use Pixypuala\ResilientCommerce\Tests\Support\MutableClock;

final class StockLedgerTest extends TestCase {

	private MutableClock $clock;

	protected function setUp(): void {
		$this->clock = new MutableClock();
	}

	public function test_reservation_reduces_availability(): void {
		$ledger = new StockLedger( 5, $this->clock );
		$this->assertTrue( $ledger->reserve( 'cart-a', 2 ) );
		$this->assertSame( 3, $ledger->available() );
		$this->assertSame( 5, $ledger->on_hand(), 'On-hand is unchanged until commit.' );
	}

	public function test_cannot_oversell_the_last_unit(): void {
		$ledger = new StockLedger( 1, $this->clock );
		$this->assertTrue( $ledger->reserve( 'cart-a', 1 ) );
		// Second concurrent checkout for the same last unit must fail.
		$this->assertFalse( $ledger->reserve( 'cart-b', 1 ) );
		$this->assertSame( 0, $ledger->available() );
	}

	public function test_commit_decrements_on_hand(): void {
		$ledger = new StockLedger( 5, $this->clock );
		$ledger->reserve( 'cart-a', 2 );
		$ledger->commit( 'cart-a' );
		$this->assertSame( 3, $ledger->on_hand() );
		$this->assertSame( 3, $ledger->available() );
	}

	public function test_release_returns_stock(): void {
		$ledger = new StockLedger( 5, $this->clock );
		$ledger->reserve( 'cart-a', 4 );
		$this->assertSame( 1, $ledger->available() );
		$ledger->release( 'cart-a' );
		$this->assertSame( 5, $ledger->available() );
	}

	public function test_expired_reservation_is_reclaimed(): void {
		$ledger = new StockLedger( 3, $this->clock );
		$ledger->reserve( 'cart-a', 3, 900 );
		$this->assertSame( 0, $ledger->available() );

		$this->clock->advance( 901 ); // Past the TTL.
		$this->assertSame( 3, $ledger->available(), 'Abandoned holds must free stock.' );
		// The reclaimed slot can be reserved again.
		$this->assertTrue( $ledger->reserve( 'cart-b', 3 ) );
	}

	public function test_committing_expired_reservation_throws(): void {
		$ledger = new StockLedger( 3, $this->clock );
		$ledger->reserve( 'cart-a', 1, 60 );
		$this->clock->advance( 61 );
		$this->expectException( StockException::class );
		$ledger->commit( 'cart-a' );
	}

	public function test_zero_or_negative_quantity_is_rejected(): void {
		$ledger = new StockLedger( 3, $this->clock );
		$this->expectException( StockException::class );
		$ledger->reserve( 'cart-a', 0 );
	}

	public function test_duplicate_reference_is_rejected(): void {
		$ledger = new StockLedger( 5, $this->clock );
		$ledger->reserve( 'cart-a', 1 );
		$this->expectException( StockException::class );
		$ledger->reserve( 'cart-a', 1 );
	}
}
