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

namespace CanyonGBS\Common\BrowserNotifications\Support;

use CanyonGBS\Common\BrowserNotifications\DataTransferObjects\BrowserNotificationPayload;
use Illuminate\Notifications\DatabaseNotification;

class BrowserNotificationPayloadResolver
{
    public function resolve(DatabaseNotification $notification): ?BrowserNotificationPayload
    {
        $data = $notification->getAttribute('data');

        if (! is_array($data)) {
            return null;
        }

        if ((bool) ($data['silent'] ?? ($data['viewData']['silent'] ?? false))) {
            return null;
        }

        $rawTitle = $data['title'] ?? $data['subject'] ?? config('app.name', 'Notification');
        $rawBody = $data['body'] ?? $data['message'] ?? '';

        if (! is_string($rawTitle) || ! is_string($rawBody)) {
            return null;
        }

        $action = $this->resolveAction($data);
        $actionUrl = $action['url'] ?? $this->resolveEmbeddedUrl($rawTitle) ?? $this->resolveEmbeddedUrl($rawBody);

        if ($actionUrl === null) {
            return null;
        }

        return new BrowserNotificationPayload(
            id: (string) $notification->getKey(),
            title: $this->toPlainText($rawTitle),
            body: $this->toPlainText($rawBody),
            actionUrl: $actionUrl,
            openInNewTab: $action['openInNewTab'] ?? false,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{url: string, openInNewTab: bool}|null
     */
    protected function resolveAction(array $data): ?array
    {
        $action = $this->searchActions($data['actions'] ?? []);

        if ($action !== null) {
            return $action;
        }

        $url = $data['action_url'] ?? $data['url'] ?? null;

        return is_string($url) && filled($url)
            ? ['url' => $url, 'openInNewTab' => false]
            : null;
    }

    /**
     * @param mixed $actions
     *
     * @return array{url: string, openInNewTab: bool}|null
     */
    protected function searchActions(mixed $actions): ?array
    {
        if (! is_array($actions)) {
            return null;
        }

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $nestedAction = $this->searchActions($action['actions'] ?? null);

            if ($nestedAction !== null) {
                return $nestedAction;
            }

            $url = $action['url'] ?? null;

            if (is_string($url) && filled($url)) {
                return [
                    'url' => $url,
                    'openInNewTab' => (bool) ($action['shouldOpenUrlInNewTab'] ?? false),
                ];
            }
        }

        return null;
    }

    protected function resolveEmbeddedUrl(string $value): ?string
    {
        if (preg_match('/href=(?:"|\')(?<url>[^"\']+)(?:"|\')/i', $value, $matches) === 1) {
            return html_entity_decode($matches['url'], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/\[[^]]+]\((?<url>[^)]+)\)/', $value, $matches) === 1) {
            return html_entity_decode($matches['url'], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    protected function toPlainText(string $value): string
    {
        $value = preg_replace('/\[([^]]+)]\([^)]+\)/', '$1', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
