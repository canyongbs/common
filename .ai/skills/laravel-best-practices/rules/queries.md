# Query Best Practices

Rules for reading and writing data with Eloquent and the query builder, and for iterating large result sets efficiently. For defining models, see `models.md`; for expert patterns (subqueries, conditional aggregates, correlated ordering), see `advanced-queries.md`.

## Start Queries with `query()`

Always begin a query with `Model::query()` rather than calling static query methods like `Model::where()`, `Model::all()`, or `Model::find()` directly. Starting with `query()` returns an explicit `Builder` instance, which improves IDE autocompletion and static analysis and keeps query chains consistent. (`Model::create()` and other write helpers are fine as-is.)

Incorrect:

```php
$users = User::where('is_active', true)->get();
$user = User::find($id);
$all = User::all();
```

Correct:

```php
$users = User::query()->where('is_active', true)->get();
$user = User::query()->find($id);
$all = User::query()->get();
```

## Always Eager Load Relationships

Lazy loading causes N+1 query problems — one query per loop iteration. Use `with()` to load relationships upfront.

Incorrect (N+1 — executes 1 + N queries):

```php
$posts = Post::query()->get();
foreach ($posts as $post) {
    echo $post->author->name;
}
```

Correct (2 queries total):

```php
$posts = Post::query()->with('author')->get();
foreach ($posts as $post) {
    echo $post->author->name;
}
```

Constrain eager loads to select only the columns you need (always include the foreign key, or the relationship won't match):

```php
$users = User::query()->with(['posts' => function ($query) {
    $query->select('id', 'user_id', 'title')
        ->where('is_published', true)
        ->latest()
        ->limit(10);
}])->get();
```

## Select Only Needed Columns

Avoid `SELECT *`, especially when tables have large text or JSON columns.

```php
$posts = Post::query()->select('id', 'title', 'user_id', 'created_at')
    ->with(['author:id,name,avatar'])
    ->get();
```

When selecting columns on an eager-loaded relationship, always include the foreign key column or the relationship won't match.

## Prevent Lazy Loading in Development

Enable this in `AppServiceProvider::boot()` to catch N+1 issues during development. It throws `LazyLoadingViolationException` when a relationship is accessed without being eager-loaded.

```php
public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

## Count and Check Existence Efficiently

Never load an entire collection just to count it — use `withCount()`.

Incorrect:

```php
$posts = Post::query()->get();
foreach ($posts as $post) {
    echo $post->comments->count();
}
```

Correct:

```php
$posts = Post::query()->withCount('comments')->get();
foreach ($posts as $post) {
    echo $post->comments_count;
}
```

Conditional counts:

```php
$posts = Post::query()->withCount([
    'comments',
    'comments as approved_comments_count' => fn ($query) => $query->where('is_approved', true),
])->get();
```

When you only need to know **whether** a relation has any records — not how many — use `withExists()`, which adds a boolean `*_exists` attribute via a cheaper `EXISTS` subquery.

```php
$posts = Post::query()->withExists('comments')->get();
// $post->comments_exists
```

## Reusable Constraints with Tappable, Invokable Scopes

Extract reusable query constraints into invokable scope classes and apply them with `->tap(new Scope())` — instantiate directly with `new`, not the `app()` container.

Incorrect (duplicated constraint):

```php
$active = User::query()->where('is_verified', true)->whereNotNull('activated_at')->get();
$articles = Article::query()->whereHas('user', function (Builder $query) {
    $query->where('is_verified', true)->whereNotNull('activated_at');
})->get();
```

Correct:

```php
class ActiveUsers
{
    /**
     * @param Builder<covariant Model> $query
     */
    public function __invoke(Builder $query): void
    {
        $query
            ->where('is_verified', true)
            ->whereNotNull('activated_at');
    }
}

$active = User::query()->tap(new ActiveUsers())->get();
$articles = Article::query()->whereHas('user', fn (Builder $query) => $query->tap(new ActiveUsers()))->get();
```

## Query Relationships with `whereBelongsTo()`

Cleaner than manually specifying the foreign key.

Incorrect:

```php
Post::query()->where('user_id', $user->id)->get();
```

Correct:

```php
Post::query()->whereBelongsTo($user)->get();
Post::query()->whereBelongsTo($user, 'author')->get();
```

## Iterate Large Datasets Safely

Never load thousands of records into memory at once. Choose the right iteration strategy:

- **`chunk()` / `chunkById()`** — process records in batches. Use `chunkById()` whenever the callback modifies the rows it iterates: `chunk()` paginates with `offset`/`limit`, so updating or deleting rows shifts the offsets and skips records. `chunkById()` paginates by primary key and stays stable.
- **`each()` / `eachById()`** — the row-by-row equivalents; prefer `eachById()` when mutating.
- **`cursor()`** — one model in memory at a time via a generator. Cheapest for read-only iteration, but it **cannot eager-load relationships**, so touching a relation inside the loop causes N+1.
- **`lazy()` / `lazyById()`** — a `LazyCollection` that chunks under the hood and **does** support eager loading. Use `lazyById()` when mutating rows during iteration.

Incorrect (loads everything; offset-based `chunk` skips mutated rows):

```php
$users = User::query()->get();

User::query()->where('is_active', false)->chunk(200, function (Collection $users) {
    $users->each->delete();
});
```

Correct:

```php
// Read-only, attribute-only work — cursor is cheapest
foreach (User::query()->where('is_active', true)->cursor() as $user) {
    ProcessUser::dispatch($user->id);
}

// Need relationships — lazy() supports eager loading
foreach (User::query()->with('roles')->lazy() as $user) {
    // $user->roles is loaded
}

// Mutating rows — id-based pagination is stable
User::query()->where('is_active', false)->chunkById(200, function (Collection $users) {
    $users->each->delete();
});
```

## Bulk Operations with `toQuery()`

Run a bulk update/delete against a collection without hand-building a `whereIn`.

Incorrect:

```php
User::query()->whereIn('id', $users->pluck('id'))->update(['is_active' => false]);
```

Correct:

```php
$users->toQuery()->update(['is_active' => false]);
```

## Higher-Order Messages for Simple Collection Operations

Prefer higher-order messages for simple per-item operations.

Incorrect:

```php
$users->each(function (User $user) {
    $user->markAsVip();
});
```

Correct: `$users->each->markAsVip();` — works with `each`, `map`, `sum`, `filter`, `reject`, `contains`, and more.

## Avoid Hardcoded Table Names in Queries

Never use string literals for table names in raw queries, joins, or subqueries. Hardcoded names make it impossible to find every place a model is used and break refactoring when a table is renamed.

Incorrect:

```php
DB::table('users')->where('is_active', true)->get();
$query->join('companies', 'companies.id', '=', 'users.company_id');
DB::select('SELECT * FROM orders WHERE status = ?', ['pending']);
```

Correct — prefer Eloquent (it already references the model's table); when raw builder access is unavoidable, use `(new Model)->getTable()`:

```php
User::query()->where('is_active', true)->get();
DB::table((new User)->getTable())->where('is_active', true)->get();
```

**Exception — migrations:** hardcoded table names via `DB::table('settings')` are acceptable and required there. Migrations are frozen snapshots, so referencing a model that is later renamed or deleted would break them.

## Index the Columns You Query

Index every column used in `WHERE`, `ORDER BY`, `JOIN`, and `GROUP BY` clauses, and add compound indexes for common multi-column filters and sorts. Define the indexes in the migration that creates the table — see `migrations.md`.

```php
// Query
Order::query()->where('status', 'pending')->latest()->get();

// Migration — index the filtered/sorted columns
$table->index(['status', 'created_at']);
```

## Never Query in Blade Templates

Pass data from the controller/component; never execute queries in Blade.

Incorrect:

```blade
@foreach (User::query()->get() as $user)
    {{ $user->profile->name }}
@endforeach
```

Correct:

```php
$users = User::query()->with('profile')->get();
```

```blade
@foreach ($users as $user)
    {{ $user->profile->name }}
@endforeach
```
