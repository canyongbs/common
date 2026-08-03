@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

## Zero-Downtime Schema & Data Changes

These applications deploy with **zero downtime**: new code is released and reachable **before** migrations run, migrations run per-tenant, and one tenant's migration failing must not break others. Any code that depends on a schema or data change that may not have run yet will break in production between deploy and migration unless it is guarded.

Whenever you add or change a migration, or write code that relies on new or changed columns, tables, or data, you **must** consider both of the following:

- **Feature Flag** — gate any code path that requires the migration to have completed so the old path keeps running until the flag is activated, and activate the flag from the migration that makes the change.
- **Cleanup Task** — every Feature Flag and every temporary (`tmp_`) migration is temporary by design and must be recorded in a cleanup task so it is removed after the deployment succeeds.

Never assume a migration has already run when the code executes. If you are unsure whether a change needs a flag, assume it does.

@if ($assist->hasSkillsEnabled())
Follow the `managing-feature-flags`, `writing-data-migrations`, and `managing-cleanup-tasks` skills for the full workflow.
@endif
