# Project: Pakistan POS Foundation — Laravel Backend

## Architecture Constraints (NEVER violate)
1. Multi-tenancy is single-database with `tenant_id` on every table. Use Laravel global scopes. Never write a query that can return another tenant's rows.
2. All money is stored as `bigInteger` in minor units (paisa). Never use `float` or `decimal` for money.
3. Financial records (sales, payments, refunds) are append-only. Never `UPDATE` a sale after it is completed. Corrections are new refund/void records.
4. Sales and stock movements use client-generated UUIDs as their primary identifier. The `sale_id` column has a unique constraint per tenant.
5. FBR submission logic does NOT live in this backend. The Tauri client submits to FBR directly. This backend only stores FBR credentials and receives result status (`fbr_usin`, `fbr_status`, `fbr_error`).
6. Every mutation must write to `audit_logs`.
7. API routes are versioned under `/api/v1/`. Authentication is Laravel Sanctum Token only. No session/cookie auth.
8. PostgreSQL is the target database. Use `bigInteger` (not `unsignedBigInteger`), avoid zero-dates, and add indexes on foreign keys explicitly.

## Coding Standards
- Laravel 11+ conventions. Action classes for complex mutations, not fat controllers.
- Type-hint everything. Use readonly properties where possible.
- Every public method has a test.
- Use Pest or PHPUnit consistently (choose Pest).

## What "Done" Means
- Tests pass (`php artisan test`).
- `php artisan migrate:fresh --seed` works without errors.
- No cross-tenant data leakage in test suite.

## NEVER Do
- Never add a `vertical_type` code branch (`if ($vertical === 'restaurant')`). Vertical differences are configuration data, not code branches.
- Never store FBR Bearer Tokens in `.env` or config files. Use encrypted database columns or a secrets manager.
- Never use `->get()` without a `where('tenant_id', ...)` unless the model has the global scope trait applied.