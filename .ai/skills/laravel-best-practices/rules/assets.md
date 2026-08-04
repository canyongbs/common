# Frontend Asset Best Practices

Static images and other assets shipped with the app (logos, illustrations) live under `resources/`, are built by Vite, and are referenced with `Vite::asset()` — never placed in or served from `public/`. For user-uploaded files and images, use the `handling-file-uploads` skill instead; these rules are only for assets committed with the code.

## Store Static Images in `resources/images/`

Keep bundled images under `resources/images/`, named in kebab-case (`logo-light.png`, `canyon-logo-dark.svg`). Never drop them in `public/`.

## Register Each Asset in `vite.config.js`

An asset must be a Vite input (or imported from one) to appear in the build manifest; otherwise `Vite::asset()` cannot resolve it and the reference 404s.

```js
// vite.config.js
laravel({
    input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/images/logo-light.png',
        'resources/images/logo-dark.png',
    ],
});
```

## Reference Assets with `Vite::asset()`

Resolve the hashed, versioned build path from the manifest — in Blade and in PHP. Never hardcode a `/images/...` path.

```blade
<img src="{{ Vite::asset('resources/images/logo-light.png') }}" />
```

```php
// e.g. registering a Filament panel logo
->brandLogo(fn () => Vite::asset('resources/images/logo-light.png'))
```
