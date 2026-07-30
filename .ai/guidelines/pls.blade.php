@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Running Commands

The application runs inside Docker containers managed by the `pls` helper. The host machine's toolchain (PHP version and extensions, Composer, Node) does not necessarily match the container, so commands run on the host may fail or behave differently.

- Run every application command inside the `app` container via `pls exec app <command>`. This applies to all PHP, Artisan, Composer, and Node/npm commands.
- Examples:
  - `pls exec app php artisan migrate`
  - `pls exec app composer install`
  - `pls exec app npm run build`
- Do not run these commands directly on the host.
- Container lifecycle commands are run with `pls` directly (not through `pls exec app`), for example `pls up -d`, `pls down`, and `pls build`.
