<?php
/**
 * Tests for the replay-safe, idempotent webhook inbox.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Tests\Support\MutableClock;
use Pixypuala\ResilientCommerce\Webhook\InboxResult;
use Pixypuala\ResilientCommerce\Webhook\InMemoryEventStore;
use Pixypuala\ResilientCommerce\Webhook\SignatureVerifier;
use Pixypuala\ResilientCommerce\Webhook\WebhookInbox;

final class WebhookInboxTest extends TestCase {

	private const SECRET = 'shh-super-secret';

	private MutableClock $clock;
	private SignatureVerifier $verifier;
	private InMemoryEventStore $store;
	private WebhookInbox $inbox;

	protected function setUp(): void {
		$this->clock    = new MutableClock();
		$this->verifier = new SignatureVerifier( self::SECRET );
		$this->store    = new InMemoryEventStore();
		$this->inbox    = new WebhookInbox( $this->verifier, $this->store, 300, $this->clock );
	}

	private function sign( string $body ): string {
		return hash_hmac( 'sha256', $body, self::SECRET );
	}

	public function test_valid_event_is_accepted_and_handler_runs_once(): void {
		$body   = '{"order":123}';
		$calls  = 0;
		$result = $this->inbox->receive(
			$body,
			$this->sign( $body ),
			'evt_1',
			$this->clock->now(),
			function () use ( &$calls ): void {
				++$calls;
			}
		);

		$this->assertSame( InboxResult::Accepted, $result );
		$this->assertTrue( $result->handled() );
		$this->assertSame( 1, $calls );
	}

	public function test_duplicate_delivery_does_not_run_handler_again(): void {
		$body    = '{"order":123}';
		$run     = 0;
		$handler = function () use ( &$run ): void {
			++$run;
		};

		$first  = $this->inbox->receive( $body, $this->sign( $body ), 'evt_1', $this->clock->now(), $handler );
		$second = $this->inbox->receive( $body, $this->sign( $body ), 'evt_1', $this->clock->now(), $handler );

		$this->assertSame( InboxResult::Accepted, $first );
		$this->assertSame( InboxResult::Duplicate, $second );
		$this->assertSame( 1, $run, 'A duplicate refund/order webhook must never run twice.' );
	}

	public function test_forged_signature_is_rejected(): void {
		$body   = '{"order":123}';
		$result = $this->inbox->receive( $body, 'deadbeef', 'evt_1', $this->clock->now(), fn () => null );
		$this->assertSame( InboxResult::InvalidSignature, $result );
	}

	public function test_tampered_body_is_rejected(): void {
		$signature = $this->sign( '{"order":123}' );
		$result    = $this->inbox->receive( '{"order":999}', $signature, 'evt_1', $this->clock->now(), fn () => null );
		$this->assertSame( InboxResult::InvalidSignature, $result );
	}

	public function test_stale_event_outside_replay_window_is_rejected(): void {
		$body   = '{"order":123}';
		$old    = $this->clock->now() - 3600;
		$result = $this->inbox->receive( $body, $this->sign( $body ), 'evt_1', $old, fn () => null );
		$this->assertSame( InboxResult::Stale, $result );
	}

	public function test_missing_event_id_is_malformed(): void {
		$body   = '{"order":123}';
		$result = $this->inbox->receive( $body, $this->sign( $body ), '', $this->clock->now(), fn () => null );
		$this->assertSame( InboxResult::Malformed, $result );
	}
}
