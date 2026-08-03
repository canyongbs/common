---
name: laravel-best-practices
description: 'Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying models, migrations, policies, jobs, invokable controllers, Action and service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, queue and job configuration, HTTP client usage, configuration and environment access, naming and code-style conventions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.'
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

### 1. Models → `rules/models.md`

- Relationship methods with correct types and return-type hints (`HasMany<Related, $this>`)
- Attribute casts in the `casts()` method; cast date columns to Carbon
- Define `$fillable` on every model to guard mass assignment
- Mirror database column defaults in the model's `$attributes`
- `#[CollectedBy]` for custom collection classes

### 2. Queries → `rules/queries.md`

- Start every query with `Model::query()` for a typed builder
- Eager load with `with()` to prevent N+1; enable `preventLazyLoading()` in development
- Select only needed columns; `withCount()` / `withExists()` to count or check existence
- Reusable constraints via invokable scope classes applied with `->tap(new Scope())`
- `whereBelongsTo($model)` for relationship queries
- Iterate large datasets safely: `chunkById()` / `eachById()` when mutating, `cursor()` read-only, `lazy()` for eager loading
- `toQuery()` for bulk operations; higher-order messages for simple collection ops
- Never hardcode table names (`(new Model)->getTable()`); index queried columns

### 3. Advanced Query Patterns → `rules/advanced-queries.md`

- `addSelect()` subqueries over eager-loading an entire has-many for a single value
- Dynamic relationships via subquery FK + `belongsTo`
- Conditional aggregates (`CASE WHEN` in `selectRaw`) over multiple count queries
- `setRelation()` to prevent circular N+1 queries
- `whereIn` + `select('id')` subquery over `whereHas` for index-friendly lookups
- Two simple queries can beat one complex query
- Compound indexes matching `orderBy` column order
- Correlated subqueries in `orderBy` for has-many sorting (avoid joins)

### 4. Migrations → `rules/migrations.md`

- Generate migrations with `php artisan make:migration`
- `constrained()` for foreign keys
- Never modify migrations that have run in production
- Add indexes in the migration (columns used in `WHERE`, `ORDER BY`, `JOIN`)
- Mirror column defaults in the model's `$attributes`
- Reversible `down()` by default; forward-fix intentionally irreversible changes
- One concern per migration — never mix DDL and DML

### 5. Controllers & HTTP Endpoints → `rules/controllers.md`

- Single-action invokable controllers (no base `Controller`) for the few standalone endpoints
- Validate inline with `$request->validate([...])` — no Form Request classes
- Authorize with `Gate::authorize()`; return Eloquent API Resources
- Keep controllers thin — validate, authorize, delegate to an Action, respond

### 6. Queue & Jobs → `rules/queue-jobs.md`

- `retry_after` must exceed job `timeout`; use exponential backoff `[1, 5, 10]`
- `ShouldBeUnique` to prevent duplicates; `ShouldBeUniqueUntilProcessing` for early lock release
- Always implement `failed()`; with `retryUntil()`, set `$tries = 0`
- `RateLimited` middleware for external API calls; `Bus::batch()` for related jobs

### 7. HTTP Client → `rules/http-client.md`

- Explicit `timeout` and `connectTimeout` on every request
- `retry()` with exponential backoff for external APIs
- Check response status or use `throw()`
- `Http::pool()` for concurrent independent requests
- `Http::fake()` and `preventStrayRequests()` in tests

### 8. Caching → `rules/caching.md`

- `Cache::remember()` over manual get/put
- `Cache::flexible()` for stale-while-revalidate on high-traffic data
- `Cache::memo()` to avoid redundant cache hits within a request
- Cache tags to invalidate related groups
- `Cache::add()` for atomic conditional writes
- `once()` to memoize per-request or per-object lifetime

### 9. Security → `rules/security.md`

- Define `$fillable` or `$guarded` on every model, authorize every action via policies or gates
- No raw SQL with user input — use Eloquent or query builder
- `{{ }}` for output escaping, `@csrf` on hand-written POST/PUT/DELETE Blade forms, `throttle` on auth and API routes
- Validate MIME type, extension, and size for file uploads
- Never commit `.env`, use `config()` for secrets, `encrypted` cast for sensitive DB fields
- Run `composer audit` to catch vulnerable dependencies

### 10. Architecture → `rules/architecture.md`

- Single-purpose invokable Action classes for business operations
- Constructor dependency injection over the `app()` helper; code to interfaces at system boundaries
- Atomic locks (`Cache::lock()` / `lockForUpdate()`) for race conditions
- `mb_*` string functions for UTF-8 safety
- `defer()` for post-response work; `Context` for request-scoped data; `Concurrency::run()` for parallel execution
- Follow Laravel conventions; don't override defaults unnecessarily

### 11. Conventions & Style → `rules/style.md`

- Follow Laravel naming conventions for all entities (classes, tables, columns, FKs, routes…)
- Name boolean columns `is_` / `has_` / `can_` and timestamps past-tense `_at`
- Prefer shorter, readable syntax (`now()`, `session()`, `back()`, `->latest()`)
- Prefer Laravel helpers (`Str`, `Arr`, `Number`, `Uri`, `Str::of()`, `$request->string()`) over raw PHP functions
- No JS/CSS in Blade, no HTML in PHP classes; comments only for config files

### 12. Configuration → `rules/config.md`

- `env()` only inside config files
- `App::environment()` or `app()->isProduction()`
- Config, lang files, and constants over hardcoded text

## How to Apply

Always use a sub-agent to read rule files and explore this skill's content.

1. Identify the file type and select relevant sections (e.g., model → §1, §9; query → §2, §3; migration → §4; HTTP endpoint → §5, §9; job → §6)
2. Check sibling files for existing patterns — follow those first per Consistency First
3. Verify API syntax with `search-docs` for the installed Laravel version
