{{--
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
--}}
<div x-data="browserNotificationsSettings" data-browser-notifications-settings>
    <x-filament::section>
        <x-slot name="heading">Desktop notifications</x-slot>
        <x-slot name="description">Control notifications for this browser.</x-slot>

        <div
            x-show="status === 'loading'"
            role="status"
            aria-live="polite"
            class="flex min-h-24 items-center justify-center"
        >
            <x-filament::loading-indicator class="h-8 w-8 text-primary-600 dark:text-primary-400" />
            <span class="sr-only">Checking desktop notification status</span>
        </div>

        <div x-show="status !== 'loading'" x-cloak class="space-y-3">
            <x-filament::badge x-show="status === 'active'" color="success" icon="heroicon-m-check-circle">
                Notifications active on this browser
            </x-filament::badge>

            <x-filament::badge x-show="status === 'denied'" color="danger" icon="heroicon-m-x-circle">
                Notifications blocked by browser
            </x-filament::badge>

            <p x-show="status === 'denied'" class="text-sm text-gray-600 dark:text-gray-400">
                Allow notifications in your browser's site settings, then reload this page.
            </p>

            <x-filament::badge x-show="status === 'unsupported'" color="gray" icon="heroicon-m-exclamation-triangle">
                This browser does not support desktop notifications
            </x-filament::badge>

            <x-filament::badge x-show="status === 'error'" color="danger" icon="heroicon-m-exclamation-circle">
                Notification settings could not be updated
            </x-filament::badge>

            <div>
                <x-filament::button
                    x-show="status === 'inactive'"
                    size="sm"
                    icon="heroicon-m-bell-alert"
                    x-on:click="subscribe()"
                >
                    Enable notifications
                </x-filament::button>

                <x-filament::button
                    x-show="status === 'active'"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-bell-slash"
                    x-on:click="unsubscribe()"
                >
                    Disable on this browser
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</div>
