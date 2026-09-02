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

final class BrowserNotificationSubscriptionKeyValidator
{
    public function isValidPublicKey(string $publicKey): bool
    {
        $decodedPublicKey = $this->decodeBase64Url($publicKey);

        if ($decodedPublicKey === null || strlen($decodedPublicKey) !== 65 || $decodedPublicKey[0] !== "\x04") {
            return false;
        }

        $derPrefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        assert(is_string($derPrefix));

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($derPrefix . $decodedPublicKey), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $opensslPublicKey = openssl_pkey_get_public($pem);

        if ($opensslPublicKey === false) {
            return false;
        }

        $details = openssl_pkey_get_details($opensslPublicKey);

        return is_array($details)
            && $details['type'] === OPENSSL_KEYTYPE_EC
            && $details['bits'] === 256
            && ($details['ec']['curve_name'] ?? null) === 'prime256v1';
    }

    public function isValidAuthToken(string $authToken): bool
    {
        $decodedAuthToken = $this->decodeBase64Url($authToken);

        return $decodedAuthToken !== null && strlen($decodedAuthToken) === 16;
    }

    protected function decodeBase64Url(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) !== 1) {
            return null;
        }

        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - (strlen($value) % 4)) % 4);
        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : null;
    }
}
