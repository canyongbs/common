---
name: building-tabbed-record-pages
description: "Use when building or changing a Filament record page that brings a record's overview, edit forms, and related records together on one page with tabs — a `Manage<Model>` page registered as the resource's `view` route, whose contained tabs hold an infolist (optionally with per-section edit actions), the page's edit form, extra forms with their own Save button, relation managers, or widgets. Trigger when replacing separate View / Edit / `ManageRelatedRecords` pages or record sub-navigation with tabs, implementing `getTabComponents()`, adding a form or relation manager tab, linking to a tab with `?tab=`, handling old sub-page URLs, or testing such a page. Do not use for: resources that keep their separate record pages or sub-navigation, list pages, organizing schema and component classes (use `structuring-filament-code`), or general test conventions (use `writing-tests`)."
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Building Tabbed Record Pages

A tabbed record page is an alternative to record sub-navigation. Instead of separate View, Edit, and `ManageRelatedRecords` pages joined by sub-navigation, the resource's `view` route renders one `Manage<Model>` page whose contained tabs hold everything. Use it when building such a page or when asked to convert a resource to one; otherwise leave existing record pages as they are.

What each kind of tab is built with:

| Tab content                                              | Built with                                                                    |
| -------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Infolist, optionally with an edit action on each section | `Tab::make(...)->schema([EmbeddedSchema::make('infolist')])`                  |
| The page's main edit form, with the page's form actions  | `$this->getContentTabComponent()`                                             |
| An extra form with its own Save button                   | `Tab::make(...)->schema([$this->makeFormContentComponent('xForm', 'saveX')])` |
| A relation manager                                       | `$this->getRelationManagerTabComponent('<relation key>')`                     |
| Widgets                                                  | `...$this->getWidgetsSchemaComponents([...])` inside any tab                  |

`makeFormContentComponent()` is a small helper you add to the page (see below). The rest are Filament's and need **v4.15.2+ / v5.10.2+** — if `getTabComponents()` doesn't exist on the page, update Filament first.

## The Page

The page extends `EditRecord` (so its main form keeps Filament's validation, save, and lifecycle hooks) but presents itself as the view page. Filament stores the active tab in `$activeRelationManager` even when it isn't a relation manager; binding it to `tab` makes every tab linkable. The `''` key is the default tab, every other key becomes `?tab=<key>`, and relation manager keys come from the resource's `getRelations()`.

```php
/**
 * @property-read Schema $settingsForm
 */
class ManageProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    #[Url(as: 'tab')]
    public ?string $activeRelationManager = null;

    /** @var array<string, mixed>|null */
    public ?array $settingsData = [];

    public function getTitle(): string
    {
        $record = $this->getRecord();
        assert($record instanceof Project);

        return __('filament-panels::resources/pages/view-record.title', ['label' => $record->name]);
    }

    public function getBreadcrumb(): string
    {
        return __('filament-panels::resources/pages/view-record.breadcrumb');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getRelationManagersContentComponent(): Component
    {
        $tabs = parent::getRelationManagersContentComponent();
        assert($tabs instanceof Tabs);

        return $tabs->contained();
    }

    public function getTabComponents(): array
    {
        $record = $this->getRecord();

        return [
            '' => Tab::make('Overview')
                ->visible(fn (): bool => ProjectResource::canView($record))
                ->schema([
                    EmbeddedSchema::make('infolist'),
                    ...$this->getWidgetsSchemaComponents([ProjectStatsOverview::class]),
                ]),
            'edit' => $this->getContentTabComponent()
                ->label('Edit')
                ->visible(fn (): bool => ProjectResource::canEdit($record)),
            'settings' => Tab::make('Settings')
                ->visible(fn (): bool => ProjectResource::canEdit($record))
                ->schema([
                    $this->makeFormContentComponent('settingsForm', 'saveSettings'),
                ]),
            'members' => $this->getRelationManagerTabComponent('members'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema)
            ->record($this->getRecord());
    }

    public function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public function settingsForm(Schema $schema): Schema
    {
        return ProjectSettingsForm::configure($schema)
            ->model($this->getRecord())
            ->operation('edit')
            ->statePath('settingsData');
    }
}
```

- Name the page `Manage<Model>` and register it as `'view' => ManageProject::route('/{record}')`. Drop `getRecordSubNavigation()` and any `$subNavigationPosition`.
- Put widgets inside a tab, as above. `getFooterWidgets()` would render them under every tab.
- The page declares several schemas, so each lives in its own schema class (see `structuring-filament-code`); the page only wires them together.
- The content tab shows `getFormActions()`. The default Cancel button navigates back, so drop it or point it at the `view` URL.

## Authorization

`EditRecord` only lets in users who can **edit**. Override `authorizeAccess()` so anyone who can use at least one tab gets in, hide each tab from users who can't use it, and re-check edit access in every save method — Livewire methods can be called even when their tab is hidden.

```php
protected function authorizeAccess(): void
{
    $record = $this->getRecord();

    abort_unless(
        ProjectResource::canView($record)
            || ProjectResource::canEdit($record)
            || filled($this->getCachedRelationManagers()),
        403,
    );
}

public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
{
    ProjectResource::authorizeEdit($this->getRecord());

    parent::save($shouldRedirect, $shouldSendSavedNotification);
}

public function saveFormComponentOnly(Component $component): void
{
    ProjectResource::authorizeEdit($this->getRecord());

    parent::saveFormComponentOnly($component);
}
```

Relation manager tabs hide themselves through the manager's `canViewForRecord()`, which also makes mounting the manager directly return 403.

Tabs don't fall back to a visible one: an active tab that is hidden from the user, or no longer exists, renders an empty panel. That happens to anyone who gets in without being able to see the `''` tab, and to anyone following an old or mistyped `?tab=` link. Always open the first visible tab instead:

```php
public function mount(int | string $record): void
{
    parent::mount($record);

    $visibleTabs = array_map(strval(...), array_keys(array_filter(
        $this->getTabComponents(),
        fn (?Tab $tab): bool => $tab?->isVisible() ?? false,
    )));

    if (! in_array($this->activeRelationManager ?? '', $visibleTabs, strict: true)) {
        $firstVisibleTab = $visibleTabs[0] ?? '';

        $this->activeRelationManager = ($firstVisibleTab === '') ? null : $firstVisibleTab;
    }
}
```

## Extra Forms With Their Own Save Button

Each extra form is a schema method with its own `statePath()`, filled in `fillForm()` and saved by its own Livewire method. Wrapping it in a `Form` gives it its own submit handler and Save button:

```php
protected function makeFormContentComponent(string $schema, string $submitHandler): Component
{
    return Form::make([EmbeddedSchema::make($schema)])
        ->id($schema)
        ->livewireSubmitHandler($submitHandler)
        ->footer([
            Actions::make([
                Action::make($submitHandler)
                    ->label('Save')
                    ->submit($submitHandler),
            ]),
        ]);
}

protected function fillForm(): void
{
    $record = $this->getRecord();

    if (! ProjectResource::canEdit($record)) {
        return;
    }

    parent::fillForm();

    $this->settingsForm->fill($record->attributesToArray());
}

public function saveSettings(): void
{
    $record = $this->getRecord();

    assert($record instanceof Project);

    ProjectResource::authorizeEdit($record);

    DB::transaction(function () use ($record): void {
        // `getState()` validates and saves the schema's relationships.
        $data = $this->settingsForm->getState();

        $record->is_public = $data['is_public'];
        $record->default_role = $data['default_role'];
        $record->save();
    });

    $this->getSavedNotification()?->send();
}
```

Form state is kept while switching tabs, so unsaved edits in one tab survive a visit to another.

To show the main form **read-only** to users who can only view, make its tab visible with `canView()`, keep `parent::fillForm()` unconditional, disable the schema with `->disabled(! ProjectResource::canEdit($this->getRecord()))`, and return no form actions for them from `getFormActions()`.

## Infolist Sections With Their Own Edit Action

For small, independent groups of fields, edit them in place from the overview instead of in a separate form tab. Put an `EditAction` in the section's header, reusing the same schema class the create form nests:

```php
Section::make('Details')
    ->key('details')
    ->headerActions([
        EditAction::make('editDetails')
            ->modalHeading('Edit details')
            ->slideOver()
            ->schema(fn (Schema $schema): Schema => ProjectDetailsForm::configure($schema)),
    ])
    ->schema([
        TextEntry::make('name'),
        TextEntry::make('description'),
    ]),
```

`EditAction` already fills, validates, saves (including relationships), sends the notification, and hides itself without the `update` permission. Give each action a unique name and each section a `key()` so tests can target them.

Don't edit the same field from both a section action and a form tab: the form tab keeps the state it loaded and would overwrite the change on its next save.

## Resource and Links

```php
public static function getRelations(): array
{
    return [
        'members' => MembersRelationManager::class,
    ];
}

public static function getPages(): array
{
    return [
        'index' => ListProjects::route('/'),
        'create' => CreateProject::route('/create'),
        'view' => ManageProject::route('/{record}'),
        'edit' => RedirectToProjectTab::route('/{record}/edit'), // Linked from sent notifications.
    ];
}
```

- Link to a tab with `ProjectResource::getUrl('view', ['record' => $record, 'tab' => 'members'])` — including list-table `EditAction` URLs, widget links, and post-delete redirects.
- Saving keeps the user on the current tab. If a save replaces the record (e.g. versioning), redirect to `view` with the same `tab`.
- A nested resource whose records are listed in a relation manager tab has no index page of its own, so point its index URL (used by its breadcrumb and redirects) at that tab. The parent record arrives under the parent's route parameter name:

    ```php
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        $project = $parameters['project'] ?? null;
        unset($parameters['project']);

        return ProjectResource::getUrl('view', [...$parameters, 'record' => $project, 'tab' => 'tasks'], $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }
    ```

## Old Sub-Page URLs

When sub-pages become tabs, remove their routes and point every link in the app at `view` with the right `tab`. Keep an old route only when notifications that were already sent (mail, database, etc.) link to it, because those links can't be updated: point it at a redirect page, and change the notification to link to the tab from now on. Each kept route's last URL segment must be an existing tab key, or the page opens on an empty tab:

```php
class RedirectToProjectTab extends Page
{
    protected static string $resource = ProjectResource::class;

    public function mount(int | string $record): void
    {
        $this->redirect(ProjectResource::getUrl('view', [
            ...request()->query(),
            'record' => $record,
            'tab' => last(request()->segments()),
        ], shouldGuessMissingParameters: true));
    }
}
```

If a kept route was a `ManageRelatedRecords` page, its links may carry state the relation manager doesn't read from the URL by default:

- **Table state:** re-declare the URL properties those links used, e.g. `#[Url(as: 'filters')] public ?array $tableFilters = null;`.
- **Its own tabs:** alias `$activeTab` away from `tab`, which the page owns (`#[Url(as: 'state')] public ?string $activeTab = null;`), and have the redirect move the link's incoming `tab` value to `state` before overwriting `tab`.
- **Table actions (`?tableAction=…`):** the redirect must also add `actionComponent` set to the manager's `getDefaultActionUrlIdentifier()`, and the manager should clear that state in `mountDefaultActions()` so it doesn't reopen on every tab switch.

## Testing

Follow `writing-tests`. Tab-specific helpers:

```php
// Anything other than the main form must be named — the page's default testing schema is `form`.
->assertSchemaStateSet(['details.name' => 'Apollo'], 'infolist')
->callAction(TestAction::make('editDetails')->schemaComponent('details', schema: 'infolist'), $data)
->fillForm($data, 'settingsForm')->call('saveSettings')

// A hidden action inside a schema component can't be resolved, so assert it doesn't exist.
->assertActionDoesNotExist(TestAction::make('editDetails')->schemaComponent('details', schema: 'infolist'))

// The main form's actions live inside its tab.
TestAction::make('archive')->schemaComponent('relationManagerTabs.edit.form-actions', schema: 'content')

// Open on a tab, or switch tabs mid-test.
Livewire::withQueryParams(['tab' => 'settings'])->test(ManageProject::class, ['record' => $project->getRouteKey()]);
->set('activeRelationManager', 'edit')

// Relation managers are tested on their own; unauthorized users can't mount them.
Livewire::test(MembersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ManageProject::class])
    ->assertForbidden();
```

Cover which tabs each kind of user sees (read the labels of the rendered `role="tab"` elements), which tab opens when the requested one is hidden or unknown, that only the selected tab renders its form, that unsaved edits survive switching tabs, that every save method rejects users who can't edit, and that each kept old URL redirects to its tab.

## Checklist

- One `Manage<Model>` page extending `EditRecord`, registered as `view`, with the view title and breadcrumb; no record sub-navigation.
- `#[Url(as: 'tab')] $activeRelationManager`, combined tabs, `->contained()`; `''` is the default tab; `mount()` falls back to the first visible tab.
- Each tab's content comes from a schema class, the content tab, `makeFormContentComponent()`, or `getRelationManagerTabComponent()`.
- `authorizeAccess()` admits anyone who can use a tab; each tab is hidden from users who can't use it; `save()`, `saveFormComponentOnly()`, and every extra save method call `authorizeEdit()`.
- Infolist sections edit themselves with an `EditAction` nesting the shared schema class.
- Every link to the record points at `view` with the right `tab`; old routes are removed unless sent notifications link to them, in which case they redirect; nested resources' `getIndexUrl()` points at their tab.
