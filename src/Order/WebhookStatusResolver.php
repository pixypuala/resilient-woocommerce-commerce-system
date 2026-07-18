<?php
/**
 * Maps a raw order-webhook body to an order-status change, framework-free.
 *
 * This is the decision logic extracted out of the live WooCommerce adapter so it
 * can be unit-tested without WooCommerce: it decodes the signed JSON body the
 * inbox has already authenticated and decides whether it carries an order-status
 * change and, if so, for which order and target status. A body that is simply a
 * different kind of event yields `null` (skip); a body that claims to be an
 * order event but is malformed fails loudly, so bad data can never be applied.
 *
 * Expected payload shape:
 *   { "order": { "id": <positive int>, "status": "<wc status>" }, ... }
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Order;

/**
 * Resolves order webhooks into status-change intents.
 */
final class WebhookStatusResolver {

	/**
	 * Resolve a webhook body into a status change, or null when it is not one.
	 *
	 * @param string $raw_body The exact JSON body the inbox authenticated.
	 *
	 * @return OrderStatusChange|null Null when the event is not an order-status change.
	 *
	 * @throws OrderException When the body is not JSON, or is an order event with a
	 *                        malformed id or an unknown status.
	 */
	public function resolve( string $raw_body ): ?OrderStatusChange {
		$data = json_decode( $raw_body, true );
		if ( ! is_array( $data ) ) {
			throw new OrderException( 'Webhook payload is not a JSON object.' );
		}

		$order = $data['order'] ?? null;
		if ( ! is_array( $order ) || ! isset( $order['id'], $order['status'] ) ) {
			// A well-formed body that simply is not an order-status event.
			return null;
		}

		$id = $order['id'];
		if ( ! is_int( $id ) || $id <= 0 ) {
			throw new OrderException( 'Webhook order id must be a positive integer.' );
		}

		// from_wc_status throws OrderException on an unknown status.
		$status = OrderStatus::from_wc_status( (string) $order['status'] );

		return new OrderStatusChange( $id, $status );
	}
}
