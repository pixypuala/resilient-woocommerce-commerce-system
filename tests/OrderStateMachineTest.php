<?php
/**
 * Tests for the idempotent order-state machine.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Order\OrderException;
use Pixypuala\ResilientCommerce\Order\OrderStateMachine;
use Pixypuala\ResilientCommerce\Order\OrderStatus;

final class OrderStateMachineTest extends TestCase {

	public function test_valid_transition_changes_status(): void {
		$order = new OrderStateMachine( OrderStatus::Pending );
		$this->assertTrue( $order->transition_to( OrderStatus::Processing ) );
		$this->assertSame( OrderStatus::Processing, $order->status() );
	}

	public function test_redelivered_status_is_idempotent_noop(): void {
		$order = new OrderStateMachine( OrderStatus::Processing );
		// A duplicate webhook reporting the current status must not throw and must
		// report "no change" rather than corrupting state.
		$this->assertFalse( $order->transition_to( OrderStatus::Processing ) );
		$this->assertSame( OrderStatus::Processing, $order->status() );
	}

	public function test_illegal_transition_is_rejected(): void {
		$order = new OrderStateMachine( OrderStatus::Completed );
		$this->expectException( OrderException::class );
		// Completed may only be refunded, never sent back to processing.
		$order->transition_to( OrderStatus::Processing );
	}

	public function test_terminal_statuses_allow_no_transition(): void {
		$this->assertTrue( OrderStatus::Cancelled->is_terminal() );
		$this->assertTrue( OrderStatus::Refunded->is_terminal() );
		$this->assertFalse( OrderStatus::Processing->is_terminal() );
	}

	public function test_apply_wc_status_tolerates_prefix(): void {
		$order = new OrderStateMachine( OrderStatus::Pending );
		$this->assertTrue( $order->apply_wc_status( 'wc-processing' ) );
		$this->assertTrue( $order->apply_wc_status( 'completed' ) );
		$this->assertSame( OrderStatus::Completed, $order->status() );
	}

	public function test_unknown_wc_status_is_rejected(): void {
		$order = new OrderStateMachine( OrderStatus::Pending );
		$this->expectException( OrderException::class );
		$order->apply_wc_status( 'wc-quantum' );
	}

	public function test_completed_can_only_be_refunded(): void {
		$order = new OrderStateMachine( OrderStatus::Completed );
		$this->assertTrue( $order->transition_to( OrderStatus::Refunded ) );
		$this->assertTrue( $order->status()->is_terminal() );
	}
}
