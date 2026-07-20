<?php
/**
 * Covers how the dedup store reads a failed INSERT.
 *
 * The whole replay guarantee rests on one distinction: an INSERT that fails
 * because the id was already claimed means "duplicate, drop it", while an INSERT
 * that fails because the storage is broken means nothing at all — and must never
 * be reported as a duplicate. Conflating them turns a missing table into the
 * silent, permanent loss of every incoming webhook.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Webhook\EventStoreUnavailable;
use Pixypuala\ResilientCommerce\Webhook\WpdbEventStore;

require_once __DIR__ . '/Support/wpdb-double.php';

final class WpdbEventStoreTest extends TestCase {

	private \wpdb $wpdb;

	private WpdbEventStore $store;

	protected function setUp(): void {
		$this->wpdb  = new \wpdb();
		$this->store = new WpdbEventStore( $this->wpdb );
	}

	public function test_first_claim_of_an_id_wins(): void {
		$this->assertTrue( $this->store->claim( 'evt-1' ) );
	}

	public function test_second_claim_of_the_same_id_loses(): void {
		$this->store->claim( 'evt-1' );

		$this->assertFalse( $this->store->claim( 'evt-1' ), 'A redelivery must lose the claim.' );
	}

	public function test_distinct_ids_both_win(): void {
		$this->assertTrue( $this->store->claim( 'evt-1' ) );
		$this->assertTrue( $this->store->claim( 'evt-2' ) );
	}

	/**
	 * The defect this test exists for: with no table, the store used to report
	 * every delivery as a duplicate, so nothing was ever processed and nothing
	 * ever complained.
	 */
	public function test_broken_storage_is_not_reported_as_a_duplicate(): void {
		$this->wpdb->broken = true;

		$this->expectException( EventStoreUnavailable::class );
		$this->store->claim( 'evt-1' );
	}

	public function test_has_reflects_stored_ids(): void {
		$this->store->claim( 'evt-1' );

		$this->assertTrue( $this->store->has( 'evt-1' ) );
		$this->assertFalse( $this->store->has( 'evt-2' ) );
	}
}
