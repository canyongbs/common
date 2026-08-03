---
name: writing-tests
description: 'Use this skill whenever writing, editing, fixing, or refactoring automated tests in a Canyon GBS Laravel application — including tests for Filament resources, pages, relation managers, actions, jobs, models, enums, console commands, and HTTP controllers. Trigger whenever a test is created or changed, a test breaks after a code change, assertions or datasets are added, or PHPUnit is converted to Pest. Covers test file placement and naming (mirroring the source namespace), one-file-per-class organization, describe() grouping, it()/expect() style, datasets, Filament Livewire testing helpers, Worksome RequestFactory usage, shared Pest.php helpers, and Pest 4 features. This is the single authoritative testing skill for these apps — it supersedes generic Pest guidance. Do not use for non-test PHP code, factories that are not request factories, seeders, or migrations.'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Writing Tests

All tests are written with **Pest**. These conventions are strict and consistent across the codebase — match them exactly, and always check sibling test files before writing a new one.

## Documentation

Use `search-docs` for Pest 4 and Filament testing APIs before writing unfamiliar assertions.

## What to assert

Test behaviour, not framework or configuration presence. A test should exercise your code and fail when your logic breaks.

- **Anything driven by a user-supplied closure needs a test** — `getStateUsing()`, `formatStateUsing()`, `state()`, `visible()` / `hidden()`, `disabled()`, action `visible()` / `authorize()`, a table query modification, conditional validation, etc. If you wrote a closure, assert its effect.
- **Do not assert mere structural presence** — that a column or field simply exists. It restates the resource definition, breaks on trivial edits, and catches no real bug. Assert observable behaviour instead.
- Assert observable outcomes: access / authorization, resolved state and formatting, records shown / hidden, sort and search results, redirects, database writes, notifications and other side effects.
- **When asserting a side effect, establish the starting state first.** Confirm (or deliberately arrange) that the effect has _not_ already happened before the action runs, then assert it did afterwards — so the test proves the action caused it rather than passing on pre-existing state. For example, `assertDatabaseMissing(...)` before and `assertDatabaseHas(...)` after; or `expect($model->status)->toBe(Status::Pending)` before → act → `expect($model->refresh()->status)->toBe(Status::Active)` after.
- Assert column visibility only when it is **conditional** (shown in some cases, hidden in others) with `assertTableColumnVisible()` / `assertTableColumnHidden()` — never a static `assertTableColumnExists()` list.

## File & Directory Placement

The test file's path and name **mirror the source class's namespace and file name**, with a `Test.php` suffix.

| Source                                                                   | Test file                                                                      |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------------ |
| `app/Models/User.php`                                                    | `tests/Models/UserTest.php`                                                    |
| `app/Actions/GenerateOneTimeLoginCode.php`                               | `tests/Actions/GenerateOneTimeLoginCodeTest.php`                               |
| `app/Console/Commands/ConnectOlympus.php`                                | `tests/Console/Commands/ConnectOlympusTest.php`                                |
| `app/Filament/Resources/Users/Pages/ListUsers.php`                       | `tests/Filament/Resources/Users/Pages/ListUsersTest.php`                       |
| `app/Filament/Resources/Users/RelationManagers/RolesRelationManager.php` | `tests/Filament/Resources/Users/RelationManagers/RolesRelationManagerTest.php` |

### One test file per class

- There is **exactly one test file per class under test**. Never create extra files to test a specific part of a class (e.g. `UserResourceValidationTest`, `ListUsersSortTest`). All tests for a class live in that class's single test file, organized with `describe()` blocks.
- When adding tests for behaviour that already has a test file, **add to the existing file** — do not create a new one.
- A Filament **resource is not a single class**: each Page (`ListUsers`, `CreateUser`, `EditUser`, `ViewUser`) and each RelationManager is its own class, so each gets its own test file mirroring its path. This is one-file-per-class, not splitting a resource across files.

### Creating a test file

Create the file manually at the mirrored path. Do **not** scaffold with `php artisan make:test` — it generates into `tests/Feature` (or `tests/Unit`), which does not match this structure. There is no `Feature`/`Unit` split; the directory tree mirrors the source namespace.

## Test Structure

### Case names (`it()`)

Use `it()` by default — descriptions read as `it('<present-tense phrase>')`. Reach for `test()` only when an `it('...')` description would be grammatically awkward (e.g. `test('the pipeline resolves handlers in order')`). Wrap code identifiers (permission names, action classes, fields, class names) in backticks. Follow these templates:

| Case                   | Template                                                                                                                                     |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Render                 | `it('can render the <page-type> <resource> page')` — e.g. `it('can render the list users page')`                                             |
| List records           | `it('can list <models>')`                                                                                                                    |
| Columns                | `it('has the expected columns')`                                                                                                             |
| Sort / search          | `it('can sort by column')` / `it('can search by column')`                                                                                    |
| Create                 | `it('can create a <model>')`                                                                                                                 |
| Update                 | `it('can update a <model>')`                                                                                                                 |
| Display data           | `it('displays the <model> data')`                                                                                                            |
| Attach / detach        | `it('can attach a <related> to a <owner>')` / `it('can detach a <related> from a <owner>')` / `it('bulk detaches <related> from a <owner>')` |
| Associate / dissociate | `it('associates a <related> to the <owner>')` / `it('dissociates a <related> from the <owner>')`                                             |
| Validation             | `it('validates the inputs')` with a `->with([...])` dataset                                                                                  |
| Action visibility      | ``it('shows the `<Action>` action ...')`` / ``it('hides the `<Action>` action ...')``                                                        |
| Negative behaviour     | `it('does not <disallowed behaviour>')`                                                                                                      |
| Deny access            | ``it('denies access to the <page> without the `<permission>` permission')``                                                                  |
| Allow access           | ``it('allows access to the <page> with the `<permission>` permission')``                                                                     |
| Admin access           | `it('allows a super admin to access the <page>')` / `it('allows a partner admin to access the <page> without any explicit permissions')`     |

Validation dataset keys are `'<field> <rule>'` — `'name required'`, `'name max'`, `'email unique'`.

### File ordering (strict)

Order every Filament resource page / relation-manager test file top to bottom:

1. `beforeEach()` — authentication + panel setup.
2. **Top-level `it()`s** (outside any `describe()`) for the happy path, in this order: render → core CRUD / display → side effects (notifications, tenant association) → `it('validates the inputs')`.
3. **Feature `describe()` blocks** grouping cross-cutting concerns, e.g. `tenant scoping`, `deletion`, `admin visibility`, `impersonation`, `filters`.
4. **`describe('authorization')` — ALWAYS LAST.** Every access-control test lives here; never place an authorization `it()` at the top level or inside another block.

```php
beforeEach(function () {
    $this->actingAs(createUserWithPermissions(
        UserPermission::View,
        UserPermission::Create,
        UserPermission::Update,
    ));

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

// 2 — happy path, top level
it('can render the create user page', function () { /* ... */ });
it('can create a user', function () { /* ... */ });
it('validates the inputs', function () { /* ... */ })->with([/* ... */]);

// 3 — feature groups
describe('deletion', function () { /* ... */ });

// 4 — authorization, always last
describe('authorization', function () {
    it('denies access to the view page without the `view` permission', function () {
        $this->actingAs(createUserWithPermissions());

        $record = User::factory()->create();

        $this->get(UserResource::getUrl('view', ['record' => $record]))
            ->assertForbidden();
    });
});
```

## Assertions

Chain expectations with `expect()->and()`, and prefer specific response assertions over `assertStatus()`.

```php
expect($record->user_id)->toBe($user->getKey())
    ->and(Hash::check($code, $record->code))->toBeTrue()
    ->and($record->expires_at->toDateTimeString())->toBe(now()->addDay()->toDateTimeString());
```

| Use                     | Instead of          |
| ----------------------- | ------------------- |
| `assertSuccessful()`    | `assertStatus(200)` |
| `assertNotFound()`      | `assertStatus(404)` |
| `assertForbidden()`     | `assertStatus(403)` |
| `assertUnprocessable()` | `assertStatus(422)` |

Import Pest Laravel functions where used, e.g. `use function Pest\Laravel\{actingAs, postJson, artisan};` and `use function Pest\Livewire\livewire;`.

For existence checks (e.g. delete / bulk-action tests) use `assertModelExists($model)` / `assertModelMissing($model)`; use `assertDatabaseHas(...)` / `assertDatabaseMissing(...)` when asserting specific column values changed by a side effect.

## Datasets

Use datasets for repetitive cases — especially validation. Pass a labelled associative array to `->with()`; each key becomes the case name. Wrap entries that build objects in `fn () => [...]` so they resolve lazily.

```php
it('validates the inputs', function (CreateUserRequestFactory $data, array $errors) {
    $request = CreateUserRequestFactory::new($data)->create();

    livewire(CreateUser::class)
        ->fillForm($request)
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'name required' => fn () => [
        CreateUserRequestFactory::new()->state(['name' => null]),
        ['name' => 'required'],
    ],
    'email unique' => fn () => [
        CreateUserRequestFactory::new()->state(['email' => 'taken@test.com']),
        ['email' => 'unique'],
    ],
]);
```

Simple scalar datasets are also fine: `->with(['id', 'created_at', 'updated_at'])`.

## Shared Setup & Helpers

- Global setup lives in `tests/Pest.php`. In single-database apps it binds the base `TestCase` with `RefreshDatabase` for all tests: `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);`. Apps with split landlord/tenant suites bind a `TestCase` per suite instead (see **Multi-tenancy**).
- `tests/TestCase.php` stays minimal (empty body) — put shared logic in `Pest.php` helper functions, not the base class.
- Authentication and tenancy use the shared helper functions (do not reinvent them per file):
    - `createUserWithPermissions(UserPermission::View, ...)` — user granted exactly those permissions via a fresh role.
    - `createUserWithRoleNamed(Authenticatable::SUPER_ADMIN_ROLE)` — user assigned a named role.
    - `setCurrentTenantFor($user)` — single-database apps: attach an organization and set it as the current Filament tenant (see **Multi-tenancy**).
- The exact helpers available differ per app — check `tests/Pest.php`.
- Add a new test-scoped helper as a plain function (in the test file for local use, or `Pest.php` if shared), as with `auditsFor()`.
- Use Eloquent model factories for domain data (`User::factory()->create()`).

## Multi-tenancy

Apps use one of two tenancy styles. Determine which this app uses before placing or writing tenancy-related tests, then follow it — do not mix them.

- **Single-database (Filament tenant):** one test tree mirroring the source; the current tenant is a Filament tenant set in `beforeEach()` (e.g. a `setCurrentTenantFor()` / `Filament::setTenant()` helper). Signal: a Filament tenant/organization model and no `tests/Landlord` + `tests/Tenant` split.
- **Separate landlord/tenant databases (Spatie multitenancy):** tests are split into `tests/Landlord/**` and `tests/Tenant/**` suites, each mirroring the source inside the suite. Signal: `spatie/laravel-multitenancy`, the `Landlord`/`Tenant` directory split, and `LandlordTestCase`/`TenantTestCase`.

For landlord/tenant suite placement and tricks (making a tenant current, asserting across connections, provisioning), read `reference/tenancy.md`. Single-database apps can ignore it entirely.

## Factories, States & Fakes

- Prefer named factory **states and sequences** over manual attribute overrides: use `User::factory()->unverified()->create()`, not `User::factory()->create(['email_verified_at' => null])`. Check the factory for an existing state before setting attributes by hand.
- Faker: use `$this->faker` for random data (e.g. `$this->faker->word()`, `$this->faker->unique()->safeEmail()`). The global `fake()` helper is banned by a PHPStan rule — never use it.
- Call `Event::fake()` **after** creating factory models — factories rely on model events (e.g. `creating` to generate UUIDs), so faking beforehand produces broken models. Incorrect: `Event::fake(); User::factory()->create();`. Correct: `User::factory()->create(); Event::fake();`.
- Use `recycle()` to share one related instance across nested factories: `Ticket::factory()->recycle($airline)->create();`.
- Use `Exceptions::fake()` to assert an exception was reported while the request still completes normally, instead of `withoutExceptionHandling()`.
- Fake external boundaries and assert on them: `Http::fake()` with `Http::preventStrayRequests()`, `Notification::fake()`, `Queue::fake()`. Set the fake up before the code under test runs, then assert what was (or was not) sent.

## Filament Resource Testing

Instantiate the page/relation-manager component with `livewire()` and drive it with Filament's testing helpers.

```php
it('can create a user', function () {
    Notification::fake();

    $request = CreateUserRequestFactory::new()->create();

    livewire(CreateUser::class)
        ->fillForm($request)
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'name' => $request['name'],
        'email' => $request['email'],
    ]);
});
```

Common helpers to use consistently:

| Helper                                                                                                      | Purpose                                                     |
| ----------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| `livewire(Page::class)` / `livewire(Manager::class, ['ownerRecord' => $model, 'pageClass' => Edit::class])` | Instantiate the component                                   |
| `fillForm($data)` / `assertSchemaStateSet($data)`                                                           | Set / assert form or infolist state                         |
| `call('create' \| 'save' \| 'delete')`                                                                      | Invoke the page action                                      |
| `assertHasFormErrors($errors)` / `assertHasNoFormErrors()`                                                  | Validation                                                  |
| `assertCanSeeTableRecords()` / `assertCanNotSeeTableRecords()`                                              | Table contents                                              |
| `sortTable()` / `searchTable()`                                                                             | Table sort / search behaviour                               |
| `assertTableColumnVisible()` / `assertTableColumnHidden()`                                                  | Assert conditional column visibility (not static existence) |
| `assertTableColumnStateSet($column, $state, record: $record)` / `assertTableColumnFormattedStateSet(...)`   | Assert a column's resolved / formatted cell state           |
| `assertActionVisible()` / `assertActionHidden()`                                                            | Action visibility                                           |
| `callAction(TestAction::make(ActionClass::class)->table())` / `->bulk()`                                    | Table & bulk actions                                        |
| `selectTableRecords([$id])`                                                                                 | Select rows for bulk actions                                |

- Render checks use HTTP: `$this->get(UserResource::getUrl('create'))->assertSuccessful();`.
- Relation managers are instantiated with the owner record and page class — `livewire(RolesRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])` — and actions are driven with `callAction(TestAction::make(AttachAction::class)->table())` (add `->bulk()` for bulk actions).

### Asserting computed state

A render check does not exercise a component that resolves or formats its own value. Assert the actual state wherever custom logic runs:

- **Infolist entries / form fields with custom state** (`state()`, `getStateUsing()`, `formatStateUsing()`, computed accessors, relationship state): assert with `assertSchemaStateSet([...])`. On **View pages** this is required whenever an entry has such logic.
- **Table columns with custom state or formatting**: assert the resolved value with `assertTableColumnStateSet($column, $state, record: $record)` and the displayed value with `assertTableColumnFormattedStateSet($column, $state, record: $record)`. This is required for any column using `getStateUsing()` / `formatStateUsing()` / a custom `state()`, on **every table** — list pages, relation managers, manage-related-records pages, and table widgets.

Plain columns/entries with no custom logic need no assertion of their own — do **not** add a static `assertTableColumnExists()` list to stand in for behaviour tests. Reserve state assertions for columns/entries that actually compute or format a value.

### The `authorization` block (required on every resource page)

Place it last in the file. At minimum cover:

- denies access without the relevant permission (`assertForbidden()`);
- allows access with the relevant permission (`assertSuccessful()`);
- allows a super admin;
- where partner/tenant admins have implicit access, allows them without any explicit permission.

### Required coverage by page type

"Required" cases appear in every such test file; add "when applicable" cases only when the resource has that behaviour. Happy-path cases are top-level `it()`s; the `authorization` block is always last.

**List page (`List<Models>Test`)**

- Required: render; `can list <models>`; `can sort by column`; `can search by column`; `authorization`.
- When applicable: assert custom column state / formatting with `assertTableColumnStateSet` / `assertTableColumnFormattedStateSet` (required for any column with a custom `state()` / `getStateUsing()` / `formatStateUsing()`); assert conditional column visibility with `assertTableColumnVisible` / `assertTableColumnHidden`; `describe('tenant scoping')`; `describe('deletion')` (delete / bulk-delete action present); action visibility (e.g. `impersonation`, `admin visibility`); `describe('filters')`.

**Create page (`Create<Model>Test`)**

- Required: render; `can create a <model>`; `validates the inputs` (dataset); `authorization`.
- When applicable: create with optional / related fields; auto-associates with the current tenant; notifications / side effects sent (and not sent); same name allowed in different tenants.

**Edit page (`Edit<Model>Test`)**

- Required: render; `displays the <model> data` (`assertSchemaStateSet`); `can update a <model>`; `allows saving with the same name as the current record`; `validates the inputs` (dataset); `authorization`.
- When applicable: `describe('deletion')`; conditional field visibility; disabled / read-only fields; complex form state (e.g. permission matrices).

**View page (`View<Model>Test`)**

- Required: render; `displays the <model> data` — assert entries with `assertSchemaStateSet`, and it is required to assert any infolist entry that uses custom state / formatting; `authorization`.
- When applicable: header actions (e.g. impersonation); admin visibility.

**Relation manager (`<Related>RelationManagerTest`)**

- Required: attach / associate; detach / dissociate; `authorization`.
- When applicable: assert custom column state / formatting with `assertTableColumnStateSet` / `assertTableColumnFormattedStateSet` (required for any column with custom state); bulk detach; action visibility gated on the owner's permissions; option / dropdown filtering; business-rule restrictions (`does not let ...`).

**Manage-related-records page (`Manage<Related>Test`, extends `ManageRelatedRecords`)**

- Required: render; lists the related records scoped to the owner; does not list other owners' records; `can sort by column`; `can search by column`; `authorization`.
- When applicable: `describe('filters')`; `describe('deletion')`; create / edit related records; custom column state (`assertTableColumnStateSet` / `assertTableColumnFormattedStateSet`); conditional column visibility.
- Instantiate with the owner record: `livewire(ManageAdapters::class, ['record' => $owner->getRouteKey()])`.

**Custom / settings form page (`Manage<Thing>Test`, a custom page with a form)**

- Required: render; loads existing data into the form (`assertSchemaStateSet`); saves with valid data; `authorization` — include a "does not save without the `update` permission" case.
- When applicable: `validates the inputs` (dataset) when the form validates; tenant scoping; fallback / default behaviour.

**Table widget (`<Name>WidgetTest`)**

- Required: render; lists the expected / scoped records; `authorization` when the widget is access-gated.
- When applicable: custom column state / formatting (`assertTableColumnStateSet` / `assertTableColumnFormattedStateSet`) whenever a closure resolves the value; `describe('filters')`; `can sort by column` / `can search by column` when enabled; conditional visibility via the widget's `canView()` closure.
- Instantiate with `livewire(<Name>Widget::class)`; pass `['record' => $model]` for a record-scoped widget.

**Stats-overview & chart widgets (`<Name>WidgetTest`)**

- Required: render; assert each computed **stat value** reflects the underlying data; for charts, assert the computed **dataset / labels** reflect the data; conditional visibility via `canView()`.
- When applicable: filters / period selection.
- Every stat and data point comes from a closure, so each must be asserted (see **What to assert**).

## Request Factories (form & validation data)

Form input and validation datasets use **Worksome `RequestFactory`** classes — do not hand-build large form arrays inline.

- One factory per form/request, co-located under a `RequestFactories/` directory mirroring the test namespace (e.g. `tests/Filament/Resources/Users/RequestFactories/CreateUserRequestFactory.php`).
- Extend `Worksome\RequestFactories\RequestFactory` and define defaults in `definition()`.
- Build data with `CreateUserRequestFactory::new()->state([...])->create()`; pass a factory instance into a dataset for validation cases.

```php
namespace Tests\Filament\Resources\Users\RequestFactories;

use Worksome\RequestFactories\RequestFactory;

class CreateUserRequestFactory extends RequestFactory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
```

## Non-Filament Classes

- **Actions**: resolve from the container and invoke — `app(GenerateOneTimeLoginCode::class)($user, now()->addDay())` — then assert results/side effects.
- **Models**: test custom behaviour — scopes, casts, accessors/mutators, and DB constraints (`->toThrow(QueryException::class)`). Do **not** test plain relationships; they are exercised by feature tests, and only warrant a dedicated test in extreme cases (e.g. an unusual custom relationship query).
- **Console commands**: `use function Pest\Laravel\artisan;`, fake HTTP/queues, assert with `assertSuccessful()` and `->throws(...)`.
- **HTTP controllers / API**: `use function Pest\Laravel\postJson;`, assert `assertOk()/assertUnprocessable()/assertForbidden()`, `assertJsonStructure()`, `assertJsonValidationErrors()`, and fake notifications.
- **Enums**: assert their behaviour and integration (labels, icons, navigation).

## Running Tests

Run tests with `php artisan test --compact`; narrow with `--filter=` or a path while iterating, and run the full suite before finalizing. Execute the command according to the `pls` guideline (these apps run commands inside the app container). Do not delete tests without approval.

## Common Pitfalls

- Creating a second test file for part of a class instead of adding to the existing file with a `describe()` block.
- Test path/name not mirroring the source class exactly, or missing the `Test.php` suffix.
- Using `test()` where `it('...')` would read naturally — default to `it()`; use `test()` only when the `it()` phrasing is grammatically awkward.
- Hand-building large form arrays instead of a `RequestFactory`.
- Putting setup logic in `TestCase` instead of `Pest.php` helpers.
- `assertStatus(200)` instead of `assertSuccessful()`; forgetting `Notification::fake()` / `Http::fake()` before asserting they were (not) sent.
- Forgetting the `authorization` `describe()` block on Filament resource pages.
- Asserting a column or field merely exists (a static `assertTableColumnExists()` list) instead of testing behaviour — reserve existence/visibility assertions for conditional columns via `assertTableColumnVisible()` / `assertTableColumnHidden()`.
- Leaving a user-supplied closure (`visible()`, `getStateUsing()`, `formatStateUsing()`, action `visible()`, a table query modification, etc.) untested.
