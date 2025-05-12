<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Notice:

    - This software is closed source and the source code is a trade secret.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ is a registered trademarks of Canyon GBS LLC, and we are
      committed to enforcing and protecting our trademarks vigorously.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace CanyonGBS\Common\Database\Migrations\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;

trait CanModifySettings
{
    /**
     * @param Closure(mixed): mixed $modifyPayload
     */
    public function updateSettings(string $group, string $name, Closure $modifyPayload, bool $isEncrypted = false): void
    {
        $payload = $this->getSetting($group, $name, $isEncrypted);

        $payload = $modifyPayload($payload);

        if ($isEncrypted) {
            $payload = encrypt($payload);
        }

        $payload = json_encode($payload);

        DB::table('settings')
            ->where('group', $group)
            ->where('name', $name)
            ->update([
                'payload' => $payload,
                'updated_at' => now(),
            ]);
    }

    public function getSetting(string $group, string $name, bool $isEncrypted = false): mixed
    {
        $payload = DB::table('settings')
            ->where('group', $group)
            ->where('name', $name)
            ->value('payload');

        $payload = json_decode($payload);

        if ($isEncrypted) {
            $payload = decrypt($payload);
        }

        return $payload;
    }
}
