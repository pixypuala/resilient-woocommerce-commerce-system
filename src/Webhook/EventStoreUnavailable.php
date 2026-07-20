<?php
/**
 * The dedup store could not be reached or written to.
 *
 * Distinct from losing a claim. Losing a claim is a normal, correct outcome —
 * the event was already processed. This means the store gave no answer at all,
 * so whether the event was processed is unknown. The only safe response is to
 * refuse the delivery and let the provider retry.
 *
 * @package Pixypuala\ResilientCommerce
 */

declare( strict_types=1 );

namespace Pixypuala\ResilientCommerce\Webhook;

/**
 * Raised when the processed-event store is unusable.
 */
final class EventStoreUnavailable extends \RuntimeException {}
