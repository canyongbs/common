---
name: managing-settings
description: 'Use when creating or changing application settings in a Canyon GBS app built on spatie/laravel-settings — adding a settings class or property, writing a settings migration, choosing the settings group or repository, encrypting settings, building a Filament `SettingsPage`, or attaching an uploaded file to a settings page. Trigger whenever you add or edit a class extending Spatie `Settings`, a `SettingsMigration`, a Filament `SettingsPage`, or the `SettingsProperty` media bridge. Do not use for: generic config files / env access (see the `config` rule in the `laravel-best-practices` skill), general file uploads not tied to settings (use `handling-file-uploads`), or writing tests (use `writing-tests`).'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Managing Settings

Application settings use **spatie/laravel-settings**: one typed settings class per group, backed by a settings migration that registers each property.

## Settings Classes

Extend `Spatie\LaravelSettings\Settings`, one class per group:

```php
use Spatie\LaravelSettings\Settings;

class ThemeSettings extends Settings
{
    public ?string $changelog_url = null;

    public ?Color $primary_color = null;

    public static function group(): string
    {
        return 'theme';
    }
}
```

- **Every property must declare a PHP default** (`= null`, `= ''`, an enum case, etc.). Spatie hydrates not-yet-saved properties by reflection, so a property without a default returns `null` regardless of its type and causes silent type errors. This is enforced by the `SettingsPropertiesMustHaveDefaults` PHPStan rule.
- Properties are typed and may use enums (`?Color $primary_color`). Keep default values in class constants (`const DEFAULT_CHANGELOG_URL = '...'`).
- `group()` (required) returns the settings group name.
- `repository()` (optional) selects a non-default repository — e.g. `return 'landlord_database';` for landlord-scoped settings in multi-tenant apps.
- `encrypted()` (optional) lists property names to store encrypted:

    ```php
    public static function encrypted(): array
    {
        return ['application_id', 'key', 'url'];
    }
    ```

## Settings Migrations

Register and remove properties with a `SettingsMigration`, idempotently and inside a transaction. Place them under `database/migrations` (per-tenant) or `database/landlord` (landlord) as appropriate.

```php
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class () extends SettingsMigration {
    public function up(): void
    {
        DB::transaction(function () {
            try {
                $this->migrator->add('theme.changelog_url');
            } catch (SettingAlreadyExists $exception) {
                // do nothing
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $this->migrator->deleteIfExists('theme.changelog_url');
        });
    }
};
```

`migrator->add('group.property', $default)` seeds the initial value; keep it consistent with the class property's default.

## Filament Settings Page

Expose settings through a Filament `SettingsPage`, pointing `$settings` at the settings class:

```php
use Filament\Pages\SettingsPage;

class ManageThemeSettings extends SettingsPage
{
    protected static string $settings = ThemeSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // fields named after the settings properties
        ]);
    }
}
```

## Attaching Files to a Settings Page

Settings classes are not Eloquent models, so uploaded files attach to the settings' backing **`SettingsProperty`** model instead. Use the `handling-file-uploads` skill for the Media Library setup itself (collections, conversions, disks, output); this section is only the settings wiring.

1. A shared `SettingsProperty` bridge model resolves a property by `group.name`:

    ```php
    use Spatie\LaravelSettings\Models\SettingsProperty as BaseSettingsProperty;

    class SettingsProperty extends BaseSettingsProperty
    {
        use HasUuids;

        public static function getInstance(string $property): ?static
        {
            [$group, $name] = explode('.', $property);

            return static::query()->where('group', $group)->where('name', $name)->first();
        }
    }
    ```

    In multi-tenant apps it also uses `UsesTenantConnection`.

2. A per-settings property model holds the media collections and conversions (a `HasMedia` model — see `handling-file-uploads`):

    ```php
    class LoginSettingsProperty extends SettingsProperty implements HasMedia
    {
        use InteractsWithMedia;

        public function registerMediaCollections(): void
        {
            $this->addMediaCollection('header_image')->singleFile();
        }

        public function registerMediaConversions(?Media $media = null): void
        {
            $this->addMediaConversion('login')->format('webp')->width(1000);
        }
    }
    ```

3. The settings class exposes it:

    ```php
    public static function getSettingsPropertyModel(string $property): ?LoginSettingsProperty
    {
        return LoginSettingsProperty::getInstance($property);
    }
    ```

4. Bind the Filament upload to that model instance with `->model(...)`:

    ```php
    SpatieMediaLibraryFileUpload::make('header_image')
        ->collection('header_image')
        ->disk('s3')
        ->model(LoginSettings::getSettingsPropertyModel('login.header_image'));
    ```

5. Output it via a temporary URL and the conversion (private disk):

    ```blade
    @php
        $url = $loginSettings
            ::getSettingsPropertyModel('login.header_image')
            ?->getFirstTemporaryUrl(now()->addMinute(), 'header_image', 'login');
    @endphp
    ```

---

Related skills: `handling-file-uploads` (Media Library mechanics), `writing-tests` (testing a settings page).
