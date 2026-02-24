# Customer Identity System

Tenant-scoped customer identity layer for the storefront API, separate from admin users.

## Overview

- **Customers** live in the **tenant database** (`customers`, `customer_addresses`, `customer_sessions`, `customer_password_reset_tokens`, `personal_access_tokens`).
- **Admin users** remain the existing `User` model (Filament panels).
- **Authentication**: Laravel Sanctum with guard `customer`; tokens stored in tenant DB.
- **API base**: `/api/v1/customer/` (tenant-aware via `InitializeTenancyBySubdomain`).

## Database (tenant migrations)

- `customers` — id (UUID), tenant_id, email (unique per tenant), password, first_name, last_name, phone, email_verified_at, is_active, last_login_at, meta, timestamps, soft deletes.
- `customer_addresses` — type (billing/shipping), line1, line2, city, state, postal_code, country_code, is_default.
- `customer_sessions` — ip_address, user_agent, last_activity_at (optional security).
- `customer_password_reset_tokens` — tenant-scoped password resets.
- `personal_access_tokens` — Sanctum tokens for customers (tenant DB).
- `orders.customer_id` — nullable FK to `customers`; `customer_email` always snapshotted.

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/customer/register` | — | Register; returns token. Links past guest orders by email. |
| POST | `/api/v1/customer/login` | — | Login; returns token. Generic error to prevent enumeration. |
| POST | `/api/v1/customer/logout` | customer | Revoke current token. |
| POST | `/api/v1/customer/forgot-password` | — | Send reset link (broker: customers). |
| POST | `/api/v1/customer/reset-password` | — | Reset with token. |
| GET | `/api/v1/customer/me` | customer | Current customer. |
| PATCH | `/api/v1/customer/profile` | customer | Update profile. |
| GET/POST/PATCH/DELETE | `/api/v1/customer/addresses` | customer | CRUD addresses. |
| POST | `/api/v1/customer/password/change` | customer | Change password; revokes all tokens. |
| GET | `/api/v1/customer/export` | customer | GDPR data export. |
| DELETE | `/api/v1/customer/account` | customer | Self-service delete (anonymize + soft delete). |

All protected routes use `auth:customer` and `Authorization: Bearer <token>`.

## Security

- Passwords hashed (bcrypt/argon2 via Laravel).
- Rate limits: `customer-register` (5/min), `customer-login` (5/min), `customer-forgot-password` (3/min), `customer-reset-password` (3/min).
- Login returns generic message on failure (no enumeration).
- Inactive customers cannot log in.
- Tokens revoked on logout and on password change.
- Audit: customer_registered, customer_logged_in, customer_profile_updated, customer_password_changed, address_created, address_deleted (with actor_id, IP, user_agent, timestamp).

## Order integration

- Checkout accepts optional `customer_id` from `auth('customer')->id()`; order is linked and `customer_email` is always snapshotted.
- After order is locked, customer cannot be changed.

## Guest checkout & linking

- Orders can be created without a customer account (`customer_id` null, `customer_email` set).
- When a guest registers (or logs in and matches), `LinkGuestOrdersToCustomerService::linkByEmail()` links past orders with the same email to the customer (called after registration).

## Promotion integration

- `CustomerPromotionEligibilityService::orderCountForCustomer(?string $customerId, string $email)` — use for `usage_limit_per_customer`.
- `CustomerPromotionEligibilityService::hasPlacedOrder(?string $customerId, string $email)` — use for `first_purchase` rule.
- Use `customer_id` when authenticated, fallback to `email` for guest.

## GDPR

- **Soft delete** customers.
- **Anonymize** on delete: email → `anon_*@deleted.local`, name → Deleted User, clear phone/meta, then soft delete.
- **Export**: `GET /api/v1/customer/export` returns customer + addresses.
- **Delete account**: `DELETE /api/v1/customer/account` (body: password, confirm) runs anonymization + soft delete and revokes tokens.

## Filament (Tenant panel)

- **CustomerIdentityResource** (group: Store, label: Customers): list/edit customers, deactivate, reset password, relation managers Addresses and Orders.
- Existing **CustomerResource** (Orders group) remains the read-only customer summary from orders.

## Config

- `config/auth.php`: guard `customer` (driver: sanctum, provider: customers); provider `customers` (Customer model); passwords broker `customers` (table: `customer_password_reset_tokens`).
- `config/sanctum.php`: `guard` includes `customer`.

## Tests

Run: `php artisan test tests/Feature/CustomerIdentity/`

- Customer registration, unique email per tenant.
- Login success, failure (generic error), inactive cannot login.
- Login rate limit (429 after 5 failures).
- Email verification field.
- Order linked to customer.
- First purchase / order count for promotions.
- Guest orders linked to customer by email.
- Account deletion anonymizes data.
