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

use CanyonGBS\Common\Enums\Color;

describe('Color enum values', function () {
    it('exposes the additional common colors', function () {
        expect(Color::Black->value)->toBe('black')
            ->and(Color::LightGray->value)->toBe('light-gray')
            ->and(Color::Gray->value)->toBe('gray')
            ->and(Color::DarkGray->value)->toBe('dark-gray')
            ->and(Color::Navy->value)->toBe('navy');
    });
});

describe('Color::isShade()', function () {
    it('marks the additional common colors as shades', function (Color $color) {
        expect($color->isShade())->toBeTrue();
    })->with([
        'black' => Color::Black,
        'light gray' => Color::LightGray,
        'dark gray' => Color::DarkGray,
        'navy' => Color::Navy,
    ]);

    it('does not mark palette colors as shades', function (Color $color) {
        expect($color->isShade())->toBeFalse();
    })->with([
        'gray' => Color::Gray,
        'red' => Color::Red,
        'blue' => Color::Blue,
        'rose' => Color::Rose,
    ]);
});

describe('Color::getRgb()', function () {
    it('returns black for the Black shade', function () {
        expect(Color::Black->getRgb())->toBe('rgb(0, 0, 0)');
    });

    it('returns navy for the Navy shade', function () {
        expect(Color::Navy->getRgb())->toBe('rgb(0, 0, 128)');
    });

    it('returns an rgb string for every color', function (Color $color) {
        expect($color->getRgb())->toStartWith('rgb(');
    })->with(Color::cases());
});

describe('Color::getLabel()', function () {
    it('humanizes multi-word color names', function () {
        expect(Color::LightGray->getLabel())->toBe('Light Gray')
            ->and(Color::DarkGray->getLabel())->toBe('Dark Gray');
    });

    it('keeps single-word color names intact', function () {
        expect(Color::Gray->getLabel())->toBe('Gray')
            ->and(Color::Red->getLabel())->toBe('Red')
            ->and(Color::Navy->getLabel())->toBe('Navy');
    });
});
