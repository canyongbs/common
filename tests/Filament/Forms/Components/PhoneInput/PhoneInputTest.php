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
use Filament\Facades\Filament;
use libphonenumber\PhoneNumberType;
use Livewire\Livewire;
use Propaganistas\LaravelPhone\Rules\Phone as PhoneRule;
use Workbench\App\Filament\Resources\PhoneContacts\Pages\CreatePhoneContact;
use Workbench\App\Filament\Resources\PhoneContacts\Pages\EditPhoneContact;
use Workbench\App\Filament\Resources\PhoneContacts\PhoneContactResource;
use Workbench\App\Models\PhoneContact;
use Workbench\App\Models\User;

beforeEach(function (): void {
    PhoneContactResource::reset();

    Filament::setCurrentPanel('testing');

    $this->actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com']));
});

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
    $phoneInput = PhoneInput::make('phone_number')->enableIpLookup();

    expect($phoneInput->canPerformIpLookup())->toBeTrue();

    $phoneInput->disableLookup();

    expect($phoneInput->canPerformIpLookup())->toBeFalse()
        ->and($phoneInput->handleLookup())->toBeNull();
});

describe('shared defaults', function (): void {
    it('applies the shared defaults', function (): void {
        $phoneInput = PhoneInput::make('phone_number');

        $rules = $phoneInput->getValidationRules();

        expect($phoneInput->getDefaultCountry())->toBe('US')
            ->and($phoneInput->getInitialCountry())->toBe('US')
            ->and($phoneInput->isStrictMode())->toBeTrue()
            ->and($phoneInput->canPerformIpLookup())->toBeFalse()
            ->and(collect($rules)->contains(fn (mixed $rule): bool => $rule instanceof PhoneRule))->toBeTrue();
    });

    it('allows an app to override the defaults', function (): void {
        $phoneInput = PhoneInput::make('phone_number')
            ->defaultCountry('CA')
            ->initialCountry('auto')
            ->strictMode(false)
            ->enableIpLookup();

        expect($phoneInput->getDefaultCountry())->toBe('CA')
            ->and($phoneInput->getInitialCountry())->toBe('auto')
            ->and($phoneInput->isStrictMode())->toBeFalse()
            ->and($phoneInput->canPerformIpLookup())->toBeTrue();
    });
});

describe('in a form', function (): void {
    it('hydrates the phone number given a specific number and country', function (): void {
        $contact = PhoneContact::create([
            'name' => 'test',
            'phone' => '(555) 123-4567',
            'phone_country' => 'US',
        ]);

        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input->countryStatePath('phone_country');

        Livewire::test(EditPhoneContact::class, ['record' => $contact->getRouteKey()])
            ->assertSuccessful()
            ->assertSet('data.phone', '+15551234567');
    });

    it('hydrates the phone number given a specific number and the default country', function (): void {
        $contact = PhoneContact::create([
            'name' => 'test',
            'phone' => '212-975-4846',
        ]);

        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input->defaultCountry('US');

        Livewire::test(EditPhoneContact::class, ['record' => $contact->getRouteKey()])
            ->assertSuccessful()
            ->assertSet('data.phone', '+12129754846');
    });

    it('saves the phone number in the configured input number format', function (PhoneInputNumberType $type, string $expected): void {
        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input->inputNumberFormat($type);

        Livewire::test(CreatePhoneContact::class)
            ->fillForm([
                'name' => fake()->name(),
                'phone' => '+905301111111',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(PhoneContact::sole()->phone)->toBe($expected);
    })->with([
        'E164' => [PhoneInputNumberType::E164, '+905301111111'],
        'international' => [PhoneInputNumberType::INTERNATIONAL, '+90 530 111 11 11'],
        'national' => [PhoneInputNumberType::NATIONAL, '0530 111 11 11'],
        'RFC3966' => [PhoneInputNumberType::RFC3966, 'tel:+90-530-111-11-11'],
    ]);

    it('validates the number for the configured country', function (string $country, string $phone, bool $passes, int | PhoneNumberType | null $type, bool $lenient): void {
        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input->validateFor($country, $type, $lenient);

        $component = Livewire::test(CreatePhoneContact::class)
            ->fillForm([
                'name' => fake()->name(),
                'phone' => $phone,
            ])
            ->call('create');

        if ($passes) {
            $component->assertHasNoFormErrors();
        } else {
            $component->assertHasFormErrors(['phone']);
        }
    })->with([
        'international number' => ['INTERNATIONAL', '+905301111111', true, null, false],
        'number from another country' => ['TR', '+18143511527', false, null, false],
        'national number when lenient' => ['TR', '5301111111', true, null, true],
        'number of the expected type' => ['TR', '+902125111111', true, PhoneNumberType::FIXED_LINE, false],
    ]);

    it('saves the country state alongside the number', function (): void {
        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input->countryStatePath('phone_country');

        Livewire::test(CreatePhoneContact::class)
            ->fillForm([
                'name' => fake()->name(),
                'phone' => '+905301111111',
                'phone_country' => 'TR',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(PhoneContact::sole())
            ->phone->toBe('0530 111 11 11')
            ->phone_country->toBe('TR');
    });

    it('respects an explicit input number format when using a country state path', function (): void {
        PhoneContactResource::$configurePhoneInput = fn (PhoneInput $input): PhoneInput => $input
            ->countryStatePath('phone_country')
            ->inputNumberFormat(PhoneInputNumberType::E164);

        Livewire::test(CreatePhoneContact::class)
            ->fillForm([
                'name' => fake()->name(),
                'phone' => '+905301111111',
                'phone_country' => 'TR',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(PhoneContact::sole())
            ->phone->toBe('+905301111111')
            ->phone_country->toBe('TR');
    });
});
