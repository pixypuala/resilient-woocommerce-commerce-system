<?php
/**
 * The outcome of offering an event to the webhook inbox.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Why the inbox did (or did not) process an event.
 */
enum InboxResult: string {
	/** Signature valid, not seen before, within the replay window: handled. */
	case Accepted = 'accepted';
	/** Already processed; safely ignored (idempotent redelivery). */
	case Duplicate = 'duplicate';
	/** HMAC did not match: rejected, not processed. */
	case InvalidSignature = 'invalid_signature';
	/** Timestamp is outside the accepted replay window: rejected. */
	case Stale = 'stale';
	/** Required fields (id/timestamp) missing or malformed: rejected. */
	case Malformed = 'malformed';
	/** The dedup store gave no answer, so at-most-once cannot be guaranteed: retry. */
	case Unavailable = 'storage_unavailable';

	/**
	 * Whether this outcome means the handler ran.
	 *
	 * @return bool
	 */
	public function handled(): bool {
		return self::Accepted === $this;
	}
}
