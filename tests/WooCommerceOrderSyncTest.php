<?php
/**
 * Covers the WooCommerce glue: what a real, authenticated delivery does to a real order.
 *
 * The domain rules are tested elsewhere. What matters here is the boundary
 * behaviour: an authenticated payload the domain rejects — a stale redelivery
 * that would move an order backwards, an unknown status, malformed JSON — must
 * leave the order untouched and surface the failure as an observable event, not
 * as an uncaught exception that fatals the whole REST request.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Integration\WooCommerceOrderSync;
use Pixypuala\ResilientCommerce\Order\WebhookStatusResolver;
use Pixypuala\ResilientCommerce\Tests\Support\WpDoubleState;

require_once __DIR__ . '/Support/WpDoubleState.php';

/**
 * Boundary tests for the order-sync glue.
 */
final class WooCommerceOrderSyncTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var WooCommerceOrderSync
	 */
	private WooCommerceOrderSync $sync;

	protected function setUp(): void {
		WpDoubleState::reset();
		$this->sync = new WooCommerceOrderSync( new WebhookStatusResolver() );
	}

	/**
	 * A legal transition is applied and persisted.
	 */
	public function test_applies_a_legal_status_change(): void {
		$order = WpDoubleState::add_order( 17, 'pending' );

		$this->sync->handle( '{"order":{"id":17,"status":"processing"}}', 'evt-1' );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 1, $order->saves );
	}

	/**
	 * A redelivery of the status the order already holds persists nothing.
	 */
	public function test_redelivery_of_the_current_status_is_a_no_op(): void {
		$order = WpDoubleState::add_order( 17, 'processing' );

		$this->sync->handle( '{"order":{"id":17,"status":"processing"}}', 'evt-2' );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 0, $order->saves, 'An idempotent redelivery must not write.' );
	}

	/**
	 * A stale delivery that would move the order backwards must not escape as a
	 * fatal: the order stays put and a failure event is fired instead.
	 */
	public function test_illegal_transition_does_not_escape_as_an_exception(): void {
		$order = WpDoubleState::add_order( 17, 'completed' );

		$this->sync->handle( '{"order":{"id":17,"status":"pending"}}', 'evt-3' );

		$this->assertSame( 'completed', $order->get_status(), 'Illegal transition must not mutate the order.' );
		$this->assertSame( 0, $order->saves );
		$this->assertContains( 'resilient_commerce_order_sync_failed', WpDoubleState::fired_hooks() );
	}

	/**
	 * An unknown status is a domain rejection, not a crash.
	 */
	public function test_unknown_status_is_reported_not_thrown(): void {
		$order = WpDoubleState::add_order( 17, 'pending' );

		$this->sync->handle( '{"order":{"id":17,"status":"teleported"}}', 'evt-4' );

		$this->assertSame( 'pending', $order->get_status() );
		$this->assertContains( 'resilient_commerce_order_sync_failed', WpDoubleState::fired_hooks() );
	}

	/**
	 * A non-JSON body is rejected the same way.
	 */
	public function test_malformed_body_is_reported_not_thrown(): void {
		WpDoubleState::add_order( 17, 'pending' );

		$this->sync->handle( 'not json at all', 'evt-5' );

		$this->assertContains( 'resilient_commerce_order_sync_failed', WpDoubleState::fired_hooks() );
	}

	/**
	 * A body that is not an order event does nothing at all — no writes, no noise.
	 */
	public function test_non_order_event_is_ignored_silently(): void {
		$order = WpDoubleState::add_order( 17, 'pending' );

		$this->sync->handle( '{"topic":"customer.updated"}', 'evt-6' );

		$this->assertSame( 'pending', $order->get_status() );
		$this->assertSame( 0, $order->saves );
		$this->assertSame( array(), WpDoubleState::fired_hooks() );
	}

	/**
	 * A webhook for an order this store does not have is ignored.
	 */
	public function test_unknown_order_is_ignored(): void {
		$this->sync->handle( '{"order":{"id":404,"status":"processing"}}', 'evt-7' );

		$this->assertSame( array(), WpDoubleState::fired_hooks() );
	}
}
