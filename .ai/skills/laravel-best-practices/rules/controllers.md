# Controller & HTTP Endpoint Best Practices

This is a Filament-first codebase: most CRUD lives in Filament resources and pages, and form validation lives in the Filament schema — not in routes, controllers, or Form Request classes. These rules cover the few standalone HTTP endpoints. For extracting business logic into Action classes and dependency injection, see `architecture.md`.

## Use Single-Action Invokable Controllers

Prefer one class with an `__invoke()` method per endpoint over resource or multi-method controllers. Invokable controllers do not extend a base `Controller`.

```php
class RunAdapterController
{
    public function __invoke(Request $request, Adapter $adapter): Response
    {
        // ...
    }
}
```

## Validate Inline — No Form Request Classes

There are no Form Request classes. Validate inline with `$request->validate([...])` in array notation, then hand the validated data to an Action class as **typed, named arguments** — never a raw array of user input.

```php
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
]);
```

## Authorize Every Endpoint

Call `Gate::authorize()` before acting. Filament resources authorize through their model policies automatically; standalone controllers must do it explicitly.

```php
Gate::authorize('create', Organization::class);
```

## Return Eloquent API Resources

Wrap responses in API Resources rather than hand-building arrays; set an explicit status code where relevant.

```php
return OrganizationResource::make($organization)
    ->response()
    ->setStatusCode(Response::HTTP_CREATED);
```

## Keep Controllers Thin — Never Perform Operations

A controller **never performs an operation or write itself**. Its only responsibilities are:

- **authorize** via `Gate::authorize()`;
- **validate** the request (inline, as above);
- **fetch data with no side effects** (read-only queries);
- **build the response** — a view, a response object, an Eloquent API Resource, or a plain array.

Every write or side effect belongs in an invokable Action class (see `architecture.md`), injected into the `__invoke()` method. A controller that mutates state directly is a bug.

```php
class CreateOrganizationController
{
    public function __invoke(Request $request, CreateOrganization $createOrganization): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
        ]);

        return OrganizationResource::make($createOrganization(name: $validated['name']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
```

## Group by Subject

Group controllers under a subject-named subdirectory and keep the `Controller` suffix: `Http/Controllers/Orders/CreateOrderController.php`. Actions follow the same subject grouping but are named for the operation with **no** suffix: `Actions/Orders/CreateOrder.php` (see `architecture.md`).
