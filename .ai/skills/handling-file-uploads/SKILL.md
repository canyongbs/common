---
name: handling-file-uploads
description: 'Use when storing, uploading, or serving user files and images in a Canyon GBS app with Spatie Media Library — adding a file/image upload, defining media collections and conversions, choosing the S3 disk (private vs public), or outputting a stored image (temporary URLs, WebP conversions). Trigger whenever a model implements `HasMedia`, you register a media collection or conversion, add a Filament `SpatieMediaLibraryFileUpload`, or output a stored file/image. Do not use for: static images shipped with the app (logos/illustrations via Vite — see the `assets` rule in the `laravel-best-practices` skill), attaching files to settings pages (use `managing-settings` for the wiring), or writing tests (use `writing-tests`).'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Handling File Uploads

User files and images are stored with **Spatie Media Library** on **S3** — never as raw uploads.

## Always Spatie Media Library

Never use raw file uploads (`$request->file(...)->store(...)`, a plain string column, etc.). Attach files to a model with Media Library.

- The model implements `HasMedia` and uses `InteractsWithMedia`.
- **Always upload into a named collection** — never the implicit default. Use `singleFile()` for one-file collections (an avatar, a single logo) so a new upload replaces the old one.
- **Register a conversion for anything shown to users** — convert raster images to **WebP** and **scale** to the size actually rendered (SVGs are vector; leave them as-is). Output the conversion, not the original.

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Adapter extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('header_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('header')
            ->format('webp')
            ->width(1000);
    }
}
```

## Disks — Private S3 by Default

- **Always store on S3.** The default disk is **`s3`, which is private** — use it for essentially all media.
- Use **`s3-public`** **only** when a file needs a **permanent public URL** — e.g. an image embedded in an email, where a signed/temporary URL would expire and break. If a temporary URL is acceptable, keep the file private on `s3`.

## Filament Upload Field

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

SpatieMediaLibraryFileUpload::make('header_image')
    ->collection('header_image')
    ->disk('s3')
    ->image()
    ->maxSize(10240)
    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']);
```

Always set `->collection(...)` and `->disk('s3')` explicitly.

## Outputting Images

Output through the registered **conversion**, not the original file:

- **Private (`s3`)** — a temporary signed URL scoped to the collection and conversion:

    ```php
    $adapter->getFirstTemporaryUrl(now()->addMinute(), 'header_image', 'header');
    ```

- **Public (`s3-public`)** — a permanent URL (e.g. an email image `src`):

    ```php
    $adapter->getFirstMediaUrl('header_image', 'header');
    ```

---

Related: attaching a file to a settings page reuses this Media Library setup wired to a settings model — see the `managing-settings` skill. For static images shipped with the app (logos, illustrations), see the `assets` rule in the `laravel-best-practices` skill.
