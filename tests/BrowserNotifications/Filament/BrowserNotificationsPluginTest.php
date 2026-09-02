<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;
use CanyonGBS\Common\BrowserNotifications\Filament\BrowserNotificationsPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Workbench\App\Models\BrowserNotificationUser;

it('renders the permission prompt only when the consuming app enables the feature', function () {
    Filament::setCurrentPanel('testing');
    Filament::bootCurrentPanel();

    $this->actingAs(BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']));

    expect(Filament::getCurrentPanel()?->getPlugin('browser-notifications'))
        ->toBeInstanceOf(BrowserNotificationsPlugin::class)
        ->and(FilamentView::renderHook(PanelsRenderHook::HEAD_END)->toHtml())
        ->toContain(Auth::user()->getBrowserNotificationStorageKey())
        ->and(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())
        ->toContain('x-data="browserNotificationsPrompt"');

    app(BrowserNotificationsManager::class)->availableUsing(fn (): bool => false);

    expect(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())
        ->not->toContain('x-data="browserNotificationsPrompt"');
});

it('allows the permission prompt to be disabled', function () {
    $panel = Panel::make()
        ->id('browser-notifications-without-prompt')
        ->plugin(BrowserNotificationsPlugin::make()->prompt(false));
    $renderHooks = (new ReflectionProperty(Panel::class, 'renderHooks'))->getValue($panel);

    expect($renderHooks)
        ->toHaveKey(PanelsRenderHook::HEAD_END)
        ->not->toHaveKey(PanelsRenderHook::BODY_END);
});

it('renders a loading state while checking browser notification status', function () {
    expect(view('common::browser-notifications.settings')->render())
        ->toContain('x-show="status === \'loading\'"')
        ->toContain('fi-loading-indicator')
        ->toContain('Checking desktop notification status')
        ->toContain('x-show="status !== \'loading\'"');
});

it('records browser opt-out before independently cleaning up the subscription', function () {
    $script = file_get_contents(dirname(__DIR__, 3) . '/resources/js/dist/filament/browser-notifications.js');

    expect($script)->not->toBeFalse();

    $unsubscribe = str($script)->between(
        'const browserNotificationsUnsubscribe = async (subscription) => {',
        "\ndocument.addEventListener('alpine:init'",
    )->toString();

    expect(strpos($unsubscribe, "browserNotificationsStorage.set('browser_notifications_opted_out', '1')"))
        ->toBeLessThan(strpos($unsubscribe, 'await subscription.unsubscribe()'))
        ->and(strpos($unsubscribe, 'await subscription.unsubscribe()'))
        ->toBeLessThan(strpos($unsubscribe, 'await browserNotificationsRequest('))
        ->and(substr_count($unsubscribe, 'catch (error)'))
        ->toBe(2);
});
