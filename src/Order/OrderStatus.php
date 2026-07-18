<?php
/**
 * The lifecycle states a commerce order can occupy.
 *
 * These mirror the WooCommerce core statuses (without the `wc-` storage prefix)
 * so the framework-free state machine can be driven directly from order webhooks
 * while staying testable without WooCommerce loaded.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * A WooCommerce-aligned order status.
 */
enum OrderStatus: string {
	case Pending    = 'pending';
	case OnHold     = 'on-hold';
	case Processing = 'processing';
	case Completed  = 'completed';
	case Cancelled  = 'cancelled';
	case Refunded   = 'refunded';
	case Failed     = 'failed';

	/**
	 * Build a status from a WooCommerce status string, tolerating the `wc-` prefix.
	 *
	 * @param string $wc_status Status as WooCommerce stores or reports it.
	 *
	 * @return self
	 *
	 * @throws OrderException When the status is not a recognised order status.
	 */
	public static function from_wc_status( string $wc_status ): self {
		$normalized = 0 === strncmp( $wc_status, 'wc-', 3 ) ? substr( $wc_status, 3 ) : $wc_status;
		$status     = self::tryFrom( $normalized );
		if ( null === $status ) {
			throw new OrderException( sprintf( 'Unknown order status "%s".', $wc_status ) );
		}
		return $status;
	}

	/**
	 * Whether this status is terminal (no further transitions are allowed).
	 *
	 * @return bool
	 */
	public function is_terminal(): bool {
		return array() === self::transitions()[ $this->value ];
	}

	/**
	 * The allowed target statuses for each status.
	 *
	 * Deliberately conservative: only transitions that are safe to apply from an
	 * asynchronous, possibly out-of-order webhook are permitted. Terminal states
	 * (cancelled, refunded) allow no onward transition.
	 *
	 * @return array<string, list<self>>
	 */
	private static function transitions(): array {
		return array(
			self::Pending->value    => array( self::OnHold, self::Processing, self::Cancelled, self::Failed ),
			self::OnHold->value     => array( self::Processing, self::Cancelled, self::Failed ),
			self::Processing->value => array( self::Completed, self::Cancelled, self::Refunded, self::Failed ),
			self::Completed->value  => array( self::Refunded ),
			self::Failed->value     => array( self::Pending, self::Cancelled ),
			self::Cancelled->value  => array(),
			self::Refunded->value   => array(),
		);
	}

	/**
	 * Whether a direct transition from this status to $target is allowed.
	 *
	 * @param self $target Desired status.
	 *
	 * @return bool
	 */
	public function can_transition_to( self $target ): bool {
		return in_array( $target, self::transitions()[ $this->value ], true );
	}
}
