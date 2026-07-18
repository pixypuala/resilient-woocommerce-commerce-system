<?php
/**
 * Tests for the shipping-rate selector.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Shipping\ShippingCalculator;
use Pixypuala\ResilientCommerce\Shipping\ShippingException;
use Pixypuala\ResilientCommerce\Shipping\ShippingRate;

final class ShippingCalculatorTest extends TestCase {

	private ShippingCalculator $calculator;
	private ShippingRate $flat;
	private ShippingRate $free_over_threshold;

	protected function setUp(): void {
		$this->calculator          = new ShippingCalculator();
		$this->flat                = new ShippingRate( 'Flat', 500 );
		$this->free_over_threshold = new ShippingRate( 'Free', 0, 5000 );
	}

	public function test_below_threshold_selects_the_flat_rate(): void {
		$rate = $this->calculator->select( 4999, 0, $this->flat, $this->free_over_threshold );
		$this->assertSame( 'Flat', $rate->label() );
		$this->assertSame( 500, $rate->cost() );
	}

	public function test_at_free_shipping_threshold_selects_free(): void {
		// Threshold is inclusive: exactly 50.00 qualifies for free shipping.
		$rate = $this->calculator->select( 5000, 0, $this->flat, $this->free_over_threshold );
		$this->assertSame( 'Free', $rate->label() );
		$this->assertSame( 0, $rate->cost() );
	}

	public function test_selects_the_matching_weight_tier(): void {
		$light  = new ShippingRate( 'Light', 500, 0, 0, 1000 );
		$medium = new ShippingRate( 'Medium', 1000, 0, 1001, 5000 );
		$heavy  = new ShippingRate( 'Heavy', 2000, 0, 5001, null );

		// Boundaries: 1000 is still Light, 1001 crosses to Medium, 5000 is the
		// top of Medium, 5001 crosses to Heavy.
		$this->assertSame( 'Light', $this->calculator->select( 0, 1000, $light, $medium, $heavy )->label() );
		$this->assertSame( 'Medium', $this->calculator->select( 0, 1001, $light, $medium, $heavy )->label() );
		$this->assertSame( 'Medium', $this->calculator->select( 0, 5000, $light, $medium, $heavy )->label() );
		$this->assertSame( 'Heavy', $this->calculator->select( 0, 5001, $light, $medium, $heavy )->label() );
	}

	public function test_no_eligible_rate_fails_loudly(): void {
		$heavy_only = new ShippingRate( 'Heavy', 2000, 0, 5001, null );
		$this->expectException( ShippingException::class );
		$this->calculator->select( 0, 100, $heavy_only );
	}

	public function test_offering_no_rates_is_rejected(): void {
		$this->expectException( ShippingException::class );
		$this->calculator->select( 1000, 100 );
	}

	public function test_negative_cart_values_are_rejected(): void {
		$this->expectException( ShippingException::class );
		$this->calculator->select( -1, 0, $this->flat );
	}

	public function test_inverted_weight_bounds_are_rejected_at_construction(): void {
		$this->expectException( ShippingException::class );
		new ShippingRate( 'Broken', 500, 0, 5000, 1000 );
	}
}
