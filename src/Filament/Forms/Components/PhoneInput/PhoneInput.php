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

namespace CanyonGBS\Common\Filament\Forms\Components\PhoneInput;

use Closure;
use Filament\Forms\Components\Concerns\HasAffixes;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Contracts\HasAffixActions;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use Livewire\Attributes\Renderless;
use Propaganistas\LaravelPhone\Rules\Phone as PhoneRule;

class PhoneInput extends Field implements HasAffixActions
{
    use HasAffixes;
    use HasExtraInputAttributes;
    use HasPlaceholder;

    protected string $view = 'common::filament.forms.components.phone-input.phone-input';

    protected string | Closure | PhoneInputNumberType $displayNumberFormat = PhoneInputNumberType::NATIONAL;

    protected string | Closure | PhoneInputNumberType $inputNumberFormat = PhoneInputNumberType::E164;

    protected bool $hasExplicitInputNumberFormat = false;

    protected string | false | Closure | PhoneInputNumberType $focusNumberFormat = false;

    protected string | Closure $placeholderNumberType = 'MOBILE';

    protected bool | Closure $allowDropdown = true;

    protected string | Closure $autoPlaceholder = 'polite';

    protected bool | Closure $countrySearch = true;

    protected bool | Closure $formatAsYouType = true;

    protected string | null | Closure $dropdownContainer = null;

    /** @var array<int, string> | Closure */
    protected array | Closure $excludeCountries = [];

    protected bool | Closure $formatOnDisplay = true;

    protected string | Closure $initialCountry = 'auto';

    protected string | Closure $containerClass = '';

    /** @var array<string, mixed> | Closure */
    protected array | Closure $i18n = [];

    protected bool | Closure $nationalMode = true;

    protected bool | Closure $fixDropdownWidth = true;

    /** @var array<int, string> | Closure */
    protected array | Closure $onlyCountries = [];

    /** @var array<int, string> | null | Closure */
    protected array | null | Closure $countryOrder = null;

    protected string | RawJs | Closure | null $customPlaceholder = null;

    protected bool | Closure $showFlags = true;

    protected bool | Closure $separateDialCode = false;

    protected bool | Closure $useFullscreenPopup = false;

    protected bool | Closure $isStrictMode = false;

    /** @var array<string, mixed> | Closure */
    protected array | Closure $customOptions = [];

    protected string | Closure $cookieName = 'intlTelInputSelectedCountry';

    protected ?Closure $ipLookupCallback = null;

    protected bool | Closure $performIpLookup = true;

    protected string | Closure | null $countryStatePath = null;

    protected string | Closure | null $defaultCountry = null;

    protected bool $countryStatePathIsAbsolute = false;

    /** @var string | array<int, string> */
    protected string | array $validatedCountry = [];

    protected string | Closure | null $locale = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ipLookup(function (): ?string {
            return rescue(fn (): ?string => Http::get('https://ipinfo.io/json')->json('country'), app()->getLocale(), report: false);
        });

        $this->afterStateHydrated(function (PhoneInput $component, mixed $livewire, mixed $state): void {
            $country = null;

            if ($component->hasCountryStatePath()) {
                $country = data_get($livewire, $countryStatePath = $component->getCountryStatePath());

                data_set($livewire, $countryStatePath, $country);
            }

            if (! $state) {
                return;
            }

            $format = PhoneInputNumberType::from($component->getInputNumberFormat());

            $component->state($this->phoneFormat($state, $country, $format->toLibPhoneNumberFormat()));
        });

        $this->mutateDehydratedStateUsing(function (PhoneInput $component, mixed $state): mixed {
            if (! $state) {
                return null;
            }

            $format = PhoneInputNumberType::from($component->getInputNumberFormat());

            $country = $this->validatedCountry === 'INTERNATIONAL' ? null : $this->validatedCountry;

            if ($component->hasCountryStatePath()) {
                $countryStatePath = $component->getCountryStatePath();

                $country = data_get($component->getLivewire(), $countryStatePath);

                if (! $component->hasExplicitInputNumberFormat()) {
                    $format = PhoneInputNumberType::NATIONAL;
                }
            }

            return $this->phoneFormat($state, $country, $format->toLibPhoneNumberFormat());
        });
    }

    /**
     * @return array{country: string} | null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function handleLookup(): ?array
    {
        if (! $this->canPerformIpLookup()) {
            return null;
        }

        $country = $this->performIpLookup();

        if (! $country) {
            return null;
        }

        return ['country' => (string) $country];
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function dehydrateValidationRules(array &$rules): void
    {
        parent::dehydrateValidationRules($rules);

        if ($this->hasCountryStatePath()) {
            $countryStatePath = $this->getCountryStatePath();

            $rules[$countryStatePath] = ['nullable'];
        }
    }

    public function hasCountryStatePath(): bool
    {
        return $this->countryStatePath !== null;
    }

    public function countryStatePath(string | Closure $statePath, bool $isStatePathAbsolute = false): static
    {
        $this->countryStatePath = $statePath;
        $this->countryStatePathIsAbsolute = $isStatePathAbsolute;

        return $this;
    }

    public function getCountryStatePath(): ?string
    {
        if (! $this->hasCountryStatePath()) {
            return null;
        }

        return $this->resolveRelativeStatePath((string) $this->evaluate($this->countryStatePath), $this->countryStatePathIsAbsolute);
    }

    /**
     * @param string | array<int, string> $country
     * @param int | string | array<int, int | string> | PhoneNumberType | null $type
     */
    public function validateFor(string | array $country = 'INTERNATIONAL', int | string | array | PhoneNumberType | null $type = null, bool $lenient = false): static
    {
        $this->validatedCountry = $country;

        $rule = new PhoneRule();

        if ($country === 'INTERNATIONAL') {
            $rule->international();
        } else {
            $rule->country($country);
        }

        if ($lenient) {
            $rule->lenient();
        }

        if ($type) {
            $rule->type($type);
        }

        return $this->rule($rule);
    }

    public function defaultCountry(string | Closure $value): static
    {
        $this->defaultCountry = $value;

        return $this;
    }

    public function getDefaultCountry(): ?string
    {
        return $this->evaluate($this->defaultCountry);
    }

    public function ipLookup(Closure $callback): static
    {
        $this->ipLookupCallback = $callback;

        return $this;
    }

    public function performIpLookup(): mixed
    {
        return $this->evaluate($this->ipLookupCallback);
    }

    public function disableLookup(): static
    {
        $this->performIpLookup = false;

        return $this;
    }

    public function enableIpLookup(bool | Closure $value = true): static
    {
        $this->performIpLookup = $value;

        return $this;
    }

    public function canPerformIpLookup(): bool
    {
        return $this->evaluate($this->performIpLookup);
    }

    public function inputNumberFormat(PhoneInputNumberType | Closure $format): static
    {
        $this->inputNumberFormat = $format instanceof Closure ? $format : $format->value;
        $this->hasExplicitInputNumberFormat = true;

        return $this;
    }

    public function hasExplicitInputNumberFormat(): bool
    {
        return $this->hasExplicitInputNumberFormat;
    }

    public function getInputNumberFormat(): string
    {
        $value = $this->evaluate($this->inputNumberFormat);

        return $value instanceof PhoneInputNumberType ? $value->value : $value;
    }

    public function displayNumberFormat(PhoneInputNumberType | Closure $format): static
    {
        $this->displayNumberFormat = $format instanceof Closure ? $format : $format->value;

        return $this;
    }

    public function getDisplayNumberFormat(): string
    {
        $value = $this->evaluate($this->displayNumberFormat);

        return $value instanceof PhoneInputNumberType ? $value->value : $value;
    }

    public function focusNumberFormat(PhoneInputNumberType | false | Closure $format): static
    {
        $this->focusNumberFormat = $format instanceof PhoneInputNumberType ? $format->value : $format;

        return $this;
    }

    public function getFocusNumberFormat(): string | false
    {
        $value = $this->evaluate($this->focusNumberFormat);

        return $value instanceof PhoneInputNumberType ? $value->value : $value;
    }

    public function disallowDropdown(): static
    {
        $this->allowDropdown = false;

        return $this;
    }

    public function allowDropdown(bool | Closure $value = true): static
    {
        $this->allowDropdown = $value;

        return $this;
    }

    public function isAllowDropdown(): bool
    {
        return $this->evaluate($this->allowDropdown);
    }

    public function autoPlaceholder(string | Closure $value): static
    {
        $this->autoPlaceholder = $value;

        return $this;
    }

    public function getAutoPlaceholder(): string
    {
        return $this->evaluate($this->autoPlaceholder);
    }

    public function containerClass(string | Closure $value): static
    {
        $this->containerClass = $value;

        return $this;
    }

    public function getContainerClass(): string
    {
        return $this->evaluate($this->containerClass);
    }

    /**
     * @param array<int, string> | Closure | null $value
     */
    public function countryOrder(array | Closure | null $value): static
    {
        $this->countryOrder = $value;

        return $this;
    }

    /**
     * @return array<int, string> | null
     */
    public function getCountryOrder(): ?array
    {
        return $this->evaluate($this->countryOrder);
    }

    public function countrySearch(bool | Closure $value = true): static
    {
        $this->countrySearch = $value;

        return $this;
    }

    public function isCountrySearch(): bool
    {
        return $this->evaluate($this->countrySearch);
    }

    public function customPlaceholder(string | RawJs | Closure | null $value): static
    {
        $this->customPlaceholder = $value;

        return $this;
    }

    public function getCustomPlaceholder(): string | RawJs | null
    {
        return $this->evaluate($this->customPlaceholder);
    }

    public function dropdownContainer(string | null | Closure $value): static
    {
        $this->dropdownContainer = $value;

        return $this;
    }

    public function getDropdownContainer(): ?string
    {
        return $this->evaluate($this->dropdownContainer);
    }

    /**
     * @param array<int, string> | Closure $value
     */
    public function excludeCountries(array | Closure $value): static
    {
        $this->excludeCountries = $value;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getExcludeCountries(): array
    {
        return $this->evaluate($this->excludeCountries);
    }

    public function fixDropdownWidth(bool | Closure $value = true): static
    {
        $this->fixDropdownWidth = $value;

        return $this;
    }

    public function isFixDropdownWidth(): bool
    {
        return $this->evaluate($this->fixDropdownWidth);
    }

    public function formatAsYouType(bool | Closure $value = true): static
    {
        $this->formatAsYouType = $value;

        return $this;
    }

    public function isFormatAsYouType(): bool
    {
        return $this->evaluate($this->formatAsYouType);
    }

    public function formatOnDisplay(bool | Closure $value = true): static
    {
        $this->formatOnDisplay = $value;

        return $this;
    }

    public function isFormatOnDisplay(): bool
    {
        return $this->evaluate($this->formatOnDisplay);
    }

    /**
     * @param array<string, mixed> | Closure $value
     */
    public function i18n(array | Closure $value): static
    {
        $this->i18n = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getI18n(): array
    {
        return $this->evaluate($this->i18n);
    }

    public function initialCountry(string | Closure $value): static
    {
        $this->initialCountry = $value;

        return $this;
    }

    public function getInitialCountry(): string
    {
        return $this->evaluate($this->initialCountry);
    }

    public function nationalMode(bool | Closure $value = true): static
    {
        $this->nationalMode = $value;

        return $this;
    }

    public function isNationalMode(): bool
    {
        return $this->evaluate($this->nationalMode);
    }

    /**
     * @param array<int, string> | Closure $value
     */
    public function onlyCountries(array | Closure $value): static
    {
        $this->onlyCountries = $value;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getOnlyCountries(): array
    {
        return $this->evaluate($this->onlyCountries);
    }

    public function placeholderNumberType(string | Closure $value): static
    {
        $this->placeholderNumberType = $value;

        return $this;
    }

    public function getPlaceholderNumberType(): string
    {
        return $this->evaluate($this->placeholderNumberType);
    }

    public function showFlags(bool | Closure $value = true): static
    {
        $this->showFlags = $value;

        return $this;
    }

    public function isShowFlags(): bool
    {
        return $this->evaluate($this->showFlags);
    }

    public function separateDialCode(bool | Closure $value = true): static
    {
        $this->separateDialCode = $value;

        return $this;
    }

    public function isSeparateDialCode(): bool
    {
        return $this->evaluate($this->separateDialCode);
    }

    public function useFullscreenPopup(bool | Closure $value = true): static
    {
        $this->useFullscreenPopup = $value;

        return $this;
    }

    public function isUseFullscreenPopup(): bool
    {
        return $this->evaluate($this->useFullscreenPopup);
    }

    public function strictMode(bool | Closure $value = true): static
    {
        $this->isStrictMode = $value;

        return $this;
    }

    public function isStrictMode(): bool
    {
        return $this->evaluate($this->isStrictMode);
    }

    public function cookieName(string | Closure $value): static
    {
        $this->cookieName = $value;

        return $this;
    }

    public function getCookieName(): string
    {
        return $this->evaluate($this->cookieName);
    }

    public function locale(string | Closure $value): static
    {
        $this->locale = $value;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->evaluate($this->locale) ?? app()->getLocale();
    }

    /**
     * @param array<string, mixed> | Closure $value
     */
    public function customOptions(array | Closure $value): static
    {
        $this->customOptions = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomOptions(): array
    {
        return $this->evaluate($this->customOptions);
    }

    protected function phoneFormat(mixed $state, mixed $country, int | PhoneNumberFormat $format): mixed
    {
        $country ??= $this->getDefaultCountry() ?? [];

        $phoneNumber = phone(number: $state, country: $country);

        if (is_int($format) && enum_exists(PhoneNumberFormat::class)) {
            $format = PhoneNumberFormat::tryFrom($format);
        }

        if (! $format) {
            return $state;
        }

        if ($phoneNumber->isValid()) {
            return $phoneNumber->format($format);
        }

        $lenientPhoneNumber = $phoneNumber->lenient();

        if ($lenientPhoneNumber->isValid()) {
            return $lenientPhoneNumber->format($format);
        }

        return $state;
    }
}
