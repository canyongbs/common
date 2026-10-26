---
name: archiving-records
description: 'Use when adding or working with archiving in a Canyon GBS app — a soft alternative to deletion using an `archived_at` timestamp where archived records are INCLUDED in queries by default (unlike SoftDeletes). Trigger whenever you add the `CanBeArchived` trait to a model, add an `archived_at` column, use archive()/unarchive()/isArchived() or the withoutArchived / onlyArchived / withoutArchivedAndUnused query scopes, define used()/isUsed() for archived-but-still-used records, wire the Filament ArchiveAction / ArchiveBulkAction / UnarchiveAction / UnarchiveBulkAction, or handle archiving model events and authorization. Do not use for: Laravel SoftDeletes (a different feature), or writing tests for archiving (use `writing-tests`).'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Archiving Records

Archiving is a soft alternative to deletion: it hides records from active use without removing them. It is modelled after `SoftDeletes` and uses an `archived_at` timestamp, with one crucial difference — **archived records are included in queries by default**. You must explicitly exclude them with `withoutArchived()` wherever they should not appear. This suits records that should no longer be selectable but must remain in historical data, reports, and existing associations.

## Preparing the Model

Add a nullable `archived_at` column, then apply the trait (it may be combined with `SoftDeletes` and automatically casts `archived_at` to a datetime):

```php
Schema::table('projects', function (Blueprint $table) {
    $table->timestamp('archived_at')->nullable();
});
```

```php
use CanyonGBS\Common\Models\Concerns\CanBeArchived;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use CanBeArchived;
}
```

## Archiving and Querying

```php
$project->archive();            // returns false if cancelled by an event listener
$project->unarchive();
$project->isArchived();
$project->archiveQuietly();     // without firing model events
$project->unarchiveQuietly();

Project::query()->withoutArchived()->get();   // exclude archived — you must call this explicitly
Project::query()->onlyArchived()->get();       // only archived
Project::query()->get();                        // all records (archived included by default)

Project::query()->where('completed', true)->archive();   // bulk archive: fires no model events, so no hooks and no audits
Project::query()->onlyArchived()->unarchive();            // bulk unarchive: fires no model events, so no hooks and no audits
```

Use the bulk query only when skipping hooks and audits is intended. Otherwise archive per record:

```php
Project::query()->withoutArchived()->eachById(function (Project $record): void {
    $record->archive();
});
```

The callback must be block-bodied and return `void`: `archive()` returns `false` when a listener cancels it, and `eachById` stops at the first `false`, so an arrow function would silently skip the remaining records.

If the volume makes per-record archiving a real concern, ask the user rather than choosing.

## Archived-but-Still-Used Records

Some archived records are still referenced elsewhere (e.g. a project type archived but still assigned to active projects). `withoutArchivedAndUnused()` hides records that are **both archived and unused**, while keeping archived-but-used ones visible. Define `used()` to declare what "in use" means:

```php
use Illuminate\Database\Eloquent\Builder;

class ProjectType extends Model
{
    use CanBeArchived;

    public function used(Builder $query): void
    {
        $query->whereHas('projects');
    }
}

ProjectType::query()->withoutArchivedAndUnused()->get();
```

An optional `isUsed()` is the record-level counterpart. It must agree with `used()`, and it changes the Filament `ArchiveAction` to delete unused records instead of archiving them (below). Keep it efficient — read the value eager-loaded by `withExists()` and memoize it, so it never runs more than one query:

> **Adding `isUsed()` is what grants users the ability to delete.** Whether a model can be archived, deleted, or both must always be a deliberate, conscious decision — never a default. Defining `isUsed()` opts the model into deletion of unused records; omitting it means the `ArchiveAction` can _only ever archive_, never delete. So if Product asks for archiving and does not mention deletion, do **not** add `isUsed()` — leave it off so users can only archive. Add `isUsed()` only when deletion of unused records has been explicitly requested.

```php
public function isUsed(): bool
{
    if (! isset($this->projects_exists)) {
        $this->projects_exists = $this->projects()->exists();

        $this->syncOriginalAttribute('projects_exists');
    }

    return (bool) $this->projects_exists;
}
```

Syncing the original keeps the attribute clean, as `withExists()` loads it, so a later `save()` does not try to write a `projects_exists` column.

In tables where `isUsed()` runs per row, eager-load the check to avoid N+1: `$table->modifyQueryUsing(fn (Builder $query) => $query->withExists('projects'))`.

## Model Events

`archiving`, `archived`, `unarchiving`, and `unarchived` fire around the operation; returning `false` from `archiving` or `unarchiving` cancels it.

`archive()` and `unarchive()` only write `archived_at` and `updated_at`: they do not fire Eloquent's save events or persist other pending changes. Afterwards, `wasChanged()`, `getChanges()` and `getPrevious()` report the columns they wrote, as after `save()`.

## Auditing

Models that implement owen-it's `Auditable` record one `archived` or `unarchived` audit per `archive()` / `unarchive()` that changes the archived-at column, recording its old and new values. A call that leaves the value unchanged, such as unarchiving a record that is not archived, records nothing. No per-model code is needed. Common adds both events to `audit.events` at boot.

- To opt a model out, set `$auditEvents` without them. owen-it matches events with an unanchored regex, so listing `archived` also matches `unarchived`.
- Override `getArchivedEventAttributes()` / `getUnarchivedEventAttributes()` to change one event's payload, or `getArchiveAuditAttributes()` to change both.
- The `*Quietly()` methods and the query-builder `archive()` / `unarchive()` record nothing. Bulk queries fire no model events, the same as Eloquent's bulk `update()` / `delete()`.
- Writes made in an `archiving` hook are audited separately, as `updated`.

## Authorization

The actions authorize with the policy's `archive` method when it is defined, otherwise `delete`. Deleting an unused record always uses `delete`. Always define `delete` and `deleteAny`.

Bulk actions check `deleteAny` once. When `archive` or `delete` has record-dependent logic, add `->authorizeIndividualRecords('delete')` so each selected record is checked, and return Filament's `DenyResponse` so the notifications explain skipped records. See the `structuring-filament-code` skill (Bulk Actions).

## Filament Actions

`ArchiveAction` and `UnarchiveAction` are header actions for `EditRecord` / `ViewRecord` pages or table record actions on any resource page or relation manager; `ArchiveBulkAction` and `UnarchiveBulkAction` are table bulk actions. All four live in `CanyonGBS\Common\Filament\Actions`.

```php
protected function getHeaderActions(): array
{
    return [
        ArchiveAction::make(),
        UnarchiveAction::make(),
    ];
}

$table
    ->recordActions([
        ArchiveAction::make(),
        UnarchiveAction::make(),
    ])
    ->toolbarActions([
        ArchiveBulkAction::make(),
        UnarchiveBulkAction::make(),
    ]);
```

- The record actions are hidden unless they apply, and the bulk actions skip selected records that are already archived or unarchived. `ArchiveAction` redirects to the index on success from an Edit/View page, and stays on the page in a table.
- When `isUsed()` returns `false`, `ArchiveAction` deletes the record instead (closures can branch on `shouldDeleteInsteadOfArchive()`). `ArchiveBulkAction` likewise deletes selected records that are not `used()`.
- The bulk actions archive and unarchive per record by default. `->fetchSelectedRecords(false)` switches them to a single bulk query, which skips hooks and audits and deletes unused records with a bulk `delete()`. Don't add it to a model with archiving hooks or audits without asking the user.
- They authorize through the resource page or relation manager they are on, and are hidden in read-only relation managers. Anywhere else, pass your own `authorize()`.

---

Related: `structuring-filament-code` (bulk action authorization and notifications) and `writing-tests` (for testing archivable models and the Filament actions).
