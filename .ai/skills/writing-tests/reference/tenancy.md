# Testing Tenancy (Landlord / Tenant)

This file applies to apps that use **Spatie multitenancy** with separate landlord and tenant databases and split test suites. If this app uses a single database with a Filament tenant, ignore this file: set the current tenant in `beforeEach()` and keep tests in the single mirrored tree.

## Choosing the suite

Put a test where the code it exercises actually runs. The suite is chosen purely by directory — `tests/Pest.php` binds `TenantTestCase` to `Tenant` and `LandlordTestCase` to `Landlord`.

- **Tenant suite** (`tests/Tenant/**`) — anything scoped to a tenant: tenant models, tenant-panel Filament resources/pages/relation managers, tenant actions, notifications, seeders. Most tests live here.
- **Landlord suite** (`tests/Landlord/**`) — central/landlord code: tenant provisioning and management, landlord console commands, landlord-panel and cross-tenant logic, architecture tests.

Inside each suite the path still mirrors the source namespace (`app/Models/Foo.php` → `tests/Tenant/Models/FooTest.php`), and every other convention in this skill applies unchanged: one file per class, `it()` only, the `describe()` ordering with `authorization` last, request factories, etc.

## What the suite already does (do not re-bootstrap)

- A **tenant test runs with a tenant already current**; its factories and queries hit the tenant connection. Do not create or `makeCurrent()` a tenant just to write an ordinary tenant test.
- A **landlord test runs on the landlord connection** with no tenant current.
- Database state is reset per suite by the base test cases. Do not add `RefreshDatabase`, migrations, or tenant creation inside individual tests.

## Tricks

- **Run code in a specific tenant's context** — from a landlord test, or to touch a second tenant: `$tenant->makeCurrent()` to switch, or `$tenant->execute(fn () => /* ... */)` to run a closure in that tenant and switch back afterwards. `Tenant::forgetCurrent()` drops back to the landlord.
- **Assert data landed in the right database** — tenant-connection models are only visible while that tenant is current; landlord models (e.g. the `Tenant` record itself) are queried from a landlord test or after `Tenant::forgetCurrent()`.
- **Provisioning tests** (landlord) assert the new tenant row exists on the landlord connection; only enter the tenant with `execute()` when you must assert tenant-side effects of provisioning.
- **Prove tenant isolation** — create data while one tenant is current, `makeCurrent()` a second tenant, and assert the first tenant's records are not visible.
- Shared auth helpers (`createUserWithPermissions()`, `createUserWithRoleNamed()`) live in `tests/Pest.php` as in single-database apps, but here they do **not** attach a Filament tenant — tenancy comes from the suite. Check `tests/Pest.php` for the exact helpers available.
