<?php
/**
 * Plugin Name:       Resilient Commerce
 * Plugin URI:        https://github.com/pixypuala/resilient-woocommerce-commerce-system
 * Description:        Protects revenue-critical WooCommerce workflows: an idempotent, replay-safe webhook inbox and oversell-safe stock reservations.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Pixypuala
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       resilient-commerce
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce;

use Pixypuala\ResilientCommerce\Http\WebhookController;
use Pixypuala\ResilientCommerce\Integration\WooCommerceOrderSync;
use Pixypuala\ResilientCommerce\Order\WebhookStatusResolver;
use Pixypuala\ResilientCommerce\Webhook\SignatureVerifier;
use Pixypuala\ResilientCommerce\Webhook\WebhookInbox;
use Pixypuala\ResilientCommerce\Webhook\WpdbEventStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefer Composer's autoloader; fall back to a minimal PSR-4 loader.
$autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'Pixypuala\\ResilientCommerce\\';
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}
			$path = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * The signing secret. Sourced from a constant so it is never hard-coded; the
 * endpoint refuses to register without it, failing safe rather than accepting
 * unauthenticated webhooks.
 */
function signing_secret(): string {
	return defined( 'RESILIENT_COMMERCE_WEBHOOK_SECRET' ) ? (string) RESILIENT_COMMERCE_WEBHOOK_SECRET : '';
}

// Create the dedup table on activation.
register_activation_hook(
	__FILE__,
	static function (): void {
		global $wpdb;
		( new WpdbEventStore( $wpdb ) )->install();
	}
);

// Wire the webhook route, but only when a secret is configured.
add_action(
	'rest_api_init',
	static function (): void {
		$secret = signing_secret();
		if ( '' === $secret ) {
			return; // Fail safe: no secret, no endpoint.
		}
		global $wpdb;
		$inbox = new WebhookInbox( new SignatureVerifier( $secret ), new WpdbEventStore( $wpdb ) );
		( new WebhookController( $inbox ) )->register_routes();

		// Apply authenticated order webhooks to live WooCommerce orders. The sync
		// is dependency-detected and a no-op when WooCommerce is not loaded.
		( new WooCommerceOrderSync( new WebhookStatusResolver() ) )->register();
	}
);
