# Project: Pakistan POS Foundation — Tauri POS Client

## Architecture Constraints
1. This is a Tauri v2 desktop app. Rust backend handles: local SQLite, ESC/POS printing, FBR IRIS API calls, and the outbox worker.
2. Vue 3 + TypeScript frontend. No SSR. This app runs offline by default.
3. Local database is SQLite (via `rusqlite` or `sqlx`). WAL mode. Every sale is ACID-transactional.
4. FBR submission: the Rust side calls FBR IRIS directly using the tenant's Bearer Token. The Vue frontend never touches FBR credentials.
5. Printing: use the printer's native QR command, not a bitmap. QR version 2.0, 25×25 modules, printed at 0.70–1.0 inch [citation:6].
6. Provisional receipts MUST be visually marked: "This receipt was generated in offline mode. The invoice number is provisional. It will be submitted to FBR within 24 hours. The QR code cannot be verified temporarily."
7. The sync outbox uses the six-state machine defined in the architecture document: `pending → processing → retrying → completed | permanently_failed`.
8. Every sync record has a client-generated UUID as its idempotency key.
9. Scan QR code printed on Provisional receipt should track to final invoice number after successful submission to FBR.

## Rust Standards
- `tokio` async for the outbox worker.
- `serde` for JSON serialization.
- Errors: use `thiserror` for domain errors, `anyhow` for application errors.
- No `unwrap()` in production paths.

## Vue Standards
- Composition API with `<script setup>`.
- Pinia for state management.
- No direct `fetch()` calls from components. Use a typed API client service.

## What "Done" Means
- `cargo test` passes.
- `npm run build` succeeds.
- A sale can be completed, printed, and queued for FBR submission with the network cable unplugged.

## NEVER Do
- Never block the UI thread while printing or submitting to FBR.
- Never lose a completed sale because of a sync failure. The outbox is the source of truth for "needs to be sent."
- Never print an FBR logo or "Verify via Tax Asaan" on a provisional receipt.