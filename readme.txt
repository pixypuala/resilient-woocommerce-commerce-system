=== Resilient Commerce for WooCommerce ===
Contributors: pixypuala
Tags: webhooks, idempotency, orders, stock, integration
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An idempotent, replay-safe webhook inbox and oversell-safe stock reservations for revenue-critical store workflows.

== Description ==

Payment providers, fulfilment services, and marketplaces all retry. A retried
webhook that is processed twice refunds twice, ships twice, or decrements stock
twice. Resilient Commerce puts a signed, deduplicating inbox in front of those
deliveries so each event takes effect exactly once, no matter how often it
arrives.

Every inbound request must carry a valid HMAC signature over its raw body, and a
timestamp inside the freshness window. Signature comparison is constant-time,
so a rejected request leaks nothing about the secret. Events outside the window
are refused as stale, which closes the replay path even for a signature that was
once genuine.

Deduplication is a database claim, not a read-then-write: the event id is
inserted into a unique-keyed ledger table, and the insert itself decides the
winner. Two concurrent deliveries of the same event cannot both proceed.

If the ledger is unreachable the endpoint does not guess. It answers 503 without
processing and without acknowledging, so the provider keeps the event in its
retry queue rather than the store silently dropping it.

= Endpoint =

`POST /wp-json/resilient-commerce/v1/webhook`

Headers:

* `X-Rc-Signature` — HMAC of the raw body, using the configured secret.
* `X-Rc-Event-Id` — the provider's unique event id.
* `X-Rc-Timestamp` — Unix timestamp of the delivery.

Responses: 200 accepted or duplicate, 400 malformed, 401 bad signature,
408 stale, 503 ledger unavailable.

= Configuration =

Define the signing secret in `wp-config.php`:

`define( 'RESILIENT_COMMERCE_WEBHOOK_SECRET', 'your-shared-secret' );`

Without it the route is never registered. The plugin fails safe rather than
accepting unauthenticated webhooks.

= Acting on events =

Each unique, authenticated webhook fires one action:

`add_action( 'resilient_commerce_webhook', function ( string $body, string $event_id ) {
	// Guaranteed at most once per event id.
}, 10, 2 );`

Order webhooks are additionally applied to live WooCommerce orders through a
status resolver that refuses invalid transitions, so a late-arriving webhook
cannot walk an order backwards.

== Frequently Asked Questions ==

= Does uninstalling delete anything? =

Yes. Uninstall drops the `rc_processed_events` table on every site, because a
dedup ledger that outlives the plugin is dead weight that would also let old
event ids block a future reinstall. The table holds only processed webhook ids
and timestamps — never order, customer, or product data. Deactivating the plugin
does not touch it; only a full uninstall does.

= Is WooCommerce required? =

The webhook inbox works without it. The order sync layer detects WooCommerce and
is a no-op when it is not loaded, so nothing fatals on a store that has it
deactivated.

= What happens if the same event arrives twice? =

The second delivery is answered 200 as a duplicate and no side effect runs. A
200 tells the provider to stop retrying, which is the correct answer once the
event has already been applied.

= Does it work on multisite? =

Yes. The ledger is per-site. Network activation creates it for every existing
site, and sites created later get one on creation.

== Screenshots ==

1. The webhook inbox. The four gates every delivery passes — signature, well-formedness, freshness, and the unique-key claim — beside the full outcome table: which HTTP status each result returns and whether the handler ran.
2. The order state machine. Every transition the WooCommerce-aligned lifecycle permits, the two terminal states that permit none, and the illegal transitions that raise an OrderException instead of corrupting the order.

== Changelog ==

= 0.1.0 =
* Initial release: signed, replay-safe, deduplicating webhook inbox; WooCommerce
  order status sync with invalid-transition rejection; stock, tax, shipping, and
  refund ledgers.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
