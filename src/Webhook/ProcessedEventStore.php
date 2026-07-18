<?php
/**
 * Storage boundary for webhook deduplication.
 *
 * The inbox needs to remember which event IDs it has already handled so a
 * duplicate delivery is a no-op. This interface abstracts that memory so the
 * inbox logic can be tested with an in-memory store and run in production
 * against the database.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Records and queries processed event IDs.
 */
interface ProcessedEventStore {

	/**
	 * Whether an event ID has already been processed.
	 *
	 * @param string $event_id Provider-unique event identifier.
	 *
	 * @return bool
	 */
	public function has( string $event_id ): bool;

	/**
	 * Atomically claim an event ID for processing.
	 *
	 * Returns true if the caller won the claim (first to see it), false if the
	 * ID was already claimed. Implementations MUST make this atomic (e.g. an
	 * INSERT on a UNIQUE column) so two concurrent deliveries cannot both win.
	 *
	 * @param string $event_id Provider-unique event identifier.
	 *
	 * @return bool True when the claim was won.
	 */
	public function claim( string $event_id ): bool;
}
