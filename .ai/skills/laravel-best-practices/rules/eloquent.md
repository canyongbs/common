# Eloquent Best Practices

## Use Correct Relationship Types

Use `hasMany`, `belongsTo`, `morphMany`, etc. with proper return type hints.

```php
/**
 * @return HasMany<Comment, $this>
 */
public function comments(): HasMany
{
    return $this->hasMany(Comment::class);
}

/**
 * @return BelongsTo<User, $this>
 */
public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

## Start Queries with `query()`

Always begin a query with `Model::query()` rather than calling static query methods like `Model::where()`, `Model::all()`, or `Model::find()` directly on the model. Starting with `query()` returns an explicit `Builder` instance, which improves IDE autocompletion and static analysis, and keeps query chains consistent.

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

## Use Tappable, Invokable Scopes for Reusable Queries

Extract reusable query constraints into tappable, invokable scope classes to avoid duplication.

Incorrect:

```php
$active = User::query()->where('is_verified', true)->whereNotNull('activated_at')->get();
$articles = Article::query()->whereHas('user', function (Builder $query) {
    $query->where('is_verified', true)->whereNotNull('activated_at');
})->get();
```

Correct:

```php
class ActiveArticles
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

// Usage
$active = User::query()->tap(new ActiveArticles())->get();
$articles = Article::query()->whereHas('user', fn (Builder $query) => $query->tap(new ActiveArticles()))->get();
```

## Name Boolean and Timestamp Columns Consistently

Prefix boolean attributes with `is_`, `can_`, or `has_` so their meaning reads as a yes/no question. Name timestamp and datetime columns in the past tense with an `_at` suffix to describe when the event happened.

Incorrect:

```php
$table->boolean('active');
$table->boolean('admin');
$table->boolean('verified');
$table->timestamp('publish');
$table->timestamp('expiry');
```

Correct:

```php
$table->boolean('is_active');
$table->boolean('can_impersonate');
$table->boolean('has_verified_email');
$table->timestamp('published_at');
$table->timestamp('expires_at');
```

## Define Attribute Casts

Use the `casts()` method (or `$casts` property following project convention) for automatic type conversion.

```php
protected function casts(): array
{
    return [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'total' => 'decimal:2',
    ];
}
```

## Cast Date Columns Properly

Always cast date columns. Use Carbon instances in templates instead of formatting strings manually.

Incorrect:

```blade
{{ Carbon::createFromFormat('Y-d-m H-i', $order->ordered_at)->toDateString() }}
```

Correct:

```php
protected function casts(): array
{
    return [
        'ordered_at' => 'datetime',
    ];
}
```

```blade
{{ $order->ordered_at->toDateString() }} {{ $order->ordered_at->format('m-d') }}
```

## Use `whereBelongsTo()` for Relationship Queries

Cleaner than manually specifying foreign keys.

Incorrect:

```php
Post::query()->where('user_id', $user->id)->get();
```

Correct:

```php
Post::query()->whereBelongsTo($user)->get();
Post::query()->whereBelongsTo($user, 'author')->get();
```

## Avoid Hardcoded Table Names in Queries

Never use string literals for table names in raw queries, joins, or subqueries. Hardcoded table names make it impossible to find all places a model is used and break refactoring (e.g., renaming a table requires hunting through every raw string).

Incorrect:

```php
DB::table('users')->where('is_active', true)->get();

$query->join('companies', 'companies.id', '=', 'users.company_id');

DB::select('SELECT * FROM orders WHERE status = ?', ['pending']);
```

Correct — reference the model's table:

```php
DB::table((new User)->getTable())->where('is_active', true)->get();

// Even better — use Eloquent or the query builder instead of raw SQL
User::query()->where('is_active', true)->get();
Order::query()->where('status', 'pending')->get();
```

Prefer Eloquent queries and relationships over `DB::table()` whenever possible — they already reference the model's table. When `DB::table()` or raw joins are unavoidable, always use `(new Model)->getTable()` to keep the reference traceable.

**Exception — migrations:** In migrations, hardcoded table names via `DB::table('settings')` are acceptable and required. Models change over time but migrations are frozen snapshots — referencing a model that is later renamed or deleted would break the migration.

## Use `chunkById()` and `eachById()` for Large Datasets

When iterating over large result sets, prefer `chunkById()` and `eachById()` over `chunk()` and `each()`. The `chunk()` and `each()` methods paginate with `offset`/`limit`, so modifying rows inside the callback (updating or deleting them) shifts the offsets and causes records to be skipped. `chunkById()` and `eachById()` paginate by the primary key instead, keeping iteration stable even while rows are changing.

Incorrect:

```php
User::query()->where('is_active', true)->chunk(200, function (Collection $users) {
    $users->each->deactivate();
});

User::query()->where('is_active', true)->each(function (User $user) {
    $user->deactivate();
});
```

Correct:

```php
User::query()->where('is_active', true)->chunkById(200, function (Collection $users) {
    $users->each->deactivate();
});

User::query()->where('is_active', true)->eachById(function (User $user) {
    $user->deactivate();
});
```
