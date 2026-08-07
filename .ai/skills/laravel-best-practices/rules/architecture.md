# Architecture Best Practices

## Single-Purpose Action Classes

Extract discrete business operations into invokable Action classes. Name an action for the operation it performs — **no `Action` suffix** — and group it under a subject directory: `Actions/Orders/CreateOrder.php`. Accept **typed, individually named parameters** — never an untyped `array` of user input — so the signature documents exactly what the operation needs.

```php
namespace App\Actions\Orders;

class CreateOrder
{
    public function __construct(private InventoryService $inventory) {}

    public function __invoke(Customer $customer, int $total): Order
    {
        return DB::transaction(function () use ($customer, $total) {
            $order = new Order();
            $order->total = $total;
            $order->customer()->associate($customer);
            $order->save();

            $this->inventory->reserve($order);

            return $order;
        });
    }
}
```

## Observers vs. Actions: Where Logic Belongs

Default to an **Action** for business logic; reach for an **Observer** only for invariants that must hold on _every_ write of a model, no matter who triggers it.

- **Observer — caller-agnostic model invariants.** Use one only for logic that must run whenever the record is persisted, independent of the call site: backfilling a missing `sort` / `order` number on a new record, stamping an audit / history entry, deriving a column from another. These are small, deterministic, and part of the model's lifecycle — no caller should be able to forget them.
- **Action — business operations.** Anything a caller _decides_ to do — orchestrating steps, calling other services, sending notifications, conditional workflows — belongs in an invokable Action. It is explicit at the call site, testable in isolation, and easy to _not_ run when it shouldn't.

**Why the line matters:** the moment business logic creeps into an observer, some code paths won't want it — and the only escape hatch is a `quietly()` write (`saveQuietly()`, `updateQuietly()`, `archiveQuietly()`), which suppresses **every** event on that model, not just the one you meant to skip. That silently disables unrelated observers (auditing, search indexing, other invariants) and becomes very hard to maintain and reason about. Keep observers thin enough that no caller ever needs to bypass them.

## Wrap Multi-Step Writes in a Transaction

When one operation performs several writes that must all succeed or all fail together, wrap them in `DB::transaction()` so a failure can't leave half-written state (as in `CreateOrder` above). Keep transactions short — do no HTTP calls inside them, and defer side effects with `DB::afterCommit()` or by dispatching jobs after the closure returns.

## Use Dependency Injection

Inject dependencies rather than reaching for `app()` or `resolve()` inside a class. Use constructor injection everywhere except controllers, which inject their per-request dependencies — the action and route-bound models — into the `__invoke()` method.

Incorrect:

```php
class CreateOrderController
{
    public function __invoke(Request $request)
    {
        $createOrder = app(CreateOrder::class);

        $validated = $request->validate([
            'total' => ['required', 'integer', 'min:0'],
        ]);

        return $createOrder(customer: $request->user()->customer, total: $validated['total']);
    }
}
```

Correct:

```php
class CreateOrderController
{
    public function __invoke(Request $request, CreateOrder $createOrder)
    {
        $validated = $request->validate([
            'total' => ['required', 'integer', 'min:0'],
        ]);

        return $createOrder(customer: $request->user()->customer, total: $validated['total']);
    }
}
```

## Code to Interfaces

Depend on contracts at system boundaries (payment gateways, notification channels, external APIs) for testability and swappability.

Incorrect (concrete dependency):

```php
class OrderService
{
    public function __construct(private StripeGateway $gateway) {}
}
```

Correct (interface dependency):

```php
interface PaymentGateway
{
    public function charge(int $amount, string $customerId): PaymentResult;
}

class OrderService
{
    public function __construct(private PaymentGateway $gateway) {}
}
```

Bind in a service provider:

```php
$this->app->bind(PaymentGateway::class, StripeGateway::class);
```

## Use Atomic Locks for Race Conditions

Prevent race conditions with `Cache::lock()` or `lockForUpdate()`.

```php
Cache::lock("order-processing-{$order->id}", 10)->block(5, function () use ($order) {
    $order->process();
});

// Or at query level
$product = Product::query()->where('id', $id)->lockForUpdate()->first();
```

## Use `mb_*` String Functions

When no Laravel helper exists, prefer `mb_strlen`, `mb_strtolower`, etc. for UTF-8 safety. Standard PHP string functions count bytes, not characters.

Incorrect:

```php
strlen('José');          // 5 (bytes, not characters)
strtolower('MÜNCHEN');  // 'mÜnchen' — fails on multibyte
```

Correct:

```php
mb_strlen('José');             // 4 (characters)
mb_strtolower('MÜNCHEN');     // 'münchen'

// Prefer Laravel's Str helpers when available
Str::length('José');          // 4
Str::lower('MÜNCHEN');        // 'münchen'
```

## Use `defer()` for Post-Response Work

For lightweight tasks that don't need to survive a crash (logging, analytics, cleanup), use `defer()` instead of dispatching a job. The callback runs after the HTTP response is sent — no queue overhead.

Incorrect (job overhead for trivial work):

```php
dispatch(new LogPageView($page));
```

Correct (runs after response, same process):

```php
defer(fn () => $logPageView($page));
```

Use jobs when the work must survive process crashes or needs retry logic. Use `defer()` for fire-and-forget work.

## Use `Context` for Request-Scoped Data

The `Context` facade passes data through the entire request lifecycle — middleware, controllers, jobs, logs — without passing arguments manually.

```php
// In middleware
Context::add('tenant_id', $request->header('X-Tenant-ID'));

// Anywhere later — controllers, jobs, log context
$tenantId = Context::get('tenant_id');
```

Context data automatically propagates to queued jobs and is included in log entries. Use `Context::addHidden()` for sensitive data that should be available in queued jobs but excluded from log context. If data must not leave the current process, do not store it in `Context`.

## Use `Concurrency::run()` for Parallel Execution

Run independent operations in parallel using child processes — no async libraries needed.

```php
use Illuminate\Support\Facades\Concurrency;

[$users, $orders] = Concurrency::run([
    fn () => User::query()->count(),
    fn () => Order::query()->where('status', 'pending')->count(),
]);
```

Each closure runs in a separate process with full Laravel access. Use for independent database queries, API calls, or computations that would otherwise run sequentially.

## Exception Reporting and Rendering

**Prefer co-locating `report()` and `render()` on the exception class.** Keeping the behaviour alongside the exception definition makes it easy to find and keeps `bootstrap/app.php` uncluttered:

```php
class InvalidOrderException extends Exception
{
    public function report(): void { /* custom reporting */ }

    public function render(Request $request): Response
    {
        return response()->view('errors.invalid-order', status: 422);
    }
}
```

Only centralize handling in `bootstrap/app.php` when co-location doesn't fit — for example, handling a third-party exception you can't modify, or applying one rule across many exception types:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (InvalidOrderException $e) { /* ... */ });
    $exceptions->render(function (InvalidOrderException $e, Request $request) {
        return response()->view('errors.invalid-order', status: 422);
    });
})
```

Whichever you use, follow the pattern already established in the codebase for consistency.

## Convention Over Configuration

Follow Laravel conventions. Don't override defaults unnecessarily.

Incorrect:

```php
class Customer extends Model
{
    protected $table = 'Customer';
    protected $primaryKey = 'customer_id';

    /**
     * @return BelongsToMany<Role, $this, CustomerRole>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->using(CustomerRole::class)->withTimestamps();
    }
}
```

Correct:

```php
class Customer extends Model
{
    /**
     * @return BelongsToMany<Role, $this, CustomerRole>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->using(CustomerRole::class)->withTimestamps();
    }
}
```
