<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

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

namespace CanyonGBS\Common\Filament\Forms\Components;

use CanyonGBS\Common\Enums\Color;
use Filament\Forms\Components\Select;

class ColorSelect
{
    public static function make(string $name = 'color'): Select
    {
        return Select::make($name)
            ->allowHtml()
            ->native(false)
            ->options(collect(Color::cases())
                ->mapWithKeys(fn (Color $color): array => [
                    $color->value => <<<HTML
                        <div style="display: flex; align-items: center; gap: 0.5rem">
                            <div style="display: block; height: 1.25rem; width: 1.25rem; border-radius: 100%; background: {$color->getRgb()}"></div>
                            <span>{$color->getLabel()}</span>
                        </div>
                        HTML,
                ])
                ->all())
            ->enum(Color::class);
    }
}
