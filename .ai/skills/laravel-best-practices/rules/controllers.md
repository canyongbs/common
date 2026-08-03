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

There are no Form Request classes. Validate inline with `$request->validate([...])` in array notation, then hand the validated data to an Action class.

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

## Keep Controllers Thin

A controller should validate, authorize, delegate to an Action or service, and return a response — nothing more. Put business logic in an Action class (see `architecture.md`), injected via the constructor.

```php
class CreateOrganizationController
{
    public function __construct(private CreateOrganization $create) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
        ]);

        return OrganizationResource::make(($this->create)($validated))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
```
