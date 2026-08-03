---
name: local-common-development
description: 'Use when an app ticket requires changes to the canyongbs/common package and you need to work against a local, editable checkout of common instead of the released version. Trigger whenever a change must be made in common (guidelines, skills, migrations, models, Filament resources, enums, console commands, health checks, rector sets, or any src/ code) while developing or testing inside a consuming app. Covers linking common into an app via a Composer path repository with symlink, bind-mounting the local checkout into the app''s Docker containers so the symlink resolves, verifying the symlink, editing common with changes reflected live, republishing common''s AI content, and safely unlinking to restore the released version before committing the app. Do not trigger for changes that live entirely inside the app, or for editing an app''s own app-modules.'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Working With a Local `canyongbs/common`

Canyon GBS apps depend on the shared `canyongbs/common` package. When an app ticket needs a change _inside_ common, do not edit files under `vendor/canyongbs/common` — that directory is a Composer artifact. Instead, link a local, editable checkout of common into the app so edits are reflected live, make and test the change there, then unlink and release common through its own PR.

These apps run in Docker via the `pls` helper, so linking has **two** parts that must agree: a Composer path repository (creates the symlink) **and** a Docker bind mount (makes the symlink's target exist inside the containers). Do both, or the symlink dangles inside the container.

## When to use this

Use this workflow whenever the fix or feature for an app ticket requires touching common: its `src/` code (models, Filament resources, enums, console commands, health checks, rector sets), database migrations, or its published AI content under `.ai/` (guidelines and skills). If the change lives entirely within the app, you do not need any of this.

## Prerequisites

- The app and common are checked out as **siblings** on disk, so common is reachable from the app root at `../common`:
    ```
    Code/
      your-app/       <- the app you're working in
      common/         <- the shared package
    ```
- If `../common` is missing, clone it as a sibling of the app first:
    ```bash
    git clone git@github.com:canyongbs/common.git ../common
    ```

## Step 1 — Composer path repository

Composer links a local path as a symlink. An app may already be permanently wired this way as a dev harness; most apps must be linked temporarily for the ticket.

1. In the **app's** `composer.json`, add a `path` repository for `../common`. `repositories` is an array, so **append** this entry — do not remove an existing `app-modules/*` entry:

    ```json
    "repositories": [
        {
            "type": "path",
            "url": "app-modules/*",
            "options": { "symlink": true }
        },
        {
            "type": "path",
            "url": "../common",
            "options": { "symlink": true }
        }
    ]
    ```

    If the app has no `repositories` key at all, add the whole key with just the `../common` entry.

2. Point the requirement at the local checkout by changing the version constraint to `@dev`:
    ```json
    "canyongbs/common": "@dev"
    ```
    A path repository is always available regardless of `minimum-stability`/`prefer-stable`, so `@dev` resolves to the local checkout even in apps configured for stable releases.

## Step 2 — Docker bind mount

Composer creates the symlink as `vendor/canyongbs/common -> ../../../common`. The app is mounted in the container at `/var/www/html`, so that relative link resolves **inside the container** to `/var/www/common`. That path only exists if you bind-mount the sibling checkout there.

In the app's `docker-compose.dev.yml`, add the mount to **every service that runs app code** — typically `app`, `worker`, and `scheduler`. Each already mounts the app as `- '.:/var/www/html'`; add a sibling line next to it:

```yaml
app:
    volumes:
        - '.:/var/www/html'
        - '../common:/var/www/common'
```

Notes for keeping this generic per app:

- The mount **target** must be the path the symlink resolves to. With the app at `/var/www/html`, that is always `/var/www/common`. Confirm the app's mount point if it differs.
- Add the mount to _all_ app-executing services, not just `app` — the queue worker and scheduler load common too.
- The `local-cli` tooling service is defined in a separate `docker-compose.local-cli.yml` (not `docker-compose.dev.yml`). If you run commands through local-cli, add the same `../common:/var/www/common` mount to its service there too.

## Step 3 — Install and restart

1. Recreate the containers so the new mount takes effect:
    ```bash
    pls up -d
    ```
2. Re-resolve the dependency inside the container:
    ```bash
    pls exec app composer update canyongbs/common -W
    ```

## Verify

Confirm the symlink exists and resolves inside the container:

```bash
pls exec app ls -l vendor/canyongbs/common
# -> vendor/canyongbs/common -> ../../../common
pls exec app cat vendor/canyongbs/common/composer.json | head -3
```

If the second command errors with "No such file or directory", the symlink is dangling — the Docker mount from Step 2 is missing or the containers were not recreated.

Once linked, any edit in `../common` is live in the app immediately — no reinstall needed.

## Make and test the change

- Edit files in `../common`, then run the app's tests / verify behaviour in the container.
- If your change touches common's **AI content** (`.ai/guidelines` or `.ai/skills`), regenerate the app's published copy so `AGENTS.md`, `.ai/`, `boost.json`, and `.vscode/mcp.json` reflect it:
    ```bash
    pls exec app php artisan common:publish
    ```
    Published skills land at `.ai/skills/<name>/SKILL.md`; guidelines compile into `AGENTS.md`.
- If your change adds migrations or publishable assets, run the relevant Artisan commands in the container as you would for any package change.

## Unlink and restore the released version

The Composer path repo, the `@dev` constraint, and the Docker bind mount are **local-only** and must never be committed to a normal app (a dedicated dev-harness app is the exception — it keeps these in place permanently). Before finishing the app work:

1. Revert the Composer wiring and reinstall the released version:
    ```bash
    git checkout -- composer.json composer.lock
    pls exec app composer install
    ```
2. Revert the Docker mount and recreate the containers:
    ```bash
    git checkout -- docker-compose.dev.yml
    pls up -d
    ```

## Ship the change correctly

1. Open a **separate PR in the `common` repo** for the change and get it merged/released (tagged).
2. In the app, bump the `canyongbs/common` constraint to the new released version (e.g. `^2.34.0`) — never leave `@dev`, the `../common` path repository, or the `/var/www/common` mount in a normal app's committed config.
3. Run `pls exec app composer update canyongbs/common -W` to lock the released version, then commit the app changes.

## Common mistakes to avoid

- Adding the Composer path repo but forgetting the Docker mount (or vice versa) — the symlink then dangles inside the container even though `ls` looks fine on the host.
- Mounting common into only the `app` service and not `worker`/`scheduler`, so queued/scheduled code fails.
- Not running `pls up -d` after changing `docker-compose.dev.yml`, so the mount never takes effect.
- Editing files under `vendor/canyongbs/common` directly — they are overwritten on the next `composer install` unless symlinked.
- Committing the `../common` path repo, the `@dev` constraint, or the `/var/www/common` mount to a normal app.
- Forgetting to run `common:publish` after changing common's guidelines or skills, so the app's `AGENTS.md`/`.ai` go stale.

---

Related: `authoring-agent-guidance` (how guidelines, skills, and the publish/override system work).
