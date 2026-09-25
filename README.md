# Dolibarr Completion Certificate

Dolibarr 23 module for creating and managing completion certificates from customer orders.

## Features

- Native **Create completion certificate** action on validated customer orders.
- Dedicated **Completion certificates** order tab with native Dolibarr count badge.
- Native Dolibarr linked-object integration with reference, amount and compact status.
- Two completion modes:
  - **Line-based completion** with per-order-line certified quantities.
  - **Progress percentage** for milestone/progress billing against the order net amount.
- Active certificates on one order use one consistent completion mode.
- Partial completion protection against over-certification or progress above 100%.
- Stored net amount for each certificate and line-based certificate line.
- Native Dolibarr object links between customer orders and completion certificates.
- Draft, validated and canceled lifecycle.
- Draft editing and controlled reopening after cancellation.
- Native Dolibarr PDF document model with automatic regeneration and status watermark.
- Native Dolibarr document block for generated files.
- Native Dolibarr email composition and sending flow with PDF attachment.
- Delete support for draft and canceled certificates.
- Hungarian and English UI labels.

Module path inside Dolibarr: `htdocs/custom/completioncertificate`.
