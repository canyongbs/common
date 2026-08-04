# Model Definition Best Practices

Rules for defining Eloquent models — relationships, casts, collections, and mass assignment. For querying models, see `queries.md`; for column and attribute naming (booleans, timestamps, snake_case), see `style.md`.

## Use Correct Relationship Types

Use `hasMany`, `belongsTo`, `morphMany`, etc. with proper return type hints. Include the pivot model as the third generic argument on `belongsToMany` relationships that use a custom pivot.

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

/**
 * @return BelongsToMany<User, $this, OrganizationUser>
 */
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)->using(OrganizationUser::class)->withTimestamps();
}
```

## Use UUID Keys, Soft Deletes & Pivots

- **UUID primary keys** — every model uses `HasUuids` (the migration declares a `uuid('id')` primary key; see `migrations.md`).
- **Soft deletes on domain models** — domain models use `SoftDeletes`; "meta" tables such as pivots and join tables do **not**. On a soft-deleting table the unique index is scoped to `whereNull('deleted_at')`, and unique validation must match it (see `migrations.md`).
- **Custom pivot models** extend `Pivot` and use `HasUuids` — a UUID key, no soft deletes.

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    use HasUuids;
}
```

## Define Attribute Casts

Use the `casts()` method for automatic type conversion.

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

## Guard Mass Assignment

Every model must define `$fillable` (whitelist) — never `$guarded = []` on a model that accepts user input. See `security.md` for the full rationale.

```php
/** @var list<string> */
protected $fillable = ['name', 'email'];
```

## Mirror Database Defaults in `$attributes`

When a column has a database default, mirror it in the model so new, unsaved instances already have the correct value.

```php
// Migration
$table->string('status')->default('pending');

// Model
protected $attributes = [
    'status' => 'pending',
];
```

## Use `#[CollectedBy]` for Custom Collection Classes

More declarative than overriding `newCollection()`.

```php
#[CollectedBy(UserCollection::class)]
class User extends Model {}
```
