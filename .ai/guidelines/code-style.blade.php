@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

## Code Style & Static Analysis

Before considering any change complete, make it pass the project's automated checks — do not leave style or analysis violations for CI:

- Apply formatting fixes with `{{ $assist->composerCommand('format') }}`.
- Then run `{{ $assist->composerCommand('lint') }}` (PHPStan/Larastan static analysis) and resolve **everything** it reports.

CI runs `{{ $assist->composerCommand('checks') }}` (`format-dryrun` plus `lint`); running `format` then `lint` locally satisfies it without re-checking the formatting you just wrote.

### Regenerate model types after schema changes

If your change adds or alters database columns or relationships, run the migrations and then `{{ $assist->composerCommand('generate-helper-files') }}` **before** linting. The IDE-helper docblocks on each model are generated from the live schema, so `lint` reports phantom errors on the changed models until they are regenerated — do **not** hand-edit those docblocks or "fix" the analysis errors, regenerate instead.

Migrate first so the generator reads the new schema. In a single-database app run `{{ $assist->artisanCommand('migrate') }}`. If the app has separate landlord and tenant databases, do **not** run `{{ $assist->artisanCommand('migrate') }}` — migrate the landlord database with `{{ $assist->artisanCommand('migrate:landlord') }}` and the tenant databases with `{{ $assist->artisanCommand('tenant:artisan "migrate"') }}`.

### Narrow types with `assert()`, never inline `@var`

When PHPStan needs a narrower type for a variable than it can infer, use a runtime `assert()` call — **never** an inline `/** @var ... */` docblock. Assertions are enabled in production, so they both satisfy the analyser and fail loudly if the assumption is ever wrong; an inline `@var` silently lies to PHPStan and hides real bugs. Because assertions run in production here, do **not** treat `assert()` as a no-op or assume it is disabled. This does not apply to property/const `@var` docblocks (e.g. on `$fillable`), which remain the correct way to type declarations.

@verbatim
```php
// Do this — verified at runtime, understood by PHPStan.
assert($record instanceof User);

// Not this — an unchecked claim the analyser is forced to trust.
/** @var User $record */
```
@endverbatim

@if ($assist->hasSkillsEnabled())
See the `code-style-and-static-analysis` skill for the tools involved and how to resolve common failures.
@endif
