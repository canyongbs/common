---
name: laravel-best-practices
description: 'Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying models, migrations, policies, jobs, invokable controllers, Action and service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, queue and job configuration, HTTP client usage, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.'
license: MIT
metadata:
    author: laravel
---

# Laravel Best Practices

Best practices for Laravel, prioritized by impact. Each rule teaches what to do and why. For exact API syntax, verify with `search-docs`.

## Consistency First

Before applying any rule, check what the application already does. Laravel offers multiple valid approaches — the best choice is the one the codebase already uses, even if another pattern would be theoretically better. Inconsistency is worse than a suboptimal pattern.

Check sibling files, related controllers, models, or tests for established patterns. If one exists, follow it — don't introduce a second way. These rules are defaults for when no pattern exists yet, not overrides.

## Quick Reference

### 1. Database Performance → `rules/db-performance.md`

- Eager load with `with()` to prevent N+1 queries
- Enable `Model::preventLazyLoading()` in development
- Select only needed columns, avoid `SELECT *`
- `chunk()` / `chunkById()` for large datasets
- Index columns used in `WHERE`, `ORDER BY`, `JOIN`
- `withCount()` / `withExists()` instead of loading relations to count or check existence
- `cursor()` for memory-efficient read-only iteration
- Never query in Blade templates

### 2. Advanced Query Patterns → `rules/advanced-queries.md`

- `addSelect()` subqueries over eager-loading an entire has-many for a single value
- Dynamic relationships via subquery FK + `belongsTo`
- Conditional aggregates (`CASE WHEN` in `selectRaw`) over multiple count queries
- `setRelation()` to prevent circular N+1 queries
- `whereIn` + `select('id')` subquery over `whereHas` for index-friendly lookups
- Two simple queries can beat one complex query
- Compound indexes matching `orderBy` column order
- Correlated subqueries in `orderBy` for has-many sorting (avoid joins)

### 3. Security → `rules/security.md`

- Define `$fillable` or `$guarded` on every model, authorize every action via policies or gates
- No raw SQL with user input — use Eloquent or query builder
- `{{ }}` for output escaping, `@csrf` on hand-written POST/PUT/DELETE Blade forms, `throttle` on auth and API routes
- Validate MIME type, extension, and size for file uploads
- Never commit `.env`, use `config()` for secrets, `encrypted` cast for sensitive DB fields
- Run `composer audit` to catch vulnerable dependencies

### 4. Caching → `rules/caching.md`

- `Cache::remember()` over manual get/put
- `Cache::flexible()` for stale-while-revalidate on high-traffic data
- `Cache::memo()` to avoid redundant cache hits within a request
- Cache tags to invalidate related groups
- `Cache::add()` for atomic conditional writes
- `once()` to memoize per-request or per-object lifetime

### 5. Eloquent Patterns → `rules/eloquent.md`

- Correct relationship types with return type hints (`HasMany<Related, $this>`)
- Start every query with `Model::query()` for a typed builder
- Tappable, invokable scope classes applied with `->tap(new Scope())` for reusable constraints
- Name boolean columns `is_` / `has_` / `can_` and timestamps past-tense `_at`
- Attribute casts in the `casts()` method
- Cast date columns, use Carbon instances in templates
- `whereBelongsTo($model)` for cleaner queries
- Never hardcode table names — use `(new Model)->getTable()` or Eloquent queries
- `chunkById()` / `eachById()` over `chunk()` / `each()` when modifying rows during iteration

### 6. Configuration → `rules/config.md`

- `env()` only inside config files
- `App::environment()` or `app()->isProduction()`
- Config, lang files, and constants over hardcoded text

### 7. Queue & Job Patterns → `rules/queue-jobs.md`

- `retry_after` must exceed job `timeout`; use exponential backoff `[1, 5, 10]`
- `ShouldBeUnique` to prevent duplicates; `ShouldBeUniqueUntilProcessing` for early lock release
- Always implement `failed()`; with `retryUntil()`, set `$tries = 0`
- `RateLimited` middleware for external API calls; `Bus::batch()` for related jobs

### 8. HTTP Client → `rules/http-client.md`

- Explicit `timeout` and `connectTimeout` on every request
- `retry()` with exponential backoff for external APIs
- Check response status or use `throw()`
- `Http::pool()` for concurrent independent requests
- `Http::fake()` and `preventStrayRequests()` in tests

### 9. Architecture → `rules/architecture.md`

- Single-purpose invokable Action classes for business operations
- Thin, invokable controllers for the few HTTP endpoints; inline `$request->validate()` (no Form Requests); Eloquent API Resources
- Constructor dependency injection over the `app()` helper; code to interfaces at system boundaries
- Atomic locks (`Cache::lock()` / `lockForUpdate()`) for race conditions
- `mb_*` string functions for UTF-8 safety
- `defer()` for post-response work; `Context` for request-scoped data; `Concurrency::run()` for parallel execution
- Follow Laravel conventions; don't override defaults unnecessarily

### 10. Migrations → `rules/migrations.md`

- Generate migrations with `php artisan make:migration`
- `constrained()` for foreign keys
- Never modify migrations that have run in production
- Add indexes in the migration, not as an afterthought
- Mirror column defaults in model `$attributes`
- Reversible `down()` by default; forward-fix migrations for intentionally irreversible changes
- One concern per migration — never mix DDL and DML

### 11. Collections → `rules/collections.md`

- Higher-order messages for simple collection operations
- `cursor()` vs. `lazy()` — choose based on relationship needs
- `lazyById()` when updating records while iterating
- `toQuery()` for bulk operations on collections
- `#[CollectedBy]` for custom collection classes

### 12. Conventions & Style → `rules/style.md`

- Follow Laravel naming conventions for all entities
- Prefer shorter, readable syntax (`now()`, `session()`, `back()`, `->latest()`)
- Prefer Laravel helpers (`Str`, `Arr`, `Number`, `Uri`, `Str::of()`, `$request->string()`) over raw PHP functions
- No JS/CSS in Blade, no HTML in PHP classes
- Code should be readable; comments only for config files

## How to Apply

Always use a sub-agent to read rule files and explore this skill's content.

1. Identify the file type and select relevant sections (e.g., migration → §10; model → §1, §5; HTTP endpoint → §3, §9)
2. Check sibling files for existing patterns — follow those first per Consistency First
3. Verify API syntax with `search-docs` for the installed Laravel version
