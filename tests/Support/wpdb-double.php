<?php
/**
 * Stand-in for WordPress' $wpdb, limited to what WpdbEventStore uses.
 *
 * The dedup store's correctness hinges on how it reads a failed INSERT, and the
 * two failures it must tell apart — a duplicate key and a broken table — look
 * identical from `insert()` alone. This double lets a test produce each one.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

if ( ! class_exists( 'wpdb' ) ) {

	/**
	 * Scriptable database double.
	 */
	// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital -- Mirrors WordPress' own class name.
	final class wpdb {

		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public string $prefix = 'wp_';

		/**
		 * Rows affected by the last write.
		 *
		 * @var int
		 */
		public int $rows_affected = 0;

		/**
		 * Last database error message.
		 *
		 * @var string
		 */
		public string $last_error = '';

		/**
		 * Event ids considered already stored.
		 *
		 * @var string[]
		 */
		public array $stored = array();

		/**
		 * When true, every insert fails and no row is ever readable —
		 * the missing-table case.
		 *
		 * @var bool
		 */
		public bool $broken = false;

		/**
		 * @return string
		 */
		public function get_charset_collate(): string {
			return '';
		}

		/**
		 * @param string $query Ignored.
		 *
		 * @return int
		 */
		public function query( string $query ): int {
			unset( $query );

			return 0;
		}

		/**
		 * Read back a claimed id. Fails closed when the store is broken.
		 *
		 * @param string $query Prepared SELECT carrying the event id.
		 *
		 * @return string|null
		 */
		public function get_var( string $query ) {
			if ( $this->broken ) {
				$this->last_error = "Table 'wp_rc_processed_events' doesn't exist";

				return null;
			}

			foreach ( $this->stored as $event_id ) {
				if ( str_contains( $query, $event_id ) ) {
					return '1';
				}
			}

			return null;
		}

		/**
		 * Echo the id into the query so get_var() can match on it.
		 *
		 * @param string $query Query with placeholders.
		 * @param mixed  ...$args Bound values.
		 *
		 * @return string
		 */
		public function prepare( string $query, ...$args ): string {
			return str_replace( '%s', (string) ( $args[0] ?? '' ), $query );
		}

		/**
		 * @param bool $suppress Whether to suppress errors.
		 *
		 * @return bool The previous setting.
		 */
		public function suppress_errors( bool $suppress = true ): bool {
			unset( $suppress );

			return false;
		}

		/**
		 * Insert, honouring the unique constraint on event_id.
		 *
		 * @param string               $table  Table name.
		 * @param array<string, mixed> $data   Row.
		 * @param string[]             $format Column formats.
		 *
		 * @return int|false Rows inserted, or false on failure.
		 */
		public function insert( string $table, array $data, array $format = array() ) {
			unset( $table, $format );

			$event_id = (string) ( $data['event_id'] ?? '' );

			if ( $this->broken ) {
				$this->rows_affected = 0;
				$this->last_error    = "Table 'wp_rc_processed_events' doesn't exist";

				return false;
			}

			if ( in_array( $event_id, $this->stored, true ) ) {
				$this->rows_affected = 0;
				$this->last_error    = "Duplicate entry '{$event_id}' for key 'event_id'";

				return false;
			}

			$this->stored[]      = $event_id;
			$this->rows_affected = 1;
			$this->last_error    = '';

			return 1;
		}
	}
}
