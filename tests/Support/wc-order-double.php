<?php
/**
 * Stand-in for WooCommerce's WC_Order, limited to what the sync glue touches.
 *
 * Defined only when WooCommerce is absent, so the same tests keep working if
 * they are ever run inside a real WooCommerce environment.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

if ( ! class_exists( 'WC_Order' ) ) {

	/**
	 * Minimal order double: identity, status, and a recorded save.
	 */
	final class WC_Order {

		/**
		 * Times save() was called.
		 *
		 * @var int
		 */
		public int $saves = 0;

		/**
		 * @param int    $id     Order id.
		 * @param string $status Current WooCommerce status.
		 */
		public function __construct( private readonly int $id, private string $status ) {}

		/**
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * @return string
		 */
		public function get_status(): string {
			return $this->status;
		}

		/**
		 * @param string $status New status.
		 */
		public function set_status( string $status ): void {
			$this->status = $status;
		}

		/**
		 * Record a persist call.
		 */
		public function save(): void {
			++$this->saves;
		}
	}
}
