<?php
/**
 * REST controller that funnels inbound webhooks through the inbox.
 *
 * This is the thin WordPress adapter over the framework-free WebhookInbox: it
 * extracts the raw body, signature header, event id, and timestamp from the
 * request, hands them to the inbox, and translates the InboxResult into an HTTP
 * status. All the security-critical logic (verify, dedup, replay) lives in the
 * tested domain, not here.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Http;

use Pixypuala\ResilientCommerce\Webhook\EventStoreUnavailable;
use Pixypuala\ResilientCommerce\Webhook\InboxResult;
use Pixypuala\ResilientCommerce\Webhook\WebhookInbox;

/**
 * Registers and handles the webhook REST route.
 */
final class WebhookController {

	/**
	 * @param WebhookInbox $inbox           The domain inbox.
	 * @param string       $signature_header Header carrying the signature.
	 */
	public function __construct(
		private readonly WebhookInbox $inbox,
		private readonly string $signature_header = 'X-Rc-Signature',
	) {}

	/**
	 * Register the REST route. Hook to `rest_api_init`.
	 */
	public function register_routes(): void {
		register_rest_route(
			'resilient-commerce/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				// Authentication is the HMAC signature, verified in the inbox, so
				// the endpoint itself is intentionally publicly reachable.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a webhook POST.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$raw_body  = $request->get_body();
		$signature = (string) $request->get_header( $this->signature_header );
		$event_id  = (string) $request->get_header( 'X-Rc-Event-Id' );
		$timestamp = (int) $request->get_header( 'X-Rc-Timestamp' );

		try {
			$result = $this->inbox->receive(
				$raw_body,
				$signature,
				$event_id,
				$timestamp,
				function ( string $body ) use ( $event_id ): void {
					/**
					 * Fires once per unique, authenticated webhook. Order/refund
					 * side effects subscribe here; the inbox guarantees at-most-once.
					 *
					 * @param string $body     Raw request body.
					 * @param string $event_id Provider event id.
					 */
					do_action( 'resilient_commerce_webhook', $body, $event_id );
				}
			);
		} catch ( EventStoreUnavailable $error ) {
			/*
			 * Without the dedup store there is no at-most-once guarantee, so the
			 * event must not be processed and must not be acknowledged either.
			 * A 503 keeps it in the provider's retry queue instead of dropping it.
			 */
			do_action( 'resilient_commerce_store_unavailable', $event_id, $error->getMessage() );

			$result = InboxResult::Unavailable;
		}

		return new \WP_REST_Response(
			array( 'result' => $result->value ),
			$this->status_for( $result )
		);
	}

	/**
	 * Map an inbox result to an HTTP status code.
	 *
	 * @param InboxResult $result Domain outcome.
	 *
	 * @return int HTTP status.
	 */
	private function status_for( InboxResult $result ): int {
		return match ( $result ) {
			InboxResult::Accepted         => 200,
			InboxResult::Duplicate        => 200, // Idempotent: acknowledge, do not retry.
			InboxResult::InvalidSignature => 401,
			InboxResult::Stale            => 408,
			InboxResult::Malformed        => 400,
			// 503 asks the provider to retry: the event may never have been seen.
			InboxResult::Unavailable      => 503,
		};
	}
}
