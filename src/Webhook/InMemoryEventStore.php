<?php
/**
 * In-memory ProcessedEventStore for tests and single-process demos.
 *
 * Not for production concurrency (a process restart forgets everything), but it
 * models the atomic-claim contract correctly for unit tests.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Simple set-backed store.
 */
final class InMemoryEventStore implements ProcessedEventStore {

	/**
	 * @var array<string, true>
	 */
	private array $seen = array();

	public function has( string $event_id ): bool {
		return isset( $this->seen[ $event_id ] );
	}

	public function claim( string $event_id ): bool {
		if ( isset( $this->seen[ $event_id ] ) ) {
			return false;
		}
		$this->seen[ $event_id ] = true;
		return true;
	}
}
