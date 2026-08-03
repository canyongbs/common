@php
    /** @var \Workbench\App\Support\LocalGuidelineAssist $assist */
@endphp

# Working In The `canyongbs/common` Package (Not An App)

You are editing the `canyongbs/common` package itself — the shared library that Canyon GBS applications depend on — **not** a consuming Laravel app. Keep this context in mind, because the rest of this file and the skills are authored primarily for apps.

- **Run commands natively on the host.** There is no Docker and no `pls` helper in this repository. Run `{{ $assist->composerCommand('...') }}`, `{{ $assist->artisanCommand('...') }}`, and `vendor/bin/pest` directly. Whenever a shared guideline or skill tells you to run a command "through the `pls` guideline" or shows `pls exec app <command>`, ignore the `pls` wrapper and run `<command>` directly — the `pls` guideline is an app-only convention and is deliberately excluded here.
- **There is no full application to run.** Library code lives in `src/`; a Testbench app under `workbench/` provides just enough of a Laravel runtime to exercise it through Pest. There is no tenant database, no `.env`, and no app to `migrate` or serve — behaviour is verified with the workbench and tests, not a booted app.
- **Treat app mechanics as context, not literal steps.** The shared skills describe app-only concerns — multi-tenancy, Filament panels, Pennant feature flags activated per tenant, zero-downtime tenant migrations. Apply their *principles* when they fit the code you are changing, but do not expect that machinery to exist here. In particular, the `local-common-development` skill is an app-side workflow for linking this package into an app; it does not apply when you are already inside the package.
- **Everything here ships to every app.** Changes are published to consumers on their next release, so treat them accordingly and run the package's own checks (`{{ $assist->composerCommand('format') }}` then `{{ $assist->composerCommand('lint') }}`) before finishing.
