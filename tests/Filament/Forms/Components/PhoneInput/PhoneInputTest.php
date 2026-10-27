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

use CanyonGBS\Common\Filament\Forms\Components\PhoneInput\PhoneInput;
use CanyonGBS\Common\Filament\Forms\Components\PhoneInput\PhoneInputNumberType;

it('evaluates configured phone input options', function (): void {
    $phoneInput = PhoneInput::make('phone_number')
        ->allowDropdown(false)
        ->countryOrder(fn (): array => ['us', 'ca'])
        ->defaultCountry(fn (): string => 'US')
        ->displayNumberFormat(PhoneInputNumberType::INTERNATIONAL)
        ->focusNumberFormat(PhoneInputNumberType::NATIONAL)
        ->i18n(fn (): array => ['searchPlaceholder' => 'Find a country'])
        ->inputNumberFormat(PhoneInputNumberType::RFC3966)
        ->onlyCountries(fn (): array => ['us', 'ca']);

    expect($phoneInput->isAllowDropdown())->toBeFalse()
        ->and($phoneInput->getCountryOrder())->toBe(['us', 'ca'])
        ->and($phoneInput->getDefaultCountry())->toBe('US')
        ->and($phoneInput->getDisplayNumberFormat())->toBe(PhoneInputNumberType::INTERNATIONAL->value)
        ->and($phoneInput->getFocusNumberFormat())->toBe(PhoneInputNumberType::NATIONAL->value)
        ->and($phoneInput->getI18n())->toBe(['searchPlaceholder' => 'Find a country'])
        ->and($phoneInput->getInputNumberFormat())->toBe(PhoneInputNumberType::RFC3966->value)
        ->and($phoneInput->getOnlyCountries())->toBe(['us', 'ca'])
        ->and($phoneInput->hasExplicitInputNumberFormat())->toBeTrue();
});

it('can disable IP country lookup', function (): void {
    $phoneInput = PhoneInput::make('phone_number');

    expect($phoneInput->canPerformIpLookup())->toBeTrue();

    $phoneInput->disableLookup();

    expect($phoneInput->canPerformIpLookup())->toBeFalse()
        ->and($phoneInput->handleLookup())->toBeNull();
});
