---
name: archiving-records
description: 'Use when adding or working with archiving in a Canyon GBS app — a soft alternative to deletion using an `archived_at` timestamp where archived records are INCLUDED in queries by default (unlike SoftDeletes). Trigger whenever you add the `CanBeArchived` trait to a model, add an `archived_at` column, use archive()/unarchive()/isArchived() or the withoutArchived / onlyArchived / withoutArchivedAndUnused query scopes, define used()/isUsed() for archived-but-still-used records, wire the Filament ArchiveAction / ArchiveBulkAction, or handle archiving model events and authorization. Do not use for: Laravel SoftDeletes (a different feature), or writing tests for archiving (use writing-tests).'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Archiving Records

Archiving is a soft alternative to deletion: it hides records from active use without removing them. It is modelled after `SoftDeletes` and uses an `archived_at` timestamp, with one crucial difference — **archived records are included in queries by default**. You must explicitly exclude them with `withoutArchived()` wherever they should not appear. This suits records that should no longer be selectable but must remain in historical data, reports, and existing associations.

## Preparing the model

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

## Archiving and querying

```php
$project->archive();            // returns false if cancelled by an event listener
$project->unarchive();
$project->isArchived();
$project->archiveQuietly();     // without firing model events
$project->unarchiveQuietly();

Project::query()->withoutArchived()->get();   // exclude archived — you must call this explicitly
Project::query()->onlyArchived()->get();       // only archived
Project::query()->get();                        // all records (archived included by default)

Project::query()->where('completed', true)->archive();   // bulk archive
Project::query()->onlyArchived()->unarchive();            // bulk unarchive
```

## Archived-but-still-used records

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

```php
public function isUsed(): bool
{
    return (bool) ($this->projects_exists ??= $this->projects()->exists());
}
```

In tables where `isUsed()` runs per row, eager-load the check to avoid N+1: `$table->modifyQueryUsing(fn (Builder $query) => $query->withExists('projects'))`. When the value is computed lazily it is stored as a dirty attribute, so avoid calling `save()` on that instance afterwards.

## Model events

`archiving`, `archived`, `unarchiving`, and `unarchived` fire around the operation; returning `false` from `archiving` or `unarchiving` cancels it.

## Authorization

Archiving is governed by the same permission as deletion: the Filament actions call `can('delete', $record)`, so define a `delete` method on the model's policy.

## Filament actions

`ArchiveAction` (from `CanyonGBS\Common\Filament\Actions\ArchiveAction`) is a header action for `EditRecord` / `ViewRecord` pages; `ArchiveBulkAction` is its table bulk-action counterpart.

```php
use CanyonGBS\Common\Filament\Actions\ArchiveAction;

protected function getHeaderActions(): array
{
    return [
        ArchiveAction::make(),
    ];
}
```

`ArchiveAction`:

- Is hidden when the record is already archived.
- Authorizes via the policy's `delete` method and redirects to the index on success.
- Becomes a **delete** action (Filament `DeleteAction` behaviour — "Delete" label, danger colour, `$record->delete()`) when the model defines `isUsed()` and it returns `false`; otherwise it archives. Closures can branch on `shouldDeleteInsteadOfArchive()`.

Override `authorize()`, `successRedirectUrl()`, or `using()` when the action is used outside a standard Edit/View page or needs custom behaviour.

---

Related skill: `writing-tests` (for testing archivable models and the Filament actions).
