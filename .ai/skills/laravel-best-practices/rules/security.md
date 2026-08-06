# Security Best Practices

## Mass Assignment Protection

Every model must define `$fillable` (a mass-assignment whitelist).

Incorrect:

```php
class User extends Model
{
    protected $guarded = []; // All fields are mass assignable
}
```

Correct:

```php
class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
```

Never use `$guarded = []` on models that accept user input.

## Authorize Every Action

Use policies or gates. Never skip authorization. Back each model with a policy class (`php artisan make:policy`); Filament resources authorize through their model policies automatically; in standalone controllers, call `Gate::authorize()` explicitly.

Incorrect:

```php
public function __invoke(Request $request, Post $post)
{
    $post->update($request->validate([
        'title' => ['required', 'string', 'max:255'],
    ]));
}
```

Correct:

```php
public function __invoke(Request $request, Post $post)
{
    Gate::authorize('update', $post);

    $post->update($request->validate([
        'title' => ['required', 'string', 'max:255'],
    ]));
}
```

## Record-Dependent Policy Methods Need Per-Record Bulk Authorization

**Whenever you write or modify a `delete` / `restore` / `forceDelete` policy method with record-dependent logic** — logic that depends on the specific record, so it diverges from its `deleteAny` / `restoreAny` / `forceDeleteAny` counterpart — you must also update the model's Filament bulk actions (all apps use Filament). A bulk action checks the `*Any` method **once** and does **not** run the per-record method for each selected record unless you add `->authorizeIndividualRecords('<method>')`. Omitting it lets users bulk-process records they may not act on individually. Return Filament's `DenyResponse` from the policy method so the failure notification reflects how many records were denied.

This is bidirectional: writing or reviewing a **policy** method, or a **Filament bulk action**, is the trigger to check the other side. Full pattern in the `structuring-filament-code` skill (“Bulk Actions: Authorize Individual Records”).

## Prevent SQL Injection

Always use parameter binding. Never interpolate user input into queries.

Incorrect:

```php
DB::select("SELECT * FROM users WHERE name = '{$request->name}'");
```

Correct:

```php
User::query()->where('name', $request->name)->get();

// Raw expressions must use bindings, never string interpolation.
// For case-insensitive search prefer an indexable `lower()` expression (see queries.md).
User::query()->whereRaw('created_at > ?', [$request->date('since')])->get();
```

## Escape Output to Prevent XSS

Use `{{ }}` for HTML escaping. Only use `{!! !!}` for trusted, pre-sanitized content.

Incorrect:

```blade
{!! $user->bio !!}
```

Correct:

```blade
{{ $user->bio }}
```

## CSRF Protection

Include `@csrf` in every hand-written POST/PUT/DELETE Blade form. Livewire and Filament forms handle CSRF automatically, so this applies only to plain Blade `<form>` elements.

Incorrect:

```blade
<form method="POST" action="/posts">
    <input type="text" name="title" />
</form>
```

Correct:

```blade
<form method="POST" action="/posts">
    @csrf
    <input type="text" name="title" />
</form>
```

## Rate Limit Auth and API Routes

Apply `throttle` middleware to authentication and API routes.

```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

Route::post('/login', LoginController::class)->middleware('throttle:login');
```

## Validate File Uploads

Validate extension, MIME type, and size. The `mimes` rule checks extensions; use `mimetypes` for actual MIME type validation. Never trust client-provided filenames.

```php
$validated = $request->validate([
    'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
]);
```

Store with generated filenames:

```php
$path = $request->file('avatar')->store('avatars', 'public');
```

## Keep Secrets Out of Code

Never commit `.env`. Access secrets via `config()` only, never `env()` outside config files — see `config.md`.

## Audit Dependencies

Run `composer audit` periodically to check for known vulnerabilities in dependencies. Automate this in CI to catch issues before deployment.

```bash
composer audit
```

## Encrypt Sensitive Database Fields

Use `encrypted` cast for API keys/tokens and mark the attribute as `hidden`.

Incorrect:

```php
class Integration extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'string',
        ];
    }
}
```

Correct:

```php
class Integration extends Model
{
    protected $hidden = ['api_key', 'api_secret'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }
}
```
