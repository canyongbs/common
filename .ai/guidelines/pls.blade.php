@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

# Running Commands

The application runs inside Docker containers managed by the `pls` helper. The host machine's toolchain (PHP version and extensions, Composer, Node) does not necessarily match the container, so commands run on the host may fail or behave differently.

- Run every application command inside the `app` container via `pls exec app <command>`. This applies to all PHP, Artisan, Composer, and Node/npm commands, and **overrides** any other guideline that shows a bare `php artisan`, `composer`, or `npm` command — always prefix those with `pls exec app` in this project.
    - Examples:
        - `pls exec app php artisan migrate`
        - `pls exec app composer install`
        - `pls exec app npm run build`
- MCP tools whose server is launched inside the `app` container (for example the Laravel Boost MCP server) are exempt — invoke them directly, as they already execute in-container. The `pls exec app` rule applies only to shell commands you run yourself, not to MCP tool calls.
- Container lifecycle commands are run with `pls` directly (not through `pls exec app`), for example `pls up -d`, `pls down`, and `pls build`.
- If `pls exec app ...` fails because the containers are not running, start them with `pls up -d` and retry.
