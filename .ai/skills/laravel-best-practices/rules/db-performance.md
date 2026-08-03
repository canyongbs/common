# Database Performance Best Practices

## Always Eager Load Relationships

Lazy loading causes N+1 query problems — one query per loop iteration. Always use `with()` to load relationships upfront.

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

Constrain eager loads to select only needed columns (always include the foreign key):

```php
$users = User::query()->with(['posts' => function ($query) {
    $query->select('id', 'user_id', 'title')
          ->where('is_published', true)
          ->latest()
          ->limit(10);
}])->get();
```

## Prevent Lazy Loading in Development

Enable this in `AppServiceProvider::boot()` to catch N+1 issues during development.

```php
public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

Throws `LazyLoadingViolationException` when a relationship is accessed without being eager-loaded.

## Select Only Needed Columns

Avoid `SELECT *` — especially when tables have large text or JSON columns.

Incorrect:

```php
$posts = Post::query()->with('author')->get();
```

Correct:

```php
$posts = Post::query()->select('id', 'title', 'user_id', 'created_at')
    ->with(['author:id,name,avatar'])
    ->get();
```

When selecting columns on eager-loaded relationships, always include the foreign key column or the relationship won't match.

## Chunk Large Datasets

Never load thousands of records at once. Use chunking for batch processing.

Incorrect:

```php
$users = User::query()->get();
foreach ($users as $user) {
    $user->notify(new WeeklyDigest);
}
```

Correct:

```php
User::query()->where('is_subscribed', true)->chunk(200, function ($users) {
    foreach ($users as $user) {
        $user->notify(new WeeklyDigest);
    }
});
```

Use `chunkById()` when modifying records during iteration — standard `chunk()` uses OFFSET which shifts when rows change:

```php
User::query()->where('is_active', false)->chunkById(200, function ($users) {
    $users->each->delete();
});
```

## Add Database Indexes

Index columns that appear in `WHERE`, `ORDER BY`, `JOIN`, and `GROUP BY` clauses.

Incorrect:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('status');
    $table->timestamps();
});
```

Correct:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->index()->constrained();
    $table->string('status')->index();
    $table->timestamps();
    $table->index(['status', 'created_at']);
});
```

Add composite indexes for common query patterns (e.g., `WHERE status = ? ORDER BY created_at`).

## Use `withCount()` for Counting Relations

Never load entire collections just to count them.

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

Conditional counting:

```php
$posts = Post::query()->withCount([
    'comments',
    'comments as approved_comments_count' => function ($query) {
        $query->where('is_approved', true);
    },
])->get();
```

When you only need to know whether a relation has any related records — not the exact count — use `withExists()` instead. It adds a boolean `*_exists` attribute using an `EXISTS` subquery, which is cheaper than counting.

```php
$posts = Post::query()->withExists('comments')->get();
foreach ($posts as $post) {
    if ($post->comments_exists) {
        // ...
    }
}
```

## Use `cursor()` for Memory-Efficient Iteration

For read-only iteration over large result sets, `cursor()` loads one record at a time via a PHP generator.

Incorrect:

```php
$users = User::query()->where('is_active', true)->get();
```

Correct:

```php
foreach (User::query()->where('is_active', true)->cursor() as $user) {
    ProcessUser::dispatch($user->id);
}
```

Use `cursor()` for read-only iteration. Use `chunk()` / `chunkById()` when modifying records.

## No Queries in Blade Templates

Never execute queries in Blade templates. Pass data from controllers.

Incorrect:

```blade
@foreach (User::query()->get() as $user)
    {{ $user->profile->name }}
@endforeach
```

Correct:

```php
// Controller
$users = User::query()->with('profile')->get();
return view('users.index', compact('users'));
```

```blade
@foreach ($users as $user)
    {{ $user->profile->name }}
@endforeach
```
