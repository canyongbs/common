# Configuration Best Practices

## `env()` Only in Config Files

Direct `env()` calls may return `null` when config is cached.

Incorrect:

```php
$key = env('API_KEY');
```

Correct:

```php
// config/services.php
'key' => env('API_KEY'),

// Application code
$key = config('services.key');
```

## Use `App::environment()` for Environment Checks

Incorrect:

```php
if (env('APP_ENV') === 'production') {
```

Correct:

```php
if (app()->isProduction()) {
// or
if (App::environment('production')) {
```

## Use Constants and Language Files

Use class constants instead of hardcoded magic strings for model states, types, and statuses.

```php
// Incorrect
return $this->type === 'normal';

// Correct
return $this->type === self::TYPE_NORMAL;
```

If the application already uses language files for localization, use `__()` for user-facing strings too. Do not introduce language files purely for English-only apps — simple string literals are fine there.

```php
// Only when lang files already exist in the project
return back()->with('message', __('app.article_added'));
```
