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

## Assign Relationships, Not Foreign Keys

Never write a foreign key column in application code — let the relationship own it. Set a `belongsTo` / `morphTo` with `associate()` (`dissociate()` to clear); a `morphTo`'s `associate()` sets both the id and type columns. Persist an unsaved `hasOne` / `hasMany` / `morphOne` / `morphMany` child through the relationship with `->save($model)` (or `->saveMany([...])`), which sets the foreign key (and morph type) for you.

```php
// Incorrect — foreign key assigned in code
$order->customer_id = $customer->id;
$comment->post_id = $post->id;

// Correct — belongsTo / morphTo via associate()
$order->customer()->associate($customer);
$order->save();
$comment->commentable()->associate($post); // morphTo: sets id + type

// Correct — persist an unsaved hasMany / hasOne / morph* child via the relationship
$post->comments()->save($comment);           // sets comment.post_id
$post->comments()->saveMany([$first, $second]);
```

## Use UUID Keys, Soft Deletes & Pivots

- **UUID primary keys** — every new model uses `Illuminate\Database\Eloquent\Concerns\HasUuids`, which generates time-ordered UUIDv7 keys (the migration declares a `uuid('id')` primary key; see `migrations.md`). Never use `HasVersion4Uuids` on a new model, and never alias it (`use HasVersion4Uuids as HasUuids;`).
- **Extend the framework base class** — new models extend `Illuminate\Database\Eloquent\Model` directly, or the right framework base such as `Pivot` or `Authenticatable`. Never extend an app-level base model such as `App\Models\BaseModel`; it is legacy and generates UUIDv4 keys.
- **Keep legacy UUIDv4 models on v4** — existing models that use `HasVersion4Uuids` (listed in the app's `legacy-v4-uuid-models.php` allowlist where it exists) must keep it. Never switch them to `HasUuids`: mixing UUID versions in one table breaks ID ordering. Never add new models to the allowlist.
- **Soft deletes on domain models** — domain models use `SoftDeletes`; "meta" tables such as pivots and join tables do **not**. On a soft-deleting table the unique index is scoped to `whereNull('deleted_at')`, and unique validation must match it (see `migrations.md`).
- **Custom pivot models** extend `Pivot` and use `HasUuids` — a UUID key, no soft deletes.

Incorrect:

```php
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;

class Organization extends BaseModel // legacy UUIDv4 base model
{
    use HasUuids; // aliased UUIDv4 — new models must use the real HasUuids (UUIDv7)
}
```

Correct:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuids;
    use SoftDeletes;
}

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

## Write Attributes Explicitly, Not by Mass Assignment

In application code (Actions, jobs, listeners) do **not** use `Model::create()`, `->update()`, `->fill()`, `updateOrCreate()`, or other mass-assignment writes for an individual record. Instantiate the model (or fetch an existing one), set each attribute explicitly with `->attribute =`, then `->save()`. This keeps every write typed, greppable, and independent of `$fillable`.

```php
// Incorrect — mass assignment
$order = Order::create(['total' => $total, 'status' => OrderStatus::Pending]);

// Correct — explicit attributes
$order = new Order();
$order->total = $total;
$order->status = OrderStatus::Pending;
$order->customer()->associate($customer);
$order->save();
```

`$fillable` is still required because Filament and other framework paths mass-assign (see above). Set-based bulk writes through the query builder (`Model::query()->where(...)->update([...])`) remain fine — this rule is about writing a single model's attributes, not bulk operations.

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
