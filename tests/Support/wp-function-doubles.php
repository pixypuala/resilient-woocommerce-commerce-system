<?php
/**
 * The three WordPress functions the WooCommerce sync glue calls.
 *
 * Each is defined only when the real one is absent, and each reads or writes
 * WpDoubleState so tests can assert on what the glue actually did.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

use Pixypuala\ResilientCommerce\Tests\Support\WpDoubleState;

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted args.
	 */
	function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): void {
		unset( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook    Hook name.
	 * @param mixed  ...$args Hook arguments.
	 */
	function do_action( string $hook, ...$args ): void {
		WpDoubleState::$actions[] = array( $hook, $args );
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * @param int $id Order id.
	 *
	 * @return mixed The stand-in order, or false when unknown.
	 */
	function wc_get_order( int $id ) {
		return WpDoubleState::$orders[ $id ] ?? false;
	}
}
