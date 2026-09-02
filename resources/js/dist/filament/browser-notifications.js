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

const browserNotificationsMeta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;

const browserNotificationsSupported = () =>
    'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

const browserNotificationsStorageKey = (key) =>
    `${key}:${browserNotificationsMeta('browser-notifications-storage-key') ?? 'default'}`;

const browserNotificationsStorage = {
    get(key) {
        try {
            return localStorage.getItem(browserNotificationsStorageKey(key));
        } catch {
            return null;
        }
    },

    remove(key) {
        try {
            localStorage.removeItem(browserNotificationsStorageKey(key));
        } catch {}
    },

    set(key, value) {
        try {
            localStorage.setItem(browserNotificationsStorageKey(key), value);
        } catch {}
    },
};

const browserNotificationsRegistration = async () => {
    const serviceWorkerUrl = browserNotificationsMeta('browser-notifications-service-worker-url');

    if (!serviceWorkerUrl) {
        throw new Error('Browser notification service worker URL is missing.');
    }

    await navigator.serviceWorker.register(serviceWorkerUrl, { scope: '/' });

    return navigator.serviceWorker.ready;
};

const browserNotificationsApplicationServerKey = () => {
    const value = browserNotificationsMeta('vapid-public-key');

    if (!value) {
        throw new Error('VAPID public key is missing.');
    }

    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const decoded = window.atob(base64);

    return Uint8Array.from(decoded, (character) => character.charCodeAt(0));
};

const browserNotificationsRequest = async (url, method, body) => {
    if (!url) {
        throw new Error('Browser notification endpoint is missing.');
    }

    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Browser notification request failed with status ${response.status}.`);
    }
};

const browserNotificationsStore = async (subscription) => {
    const subscriptionJson = subscription.toJSON();

    await browserNotificationsRequest(browserNotificationsMeta('browser-notifications-subscribe-url'), 'POST', {
        endpoint: subscription.endpoint,
        keys: subscriptionJson.keys,
        contentEncoding: 'aes128gcm',
    });
};

const browserNotificationsSubscribe = async () => {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return null;
    }

    const registration = await browserNotificationsRegistration();
    const existingSubscription = await registration.pushManager.getSubscription();
    const subscription =
        existingSubscription ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: browserNotificationsApplicationServerKey(),
        }));

    await browserNotificationsStore(subscription);

    return subscription;
};

const browserNotificationsUnsubscribe = async (subscription) => {
    browserNotificationsStorage.set('browser_notifications_opted_out', '1');

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;

    try {
        await subscription.unsubscribe();
    } catch (error) {
        console.error('[BrowserNotifications] Local unsubscription failed.', error);
    }

    try {
        await browserNotificationsRequest(browserNotificationsMeta('browser-notifications-unsubscribe-url'), 'DELETE', {
            endpoint,
        });
    } catch (error) {
        console.error('[BrowserNotifications] Server subscription cleanup failed.', error);
    }
};

document.addEventListener('alpine:init', () => {
    Alpine.data('browserNotificationsPrompt', () => ({
        showPrompt: false,

        async init() {
            if (document.querySelector('[data-browser-notifications-settings]') || !browserNotificationsSupported()) {
                return;
            }

            if (this.isOptedOut()) {
                return;
            }

            try {
                const registration = await browserNotificationsRegistration();
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await browserNotificationsStore(subscription);

                    return;
                }

                if (Notification.permission === 'denied' || this.isDismissed()) {
                    return;
                }

                const delay = Number(this.$el.dataset.promptDelay || 2) * 1000;
                window.setTimeout(() => (this.showPrompt = true), delay);
            } catch {
                this.showPrompt = false;
            }
        },

        async subscribe() {
            this.showPrompt = false;

            try {
                const subscription = await browserNotificationsSubscribe();

                if (subscription) {
                    browserNotificationsStorage.remove('browser_notifications_opted_out');
                }
            } catch (error) {
                console.error('[BrowserNotifications] Subscription failed.', error);
            }
        },

        dismiss() {
            this.showPrompt = false;

            const days = Number(this.$el.dataset.dismissCooldownDays || 7);
            browserNotificationsStorage.set(
                'browser_notifications_dismissed_until',
                String(Date.now() + days * 86400000),
            );
        },

        isDismissed() {
            const dismissedUntil = Number(
                browserNotificationsStorage.get('browser_notifications_dismissed_until') || 0,
            );

            return dismissedUntil > Date.now();
        },

        isOptedOut() {
            return browserNotificationsStorage.get('browser_notifications_opted_out') === '1';
        },
    }));

    Alpine.data('browserNotificationsSettings', () => ({
        status: 'loading',
        registration: null,
        subscription: null,

        async init() {
            if (!browserNotificationsSupported()) {
                this.status = 'unsupported';

                return;
            }

            try {
                this.registration = await browserNotificationsRegistration();
                this.subscription = await this.registration.pushManager.getSubscription();

                if (browserNotificationsStorage.get('browser_notifications_opted_out') === '1') {
                    await browserNotificationsUnsubscribe(this.subscription);

                    this.subscription = null;
                    this.status = 'inactive';

                    return;
                }

                if (Notification.permission === 'denied') {
                    this.status = 'denied';
                } else if (this.subscription) {
                    await browserNotificationsStore(this.subscription);
                    this.status = 'active';
                } else {
                    this.status = 'inactive';
                }
            } catch {
                this.status = 'error';
            }
        },

        async subscribe() {
            try {
                this.subscription = await browserNotificationsSubscribe();

                if (this.subscription) {
                    browserNotificationsStorage.remove('browser_notifications_opted_out');
                    this.status = 'active';
                } else {
                    this.status = Notification.permission === 'denied' ? 'denied' : 'inactive';
                }
            } catch (error) {
                console.error('[BrowserNotifications] Subscription failed.', error);
                this.status = 'error';
            }
        },

        async unsubscribe() {
            const subscription = this.subscription;

            this.subscription = null;
            this.status = 'inactive';

            await browserNotificationsUnsubscribe(subscription);
        },
    }));
});
