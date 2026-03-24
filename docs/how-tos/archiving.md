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

Define an `archive` method on your model's policy. The `ArchiveAction` Filament component calls `can('archive', $record)` to determine if the action should be available.

```php
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function archive(User $user, Project $project): Response
    {
        // Example: only allow archiving if the project has associated data
        // that would be lost by deletion.
        if (! $project->tasks()->exists()) {
            return Response::deny('Delete this project instead of archiving it.');
        }

        return $user->can('projects.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to archive this project.');
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
- Authorizes via the policy's `archive` method.
- Redirects to the resource's index page on success.

### Custom authorization or redirect

The default `authorize()` and `successRedirectUrl()` callbacks expect the action to live on an `EditRecord` or `ViewRecord` page. If you need to use the action elsewhere, override them:

```php
ArchiveAction::make()
    ->authorize(fn (Model $record): bool => Gate::allows('archive', $record))
    ->successRedirectUrl('/projects');
```

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
