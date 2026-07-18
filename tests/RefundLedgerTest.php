<?php
/**
 * Tests for the partial-refund validator.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Order\OrderException;
use Pixypuala\ResilientCommerce\Order\Refund;
use Pixypuala\ResilientCommerce\Order\RefundLedger;

final class RefundLedgerTest extends TestCase {

	public function test_partial_refund_reduces_the_remaining_balance(): void {
		$ledger = new RefundLedger( 10000 );
		$ledger->apply( new Refund( 3000 ) );
		$this->assertSame( 3000, $ledger->refunded() );
		$this->assertSame( 7000, $ledger->remaining() );
		$this->assertFalse( $ledger->is_fully_refunded() );
	}

	public function test_successive_partials_can_refund_the_full_amount(): void {
		$ledger = new RefundLedger( 10000 );
		$ledger->apply( new Refund( 3000 ) );
		$ledger->apply( new Refund( 7000 ) );
		$this->assertSame( 0, $ledger->remaining() );
		$this->assertTrue( $ledger->is_fully_refunded() );
	}

	public function test_refunding_exactly_the_captured_amount_is_allowed(): void {
		$ledger = new RefundLedger( 10000 );
		$ledger->apply( new Refund( 10000 ) );
		$this->assertTrue( $ledger->is_fully_refunded() );
	}

	public function test_refund_over_the_captured_amount_is_rejected(): void {
		$ledger = new RefundLedger( 10000 );
		$this->expectException( OrderException::class );
		$ledger->apply( new Refund( 10001 ) );
	}

	public function test_partial_then_over_remaining_is_rejected(): void {
		$ledger = new RefundLedger( 10000 );
		$ledger->apply( new Refund( 6000 ) );
		$this->expectException( OrderException::class );
		// Only 4000 remains; a further 5000 must be refused.
		$ledger->apply( new Refund( 5000 ) );
	}

	public function test_a_rejected_refund_does_not_change_the_balance(): void {
		$ledger = new RefundLedger( 10000 );
		$ledger->apply( new Refund( 6000 ) );
		try {
			$ledger->apply( new Refund( 5000 ) );
			$this->fail( 'Expected an over-refund to throw.' );
		} catch ( OrderException ) {
			$this->assertSame( 4000, $ledger->remaining(), 'A rejected refund must leave the balance intact.' );
		}
	}

	public function test_non_positive_refund_amount_is_rejected(): void {
		$this->expectException( OrderException::class );
		new Refund( 0 );
	}

	public function test_non_positive_captured_amount_is_rejected(): void {
		$this->expectException( OrderException::class );
		new RefundLedger( 0 );
	}
}
