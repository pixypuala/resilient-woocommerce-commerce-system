<?php
/**
 * A replay-safe, idempotent webhook inbox.
 *
 * Every inbound webhook passes the same gauntlet before any side effect runs:
 *   1. Signature must verify (authenticity).
 *   2. Required fields (event id, timestamp) must be present (well-formed).
 *   3. Timestamp must be within the replay window (freshness / anti-replay).
 *   4. The event id must not have been processed before (idempotency).
 * Only then is the caller's handler invoked, and the id recorded so a
 * redelivery becomes a no-op. This is what stops duplicate refunds and
 * double-fulfilled orders.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

use Pixypuala\ResilientCommerce\Clock\Clock;
use Pixypuala\ResilientCommerce\Clock\SystemClock;

/**
 * Guards side effects behind authenticity, freshness, and idempotency checks.
 */
final class WebhookInbox {

	/**
	 * @param SignatureVerifier   $verifier         Authenticates the raw body.
	 * @param ProcessedEventStore $store            Remembers processed ids.
	 * @param int                 $replay_window    Max age in seconds for an event.
	 * @param Clock               $clock            Time source (defaults to system).
	 */
	public function __construct(
		private readonly SignatureVerifier $verifier,
		private readonly ProcessedEventStore $store,
		private readonly int $replay_window = 300,
		private readonly Clock $clock = new SystemClock(),
	) {}

	/**
	 * Offer a delivery to the inbox.
	 *
	 * @param string   $raw_body  Exact raw request body.
	 * @param string   $signature Signature header value.
	 * @param string   $event_id  Provider-unique event id.
	 * @param int      $timestamp Event creation time (Unix seconds).
	 * @param callable $handler   Side-effect to run once, iff the event is Accepted.
	 *                            Signature: fn(string $raw_body): void.
	 *
	 * @return InboxResult What happened. The handler runs only for Accepted.
	 */
	public function receive( string $raw_body, string $signature, string $event_id, int $timestamp, callable $handler ): InboxResult {
		// 1. Authenticity — reject forgeries before anything else.
		if ( ! $this->verifier->verify( $raw_body, $signature ) ) {
			return InboxResult::InvalidSignature;
		}

		// 2. Well-formed — an event without an id cannot be deduplicated.
		if ( '' === $event_id || $timestamp <= 0 ) {
			return InboxResult::Malformed;
		}

		// 3. Freshness — reject replays of old, captured deliveries.
		$age = $this->clock->now() - $timestamp;
		if ( $age > $this->replay_window || $age < -$this->replay_window ) {
			return InboxResult::Stale;
		}

		// 4. Idempotency — atomically claim the id; a lost claim is a duplicate.
		if ( ! $this->store->claim( $event_id ) ) {
			return InboxResult::Duplicate;
		}

		// All gates passed exactly once: run the side effect.
		$handler( $raw_body );

		return InboxResult::Accepted;
	}
}
