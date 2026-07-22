# Archiving

This guide covers how to add archiving support to Eloquent models and Filament resources. Archiving is a soft alternative to deletion -- it hides records from normal use without removing them from the database.

---

## Introduction

The archiving system is modeled after Laravel's `SoftDeletes`. It uses an `archived_at` timestamp column to mark records as archived. The key difference from soft deletes is that **archived records are included in queries by default** -- you must explicitly exclude them with `withoutArchived()` wherever needed.

This makes archiving suitable for records that should no longer be selectable or actively used, but should still appear in historical data, reports, and existing associations.

---

## Preparing the database

Add an `archived_at` column to your model's table:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
```

---

## Adding the trait to a model

Use the `CanBeArchived` trait on your Eloquent model. It can be combined with `SoftDeletes`:

```php
use CanyonGBS\Common\Models\Concerns\CanBeArchived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use CanBeArchived;
    use SoftDeletes;
}
```

The trait automatically casts the `archived_at` column to a `datetime`.

---

## Archiving and unarchiving records

```php
// Archive a single record
$project->archive();

// Unarchive a single record
$project->unarchive();

// Check if a record is archived
$project->isArchived(); // true / false

// Archive/unarchive without firing model events
$project->archiveQuietly();
$project->unarchiveQuietly();
```

The `archive()` and `unarchive()` methods return `false` if the operation was cancelled by a model event listener. They return `true` on success.

---

## Query scopes

The `CanBeArchived` trait registers an `ArchivingScope` that adds builder macros. Unlike `SoftDeletes`, archived records **are included by default** in all queries. You must explicitly filter them out.

```php
// Exclude archived records (you must call this explicitly)
Project::query()->withoutArchived()->get();

// Return only archived records
Project::query()->onlyArchived()->get();

// All records (the default -- no scope method needed)
Project::query()->get();
```

### Bulk operations

```php
// Bulk archive matching records
Project::query()->where('completed', true)->archive();

// Bulk unarchive matching records
Project::query()->onlyArchived()->unarchive();
```

### Excluding archived and unused records

Some archived records may still be "in use" -- for example, a project type that is archived but still assigned to active projects. The `withoutArchivedAndUnused()` scope excludes records that are both archived **and** unused, while keeping archived-but-still-used records visible.

#### Defining `used()`

Add a `used()` method to your model that accepts a `Builder` and adds constraints to scope it to records considered "in use":

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
```

The archiving scope passes a query builder to this method. The constraints you add determine which archived records are considered "in use" and should remain visible.

#### Using the scope

```php
// Exclude records that are both archived and unused
ProjectType::query()->withoutArchivedAndUnused()->get();
```

This returns:

- All records that are **not archived**.
- Archived records that are still **"used"** (matched by the `used()` query).

It excludes:

- Archived records that are **not "used"**.

### Checking whether a single record is used

The `used()` method filters queries. Its record-level counterpart is an optional `isUsed()` method, which reports whether an individual record is still referenced:

```php
class ProjectType extends Model
{
    use CanBeArchived;

    public function used(Builder $query): void
    {
        $query->whereHas('projects');
    }

    public function isUsed(): bool
    {
        return (bool) ($this->projects_exists ??= $this->projects()->exists());
    }
}
```

The two methods should agree on what "used" means. Defining `isUsed()` changes the behavior of the `ArchiveAction` Filament component, which deletes unused records instead of archiving them ([see below](#filament-archive-action)).

#### Keeping `isUsed()` efficient

`isUsed()` may be called several times per request, so implement it with the pattern above instead of querying the relationship directly. `$this->projects_exists` is the attribute that Eloquent's `withExists('projects')` populates: when the record was retrieved with `withExists()`, the check reads the eager-loaded value and runs no query at all. Otherwise, the first call runs a single `exists` query and stores the result in the same attribute, so repeated calls never query again.

In a table context, where `isUsed()` may be evaluated for every row, always eager-load the existence check with `modifyQueryUsing()` to avoid one query per record:

```php
use Illuminate\Database\Eloquent\Builder;

$table->modifyQueryUsing(
    fn (Builder $query) => $query->withExists('projects'),
)
```

This can be combined with other query changes, such as `withoutArchived()`.

One caveat of the memoizing pattern: when the value is computed lazily (without `withExists()`), the result is stored as a "dirty" attribute on the model. Avoid calling `save()` on the same model instance afterwards, as the attribute does not correspond to a real database column.

---

## Model events

Four model events are available. Returning `false` from an `archiving` or `unarchiving` listener cancels the operation.

```php
use App\Models\Project;

Project::archiving(function (Project $project) {
    // Runs before archiving. Return false to cancel.
});

Project::archived(function (Project $project) {
    // Runs after archiving.
});

Project::unarchiving(function (Project $project) {
    // Runs before unarchiving. Return false to cancel.
});

Project::unarchived(function (Project $project) {
    // Runs after unarchiving.
});
```

---

## Authorization

The `ArchiveAction` Filament component calls `can('delete', $record)` to determine if the action should be available, so define a `delete` method on your model's policy. The same ability is checked whether the action archives or deletes the record -- archiving is a soft alternative to deletion, so it is governed by the same permission.

```php
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function delete(User $user, Project $project): Response
    {
        return $user->can('projects.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete this project.');
    }
}
```

---

## Filament: archive action

`ArchiveAction` is a header action for Filament `EditRecord` or `ViewRecord` pages. It shows a confirmation modal and archives the record on confirmation.

```php
use CanyonGBS\Common\Filament\Actions\ArchiveAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;

class EditProject extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            ArchiveAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
```

The action:

- Is hidden when the record is already archived.
- Authorizes via the policy's `delete` method.
- Redirects to the resource's index page on success.

### Deleting unused records instead of archiving

Archiving exists to preserve records that are still referenced elsewhere. If a record is not referenced by anything, there is nothing to preserve, and it can safely be deleted outright.

If the model defines an optional `isUsed()` method ([see above](#checking-whether-a-single-record-is-used)), `ArchiveAction` becomes a dual-mode action:

- **`isUsed()` returns `true`** -- the record is still referenced, so the action looks and behaves exactly as described above: it archives the record.
- **`isUsed()` returns `false`** -- the record is unreferenced, so the action looks and behaves like Filament's `DeleteAction` instead: it is labelled "Delete" with a `danger` color and trash icon, calls `$record->delete()`, and is hidden when the record is soft-deleted (rather than when it is archived).

If the model does not define `isUsed()`, the action always archives.

Closures passed to the action can check which mode applies to the current record by calling `shouldDeleteInsteadOfArchive()` on the action:

```php
ArchiveAction::make()
    ->modalDescription(fn (ArchiveAction $action): ?string => $action->shouldDeleteInsteadOfArchive()
        ? 'This record is not in use, so it will be permanently deleted.'
        : null)
```

### Custom authorization or redirect

The default `authorize()` and `successRedirectUrl()` callbacks expect the action to live on an `EditRecord` or `ViewRecord` page. If you need to use the action elsewhere, override them:

```php
ArchiveAction::make()
    ->authorize(fn (Model $record): bool => Gate::allows('delete', $record))
    ->successRedirectUrl('/projects');
```

### Customizing the operation

Like Filament's `DeleteAction`, the archive/delete operation itself can be replaced with `using()`. Return `true` on success or `false` to report a failure:

```php
ArchiveAction::make()
    ->using(function (Model $record, ArchiveAction $action): bool {
        if ($action->shouldDeleteInsteadOfArchive($record)) {
            return (bool) $record->delete();
        }

        return $record->archive();
    })
```

---

## Filament: bulk archive action

`ArchiveBulkAction` is a table bulk action that processes all selected records:

```php
use CanyonGBS\Common\Filament\Actions\ArchiveBulkAction;

public static function table(Table $table): Table
{
    return $table
        ->toolbarActions([
            ArchiveBulkAction::make(),
        ]);
}
```

Like the single-record `ArchiveAction` uses `isUsed()`, the bulk action decides between archiving and deleting -- but at the query level, using the model's `used()` scope:

- If the model defines `used()`, selected records that are "used" are archived, and the rest are permanently deleted. The confirmation modal warns the user about this.
- If the model does not define `used()`, every selected record is archived.
- If the model does not use the `CanBeArchived` trait, an exception is thrown.

The notification sent when the action finishes counts each operation separately -- for example, "5 archived, 3 deleted". If some records could not be processed, failure messages are appended below the counts.

Closures passed to the action can check whether unused records will be deleted by calling `shouldDeleteUnusedRecords()` on the action.

### Processing modes

By default, records are fetched and processed one at a time, so `archiving`/`deleting` model events fire for each record. An operation cancelled by an event listener counts as a failure in the final notification. To determine which records to archive, the `used()` scope is applied to the selection in a single query up front, so the split does not cost a query per record.

To avoid loading a large selection into memory at once, records can be fetched in chunks:

```php
ArchiveBulkAction::make()
    ->chunkSelectedRecords(100)
```

For the fastest option, `fetchSelectedRecords(false)` switches to two bulk statements (one `UPDATE`, one `DELETE`) with no model hydration at all, but model events do not fire, and per-record failure reporting is unavailable:

```php
ArchiveBulkAction::make()
    ->fetchSelectedRecords(false)
```

### Bulk authorization

By default, the action is authorized with the policy's `deleteAny` method -- one check for the whole selection, consistent with Filament's `DeleteBulkAction`.

To also check the `delete` policy method for each individual record, use `authorizeIndividualRecords()`:

```php
ArchiveBulkAction::make()
    ->authorizeIndividualRecords('delete')
```

Records that fail the check are skipped, and the final notification reports how many were skipped alongside the archived/deleted counts. Individual record authorization only applies in the default (fetched) processing mode.

---

## Filament: filtering archived records from lists

On list pages, use `modifyQueryUsing()` on the table to exclude archived records:

```php
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->withoutArchived(),
            );
    }
}
```

---

## Filament: filtering archived records from select dropdowns

When a `Select` field references a `BelongsTo` relationship on an archivable model, archived records should be hidden from the dropdown options. However, if the currently-selected record is archived, it should still appear so the form displays correctly.

Use `HideDeletedAndArchivedExceptSelectedFromSelectOptions` for models that use both `SoftDeletes` and `CanBeArchived`:

```php
use CanyonGBS\Common\Filament\Support\HideDeletedAndArchivedExceptSelectedFromSelectOptions;

Select::make('project_id')
    ->relationship(
        'project',
        'name',
        app(HideDeletedAndArchivedExceptSelectedFromSelectOptions::class)(...),
    )
```

---

## Filtering archived records in APIs

Apply `withoutArchived()` in controllers or API queries to exclude archived records from public-facing responses:

```php
class ProjectController extends Controller
{
    public function index()
    {
        return Project::query()
            ->withoutArchived()
            ->orderBy('name')
            ->get();
    }
}
```
