<?php
/**
 * Verifies inbound webhook signatures.
 *
 * A webhook that is not authenticated is an open door to forged orders and
 * refunds. This verifier recomputes the HMAC over the raw body and compares it
 * in constant time, so an attacker cannot forge a signature or learn anything
 * from timing. It is framework-free and therefore fully unit-testable.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Constant-time HMAC signature verification.
 */
final class SignatureVerifier {

	/**
	 * @param string $secret    Shared secret configured with the sender.
	 * @param string $algorithm Hash algorithm (default sha256).
	 *
	 * @throws \InvalidArgumentException On an empty secret or unknown algorithm.
	 */
	public function __construct(
		private readonly string $secret,
		private readonly string $algorithm = 'sha256',
	) {
		if ( '' === $secret ) {
			throw new \InvalidArgumentException( 'Webhook signing secret must not be empty.' );
		}
		if ( ! in_array( $algorithm, hash_hmac_algos(), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unsupported HMAC algorithm: %s', $algorithm ) );
		}
	}

	/**
	 * The signature the sender should have produced for this body.
	 *
	 * @param string $raw_body Exact raw request body (not the parsed array).
	 *
	 * @return string Lowercase hex HMAC.
	 */
	public function expected( string $raw_body ): string {
		return hash_hmac( $this->algorithm, $raw_body, $this->secret );
	}

	/**
	 * Whether a provided signature matches the body.
	 *
	 * Accepts the signature with or without an "sha256=" style prefix, since
	 * different providers format it differently.
	 *
	 * @param string $raw_body           Exact raw request body.
	 * @param string $provided_signature Signature from the request header.
	 *
	 * @return bool True only on an exact, constant-time match.
	 */
	public function verify( string $raw_body, string $provided_signature ): bool {
		$provided = $this->strip_prefix( $provided_signature );
		if ( '' === $provided ) {
			return false;
		}
		// hash_equals is constant-time: it never short-circuits on first diff.
		return hash_equals( $this->expected( $raw_body ), $provided );
	}

	/**
	 * Strip a leading "algo=" prefix if present.
	 *
	 * @param string $signature Raw signature header value.
	 *
	 * @return string
	 */
	private function strip_prefix( string $signature ): string {
		$signature = trim( $signature );
		$equals    = strpos( $signature, '=' );
		if ( false !== $equals && $equals < 12 ) {
			return substr( $signature, $equals + 1 );
		}
		return $signature;
	}
}
