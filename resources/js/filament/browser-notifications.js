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

// Mirrors Filament database (in-app) notifications as native desktop notifications.
// Database notifications arrive via polling with no per-item client event, so we
// observe the notifications list DOM and fire a desktop notification for each newly
// added unread item, deduplicated by notification id.

const NOTIFICATION_CTN_SELECTOR = '.fi-no-notification-unread-ctn';
const WIRE_KEY_SUFFIX = '.database-notifications.ctn';

// Ids we have already accounted for, so a notification is never shown twice across
// polls, Livewire DOM morphs, or read/unread state changes.
const seenNotificationIds = new Set();

let hasInitialized = false;

function isSupported() {
    return typeof window !== 'undefined' && 'Notification' in window;
}

function notificationIdFromNode(node) {
    const wireKey = node.getAttribute('wire:key') ?? '';

    if (!wireKey.endsWith(WIRE_KEY_SUFFIX)) {
        return null;
    }

    return wireKey.slice(0, -WIRE_KEY_SUFFIX.length);
}

function extractTitle(node) {
    const titleNode = node.querySelector('[class*="fi-no-notification-title"]');

    return titleNode?.textContent?.trim() || null;
}

function extractBody(node) {
    const bodyNode = node.querySelector('[class*="fi-no-notification-body"]');

    return bodyNode?.textContent?.trim() || '';
}

function extractDestination(node) {
    const link = node.querySelector('a[href]');

    return link?.getAttribute('href') || null;
}

function showDesktopNotification(node) {
    const title = extractTitle(node);

    if (!title) {
        return;
    }

    const destination = extractDestination(node);

    const notification = new Notification(title, {
        body: extractBody(node),
        icon: '/favicon.ico',
    });

    notification.addEventListener('click', () => {
        window.focus();

        if (destination) {
            window.location.href = destination;
        }

        notification.close();
    });
}

function handleNotificationNode(node) {
    const id = notificationIdFromNode(node);

    if (id === null || seenNotificationIds.has(id)) {
        return;
    }

    seenNotificationIds.add(id);

    // During the first pass we only record existing notifications so that items
    // already present on page load do not trigger a desktop notification.
    if (!hasInitialized) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    showDesktopNotification(node);
}

function scan(root) {
    if (!(root instanceof Element)) {
        return;
    }

    if (root.matches?.(NOTIFICATION_CTN_SELECTOR)) {
        handleNotificationNode(root);
    }

    root.querySelectorAll?.(NOTIFICATION_CTN_SELECTOR).forEach(handleNotificationNode);
}

function requestPermissionOnFirstGesture() {
    if (Notification.permission !== 'default') {
        return;
    }

    const request = () => {
        window.removeEventListener('click', request);
        window.removeEventListener('keydown', request);

        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    };

    window.addEventListener('click', request, { once: true });
    window.addEventListener('keydown', request, { once: true });
}

function start() {
    if (!isSupported()) {
        return;
    }

    // Seed the seen set with whatever is already rendered before we begin observing.
    scan(document.body);
    hasInitialized = true;

    requestPermissionOnFirstGesture();

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((addedNode) => scan(addedNode));
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}
