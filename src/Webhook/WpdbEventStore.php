<?php
/**
 * Database-backed ProcessedEventStore using WordPress' $wpdb.
 *
 * Deduplication must survive process restarts and be safe under concurrent
 * deliveries. Both are achieved with a UNIQUE column on the event id: the claim
 * is a single INSERT, and the database — not PHP — arbitrates the race. If the
 * insert hits the unique constraint, the caller lost the claim (a duplicate).
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Persists processed webhook event ids.
 */
final class WpdbEventStore implements ProcessedEventStore {

	private string $table;

	/**
	 * @param \wpdb $wpdb WordPress database handle.
	 */
	public function __construct( private readonly \wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'rc_processed_events';
	}

	/**
	 * Create the dedup table. Called on plugin activation.
	 *
	 * dbDelta is intentionally not used here to keep the schema explicit; the
	 * UNIQUE key on event_id is the correctness-critical part.
	 */
	public function install(): void {
		$charset = $this->wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(191) NOT NULL,
			processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY event_id (event_id)
		) {$charset};";

		// $sql contains only a constant DDL string plus the trusted, prefixed
		// table name — no user input. Identifiers cannot be bound placeholders.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( $sql );
	}

	public function has( string $event_id ): bool {
		// The interpolated value is the trusted, prefixed table name (an SQL
		// identifier, which cannot be a bound placeholder); $event_id is bound.
		$prepared = $this->wpdb->prepare( "SELECT 1 FROM {$this->table} WHERE event_id = %s", $event_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// $prepared is already a prepared statement (see line above).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $this->wpdb->get_var( $prepared );
		return null !== $found;
	}

	/**
	 * Atomically claim an event id via a UNIQUE-constrained INSERT.
	 *
	 * @param string $event_id Provider-unique event id.
	 *
	 * @return bool True when this caller won the claim.
	 *
	 * @throws EventStoreUnavailable When the insert failed for any reason other
	 *                               than the id already being claimed.
	 */
	public function claim( string $event_id ): bool {
		// Suppress the expected duplicate-key warning; the failure is inspected below.
		$previous = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $this->wpdb->insert( $this->table, array( 'event_id' => $event_id ), array( '%s' ) );
		$this->wpdb->suppress_errors( $previous );

		if ( false !== $inserted && $this->wpdb->rows_affected > 0 ) {
			return true;
		}

		/*
		 * A failed insert has two very different meanings. If the id is now
		 * readable, the unique constraint did its job and this is a duplicate.
		 * If it is not, the write did not happen — a missing table, a lost
		 * connection — and reporting that as a duplicate would silently discard
		 * every delivery forever. Reading back costs one query on a path that is
		 * rare by construction, and it never guesses from an error string.
		 */
		if ( $this->has( $event_id ) ) {
			return false;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-free domain: the controller catches this and forwards the detail to an operator-only action; the 503 response body never carries it.
		throw new EventStoreUnavailable(
			sprintf(
				'Could not claim event "%s" in %s: %s',
				$event_id,
				$this->table,
				'' !== $this->wpdb->last_error ? $this->wpdb->last_error : 'the row was not written and is not present.'
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
