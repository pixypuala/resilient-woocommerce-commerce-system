<?php
/**
 * Tests for webhook signature verification.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Tests;

use PHPUnit\Framework\TestCase;
use Pixypuala\ResilientCommerce\Webhook\SignatureVerifier;

final class SignatureVerifierTest extends TestCase {

	private const SECRET = 'shh-super-secret';

	public function test_correct_signature_verifies(): void {
		$verifier = new SignatureVerifier( self::SECRET );
		$body     = '{"a":1}';
		$this->assertTrue( $verifier->verify( $body, $verifier->expected( $body ) ) );
	}

	public function test_prefixed_signature_verifies(): void {
		$verifier = new SignatureVerifier( self::SECRET );
		$body     = '{"a":1}';
		$this->assertTrue( $verifier->verify( $body, 'sha256=' . $verifier->expected( $body ) ) );
	}

	public function test_wrong_signature_fails(): void {
		$verifier = new SignatureVerifier( self::SECRET );
		$this->assertFalse( $verifier->verify( '{"a":1}', 'not-the-signature' ) );
	}

	public function test_empty_signature_fails(): void {
		$verifier = new SignatureVerifier( self::SECRET );
		$this->assertFalse( $verifier->verify( '{"a":1}', '' ) );
	}

	public function test_empty_secret_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new SignatureVerifier( '' );
	}

	public function test_unknown_algorithm_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new SignatureVerifier( self::SECRET, 'not-a-real-algo' );
	}
}
