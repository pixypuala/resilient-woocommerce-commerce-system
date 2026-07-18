<?php
/**
 * Tests for the deterministic tax calculator.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Tax\TaxCalculator;
use Pixypuala\ResilientCommerce\Tax\TaxException;
use Pixypuala\ResilientCommerce\Tax\TaxRate;

final class TaxCalculatorTest extends TestCase {

	private TaxCalculator $calculator;

	protected function setUp(): void {
		$this->calculator = new TaxCalculator();
	}

	public function test_sums_line_items_before_applying_rate(): void {
		// 60.00 + 40.00 = 100.00 base; 5% = 5.00.
		$rate      = new TaxRate( 'VAT', 500 );
		$breakdown = $this->calculator->calculate( array( 6000, 4000 ), $rate );
		$this->assertSame( 500, $breakdown->total() );
		$this->assertSame( 500, $breakdown->for_rate( 'VAT' ) );
	}

	public function test_rounds_half_up_to_the_minor_unit(): void {
		// 12.5% of 1.00 = 0.125 → 0.13 (half up), i.e. 13 minor units.
		$breakdown = $this->calculator->calculate( array( 100 ), new TaxRate( 'GST', 1250 ) );
		$this->assertSame( 13, $breakdown->total() );
	}

	public function test_zero_rate_yields_zero_tax(): void {
		$breakdown = $this->calculator->calculate( array( 9999 ), new TaxRate( 'Exempt', 0 ) );
		$this->assertSame( 0, $breakdown->total() );
		$this->assertSame( 0, $breakdown->for_rate( 'Exempt' ) );
	}

	public function test_compound_rate_applies_on_base_plus_non_compound_tax(): void {
		// Base 100.00. GST 5% = 5.00 (non-compound). PST 7% compound on 105.00 = 7.35.
		$gst       = new TaxRate( 'GST', 500 );
		$pst       = new TaxRate( 'PST', 700, true );
		$breakdown = $this->calculator->calculate( array( 10000 ), $gst, $pst );
		$this->assertSame( 500, $breakdown->for_rate( 'GST' ) );
		$this->assertSame( 735, $breakdown->for_rate( 'PST' ) );
		$this->assertSame( 1235, $breakdown->total() );
	}

	public function test_compound_result_is_independent_of_rate_order(): void {
		$gst = new TaxRate( 'GST', 500 );
		$pst = new TaxRate( 'PST', 700, true );
		$one = $this->calculator->calculate( array( 10000 ), $gst, $pst )->total();
		$two = $this->calculator->calculate( array( 10000 ), $pst, $gst )->total();
		$this->assertSame( $one, $two );
	}

	public function test_negative_line_amount_is_rejected(): void {
		$this->expectException( TaxException::class );
		$this->calculator->calculate( array( 100, -1 ), new TaxRate( 'VAT', 500 ) );
	}

	public function test_negative_rate_is_rejected_at_construction(): void {
		$this->expectException( TaxException::class );
		new TaxRate( 'Bad', -1 );
	}

	public function test_duplicate_rate_label_is_rejected(): void {
		$this->expectException( TaxException::class );
		$this->calculator->calculate( array( 100 ), new TaxRate( 'VAT', 500 ), new TaxRate( 'VAT', 700 ) );
	}

	public function test_unknown_rate_label_lookup_throws(): void {
		$breakdown = $this->calculator->calculate( array( 100 ), new TaxRate( 'VAT', 500 ) );
		$this->expectException( TaxException::class );
		$breakdown->for_rate( 'Missing' );
	}
}
