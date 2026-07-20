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

/**
 * Create the dedup table for the site currently in scope.
 *
 * @return void
 */
function install_event_store(): void {
	global $wpdb;
	( new WpdbEventStore( $wpdb ) )->install();
}

/*
 * Create the dedup table on activation.
 *
 * The table is per-site, because $wpdb->prefix is. On a network activation that
 * means every existing site needs one — otherwise the sub-sites answer webhooks
 * with no store behind them. Sites created afterwards are covered by the
 * `wp_initialize_site` hook below.
 */
register_activation_hook(
	__FILE__,
	static function ( bool $network_wide = false ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			install_event_store();

			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			install_event_store();
			restore_current_blog();
		}
	}
);

// A site added to the network later still needs its own dedup table.
add_action(
	'wp_initialize_site',
	static function ( \WP_Site $site ): void {
		// New-site creation does not run in an admin context, so the plugin
		// helpers this check needs are not loaded yet.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		install_event_store();
		restore_current_blog();
	},
	100
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
