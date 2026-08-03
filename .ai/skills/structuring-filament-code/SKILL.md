---
name: structuring-filament-code
description: 'Use when writing or reviewing Filament v4 code in a Canyon GBS app and want it maintainable and small — organizing a resource''s form, infolist, and table into separate `configure()` classes; extracting fields, columns, filters, or actions into their own classes; deciding between a static `make()` factory and extending a component base class; or splitting a schema per page instead of branching by operation/context. Trigger whenever a Filament schema/table/action definition grows beyond a few lines or gains a complex closure, when adding a resource page schema, or when you see per-page branching (`hiddenOn`/`visibleOn`/`disabledOn`, `$operation`, `$livewire instanceof`). Do not use for: non-Filament PHP (use `laravel-best-practices`), Filament file uploads (use `handling-file-uploads`), settings-page wiring (use `managing-settings`), or writing tests (use `writing-tests`).'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Structuring Filament Code

The goal is to keep every Filament file **as small as possible** — without creating pointless ones. Two forces balance each other: don't let one class hold several definitions, and don't split out a file that is only used in one place. Practical heuristic: **a class should define at most one schema/table**. A resource declares a form, an infolist, and a table — three definitions — so each moves to its own `configure()` class; a page that declares only one schema keeps it inline. Whatever the container, pull non-trivial fields, columns, filters, and actions into their own classes so the definition reads as a short list.

## Resources: Separate `configure()` Classes (v4)

A resource's form, infolist, and table each live in their own class with a static `configure()` method. The resource only delegates.

```
Resources/<Name>/
  <Name>Resource.php
  Schemas/<Name>Form.php        # configure(Schema $schema): Schema
  Schemas/<Name>Infolist.php    # configure(Schema $schema): Schema
  Tables/<Name>Table.php        # configure(Table $table): Table
```

```php
// DepartmentResource.php — thin delegation
public static function form(Schema $schema): Schema
{
    return DepartmentForm::configure($schema);
}

public static function table(Table $table): Table
{
    return DepartmentsTable::configure($table);
}
```

```php
// Schemas/DepartmentForm.php
class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            NameInput::make(),
            // ...
        ]);
    }
}
```

**Exception:** relation managers and `ManageRelatedRecords` pages that do **not** have a backing resource define their form/table **inline** (there is no resource to host separate schema classes). If a relation manager surfaces records that *do* have their own resource, reuse that resource's schema/table classes instead of duplicating them.

## Extract Non-Trivial Components into Their Own Classes

Rule of thumb: if a field, column, filter, or action definition is **more than a few lines**, or takes a **complex closure argument**, give it its own class. This keeps the `configure()` classes to a readable list.

Co-locate extracted classes under the resource, or share them more widely as their scope grows:

```
Resources/<Name>/Schemas/Components/NameInput.php   # resource-specific
Resources/<Name>/Tables/Columns/...                 # resource-specific
app/Filament/Tables/Columns/StatusColumn.php        # app-wide
```

Truly cross-app components live in `canyongbs/common`.

## Prefer a Static `make()` Factory

**Default pattern:** a plain class with `public static function make(): <ComponentType>` that builds and returns a **configured base component**. Pass every **mandatory** input as a `make()` parameter, so a caller cannot construct it without them. Chain extra, page-specific config at the call site.

```php
namespace App\Filament\Resources\Posts\Schemas\Components;

use Filament\Forms\Components\TextInput;

class NameInput
{
    public static function make(): TextInput
    {
        return TextInput::make('name')
            ->required()
            ->maxLength(255);
    }
}
```

Pass required context in as parameters:

```php
class AuthorSelect
{
    public static function make(Organization $organization): Select
    {
        return Select::make('author_id')
            ->options($organization->users()->pluck('name', 'id'))
            ->required();
    }
}

// Call site — chain extra config as needed:
AuthorSelect::make($organization)->columnSpanFull();
```

Actions follow the same shape — a factory returning a configured `Action`:

```php
class AboutAction
{
    public static function make(): Action
    {
        return Action::make('about')
            ->label('About')
            ->modalContent(fn () => view('filament.actions.about'));
    }
}
```

## Extend the Base Class Only in Extreme Cases

Extending a component base class with a `setUp()` method should be **rare**. Use it only for a **generic, reusable** component that:

- works **out of the box with no required parameters**,
- offers **optional** fluent config methods, and
- needs to **store config state** in properties.

An extended class can be instantiated with no arguments, so it **cannot enforce mandatory inputs** — that is exactly why a `make()` factory is the default. Reach for `extends` only when a `make()` factory would be awkward: a generic, OOTB action that works with no configuration but exposes **optional** fluent config methods backed by config properties.

```php
class ArchiveAction extends Action
{
    protected bool | Closure $shouldRedirectToIndex = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Archive');
        $this->icon(Heroicon::ArchiveBox);
        $this->requiresConfirmation();

        $this->action(function (Model $record): void {
            $record->archive();
        });

        $this->successRedirectUrl(fn (ArchiveAction $action): ?string => $action->evaluate($action->shouldRedirectToIndex)
            ? $action->getResource()::getUrl('index')
            : null);
    }

    // Optional config method, stored in a config property — the whole reason to extend
    public function shouldRedirectToIndex(bool | Closure $condition = true): static
    {
        $this->shouldRedirectToIndex = $condition;

        return $this;
    }
}
```

Used out of the box as `ArchiveAction::make()`, or configured with `ArchiveAction::make()->shouldRedirectToIndex(false)`.

## Inject the Current Object in Closures — Never `$this`

Inside a Filament closure, get the component, action, or record through a **typed parameter** rather than `$this`. Filament injects them by type-hint (`Action $action`, `Component $component`, `Get $get`, `Set $set`, `Model $record`, …). This keeps closures self-contained and correct even when defined inside a `setUp()` or a `configure()` class.

Incorrect:

```php
->visible(fn (): bool => $this->getRecord()->is_active)
```

Correct:

```php
->visible(fn (Component $component): bool => $component->getRecord()->is_active)
```

Within an extended class, the injected parameter is that class's own type, so closures can still read its config properties (`fn (ArchiveAction $action) => $action->shouldRedirectToIndex`). Direct method calls in `setUp()` that are not inside a closure (`$this->label(...)`) are fine — the rule is about closures.

## Put a Single-Use Schema on the Page

Extract a schema into its own class only when it is **shared** across more than one place, or when it would otherwise be one of several definitions in the same class (the reason a resource's form, infolist, and table each get a class). A page that declares **only one** schema does not need a separate file for it — define it **inline** on the page. Don't create a `CreateRoleForm` / `EditRoleForm` class that is referenced in a single place.

```php
// CreateRole.php — the page owns its form inline
public function form(Schema $schema): Schema
{
    return $schema->components([
        NameInput::make(),
        GuardNameSelect::make(),
    ]);
}
```

Extract it to a shared class only when another page, action, or place needs the **same** schema. Either way, keep the definition short by pulling non-trivial fields into component classes.

## No Per-Context Branching

Never branch a shared schema by page or operation. Do **not** use `hiddenOn()` / `visibleOn()` / `disabledOn()`, `$operation`-based closures, `$livewire instanceof ...`, or an injected `$context` to change behaviour per page. Give each page its own schema — inline on the page — referencing shared component classes and chaining the differences.

Incorrect — one schema branching across pages:

```php
Select::make('guard_name')
    ->disabled(fn (string $operation): bool => $operation === 'edit');
```

Correct — each page's inline schema reuses shared components and differs only by chained config:

```php
// CreateRole.php
public function form(Schema $schema): Schema
{
    return $schema->components([GuardNameSelect::make()]);
}

// EditRole.php
public function form(Schema $schema): Schema
{
    return $schema->components([GuardNameSelect::make()->disabled()]);
}
```

## Checklist

- A class defines at most one schema/table: a resource's form / infolist / table each become separate `configure()` classes; the resource just delegates.
- Single-use schema → inline on the page (`public function form(Schema $schema)`); extract to a class only when shared across 2+ places. (Relation managers / `ManageRelatedRecords` without a resource also define inline.)
- Any multi-line or complex-closure field / column / filter / action → its own class.
- `public static function make(<mandatory params>)` factory by default; `extends` + `setUp()` only for OOTB, no-required-param, optionally-configurable components.
- Inject the object into closures via a typed parameter (`Action $action`, `Component $component`, …) — never `$this`.
- No `hiddenOn` / `visibleOn` / `disabledOn` / `$operation` / `$livewire instanceof` / `$context` branching — each page owns its schema, reusing shared component classes.
