# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Repository scaffolding: governance files, docs, and CI skeleton.
- Replay-safe, idempotent webhook inbox: constant-time HMAC verification, replay window, atomic dedup ($wpdb UNIQUE-key store).
- Oversell-safe stock ledger: reserved vs committed stock, TTL reclaim, never sells below zero.
- REST webhook endpoint wiring (fails safe without a signing secret).
- Idempotent, WooCommerce-aligned `OrderStateMachine` (`src/Order/`): redelivered status events are safe no-ops; illegal lifecycle transitions are rejected.
- 27 PHPUnit tests; PHPCS/WPCS clean; CI on PHP 8.1 and 8.3.
