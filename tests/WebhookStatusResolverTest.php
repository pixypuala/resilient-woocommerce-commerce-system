<?php
/**
 * Tests for the webhook payload → order-status-change mapper.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Order\OrderException;
use Pixypuala\ResilientCommerce\Order\OrderStatus;
use Pixypuala\ResilientCommerce\Order\WebhookStatusResolver;

final class WebhookStatusResolverTest extends TestCase {

	private WebhookStatusResolver $resolver;

	protected function setUp(): void {
		$this->resolver = new WebhookStatusResolver();
	}

	public function test_resolves_order_id_and_target_status(): void {
		$change = $this->resolver->resolve( '{"order":{"id":42,"status":"completed"}}' );
		$this->assertNotNull( $change );
		$this->assertSame( 42, $change->order_id() );
		$this->assertSame( OrderStatus::Completed, $change->target() );
	}

	public function test_tolerates_the_wc_status_prefix(): void {
		$change = $this->resolver->resolve( '{"order":{"id":7,"status":"wc-processing"}}' );
		$this->assertNotNull( $change );
		$this->assertSame( OrderStatus::Processing, $change->target() );
	}

	public function test_non_order_event_resolves_to_null(): void {
		$this->assertNull( $this->resolver->resolve( '{"type":"ping"}' ) );
	}

	public function test_order_without_status_resolves_to_null(): void {
		$this->assertNull( $this->resolver->resolve( '{"order":{"id":42}}' ) );
	}

	public function test_invalid_json_is_rejected(): void {
		$this->expectException( OrderException::class );
		$this->resolver->resolve( 'not json' );
	}

	public function test_unknown_status_is_rejected(): void {
		$this->expectException( OrderException::class );
		$this->resolver->resolve( '{"order":{"id":42,"status":"quantum"}}' );
	}

	public function test_non_integer_order_id_is_rejected(): void {
		$this->expectException( OrderException::class );
		$this->resolver->resolve( '{"order":{"id":"42","status":"completed"}}' );
	}

	public function test_non_positive_order_id_is_rejected(): void {
		$this->expectException( OrderException::class );
		$this->resolver->resolve( '{"order":{"id":0,"status":"completed"}}' );
	}
}
