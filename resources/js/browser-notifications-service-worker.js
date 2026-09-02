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

const receivedNotificationsCache = 'common-browser-notifications-received-v1';
const receivedNotificationsLimit = 100;

const showNotificationOnce = async (payload) => {
    const notificationId = payload.data?.notification_id;

    if (!notificationId) {
        return self.registration.showNotification(payload.title ?? 'Notification', {
            body: payload.body ?? '',
            icon: payload.icon ?? '/favicon.ico',
            badge: payload.badge ?? '/favicon.ico',
            tag: payload.tag,
            data: payload.data ?? {},
        });
    }

    const cache = await caches.open(receivedNotificationsCache);
    const cacheKey = new Request(new URL(`/browser-notifications/received/${notificationId}`, self.location.origin));

    if (await cache.match(cacheKey)) {
        return;
    }

    await self.registration.showNotification(payload.title ?? 'Notification', {
        body: payload.body ?? '',
        icon: payload.icon ?? '/favicon.ico',
        badge: payload.badge ?? '/favicon.ico',
        tag: payload.tag ?? notificationId,
        data: payload.data ?? {},
    });

    await cache.put(cacheKey, new Response(''));

    const cacheKeys = await cache.keys();

    await Promise.all(cacheKeys.slice(0, -receivedNotificationsLimit).map((key) => cache.delete(key)));
};

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();

    event.waitUntil(showNotificationOnce(payload));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const actionUrl = event.notification.data?.action_url ?? '/';

    if (event.notification.data?.open_in_new_tab) {
        event.waitUntil(clients.openWindow(actionUrl));

        return;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (clientList) => {
            const client = clientList.find((candidate) => new URL(candidate.url).origin === self.location.origin);

            if (!client) {
                return clients.openWindow(actionUrl);
            }

            await client.focus();

            return client.navigate(actionUrl);
        }),
    );
});
