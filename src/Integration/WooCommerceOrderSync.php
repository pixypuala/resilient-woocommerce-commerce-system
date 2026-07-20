<?php
/**
 * Applies authenticated order webhooks to live WooCommerce orders.
 *
 * This is the thin glue between the framework-free domain and WooCommerce. It
 * subscribes to `resilient_commerce_webhook` (fired once per unique, verified
 * delivery by the inbox), resolves the body into a status change with the tested
 * WebhookStatusResolver, and applies it through the tested OrderStateMachine so
 * the same idempotency and illegal-transition guarantees hold against a real
 * `WC_Order`. WooCommerce is dependency-detected via `function_exists`; without
 * it, the handler is a safe no-op. Every decision here is unit-tested elsewhere;
 * only the `wc_get_order` load and `save()` are irreducible live-WooCommerce glue.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Integration;

use Pixypuala\ResilientCommerce\Order\OrderException;
use Pixypuala\ResilientCommerce\Order\OrderStateMachine;
use Pixypuala\ResilientCommerce\Order\OrderStatus;
use Pixypuala\ResilientCommerce\Order\WebhookStatusResolver;

/**
 * Binds resolved order-status changes onto WooCommerce orders.
 */
final class WooCommerceOrderSync {

	/**
	 * @param WebhookStatusResolver $resolver Framework-free payload → status-change mapper.
	 */
	public function __construct( private readonly WebhookStatusResolver $resolver ) {}

	/**
	 * Subscribe to the webhook action. Call during plugin bootstrap.
	 */
	public function register(): void {
		add_action( 'resilient_commerce_webhook', array( $this, 'handle' ), 10, 2 );
	}

	/**
	 * Handle one authenticated webhook delivery.
	 *
	 * @param string $body     Raw, already-verified request body.
	 * @param string $event_id Provider event id (unused here; dedup is upstream).
	 */
	public function handle( string $body, string $event_id ): void {
		unset( $event_id );

		// Dependency detection: without WooCommerce there is no order to update.
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		try {
			$change = $this->resolver->resolve( $body );
			if ( null === $change ) {
				return; // Not an order-status webhook.
			}

			// Irreducible live-WooCommerce glue: load, transition, persist.
			$order = wc_get_order( $change->order_id() );
			if ( ! $order instanceof \WC_Order ) {
				return;
			}

			$machine = new OrderStateMachine( OrderStatus::from_wc_status( (string) $order->get_status() ) );
			if ( $machine->transition_to( $change->target() ) ) {
				$order->set_status( $change->target()->value );
				$order->save();
			}
		} catch ( OrderException $error ) {
			/*
			 * The domain rejected an authenticated delivery: a stale redelivery that
			 * would move the order backwards, an unknown status, a malformed body.
			 * That rejection is correct and must not be swallowed — but it is a bad
			 * message, not a broken server, and letting it escape would fatal the
			 * REST request for a payload the provider can never make valid. So the
			 * order is left untouched and the rejection is re-raised where operators
			 * can see it: a dedicated action any logger or alerting hook can bind to.
			 */
			do_action( 'resilient_commerce_order_sync_failed', $body, $error->getMessage() );
		}
	}
}
