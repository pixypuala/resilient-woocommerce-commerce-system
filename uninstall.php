<?php
/**
 * Uninstall routine.
 *
 * Activation creates a per-site dedup ledger table (`{prefix}rc_processed_events`).
 * Uninstalling removes it, on every site of a network as well as a single site,
 * so no orphaned table survives the plugin. The ledger holds only processed
 * webhook ids and their timestamps — it is the plugin's own bookkeeping, never
 * order, customer, or product data — so dropping it is the correct end state.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

// Only ever reachable through WordPress' uninstall path.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop the dedup ledger table for the site currently in scope.
 *
 * @return void
 */
function resilient_commerce_drop_event_store(): void {
	global $wpdb;

	/*
	 * The table name is an identifier, so it goes through the %i placeholder
	 * rather than string interpolation. Uninstall runs once against schema, so
	 * there is no object cache to consult or invalidate.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rc_processed_events' ) );
}

/**
 * Remove the plugin's tables from every site it could have installed one on.
 *
 * Wrapped in a function so no variable from this file leaks into the global
 * scope, where it could collide with another plugin's.
 *
 * @return void
 */
function resilient_commerce_uninstall(): void {
	if ( ! is_multisite() ) {
		resilient_commerce_drop_event_store();

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
		resilient_commerce_drop_event_store();
		restore_current_blog();
	}
}

resilient_commerce_uninstall();
