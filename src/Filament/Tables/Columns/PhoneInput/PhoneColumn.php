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

namespace CanyonGBS\Common\Filament\Tables\Columns\PhoneInput;

use CanyonGBS\Common\Filament\Forms\Components\PhoneInput\PhoneInputNumberType;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;

class PhoneColumn extends TextColumn
{
    protected string | Closure | null $countryColumn = null;

    /** @var string | array<int, string> | Closure | null */
    protected string | array | Closure | null $defaultCountry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->displayFormat(PhoneInputNumberType::NATIONAL);
    }

    /**
     * @param string | array<int, string> | Closure $country
     */
    public function defaultCountry(string | array | Closure $country): static
    {
        $this->defaultCountry = $country;

        return $this;
    }

    /**
     * @return string | array<int, string> | null
     */
    public function getDefaultCountry(): string | array | null
    {
        return $this->evaluate($this->defaultCountry);
    }

    public function countryColumn(string | Closure $column): static
    {
        $this->countryColumn = $column;

        return $this;
    }

    public function getCountryColumn(): ?string
    {
        return $this->evaluate($this->countryColumn);
    }

    public function displayFormat(PhoneInputNumberType $format): static
    {
        return $this->formatStateUsing(function (PhoneColumn $column, mixed $state) use ($format): mixed {
            try {
                $country = $this->getDefaultCountry() ?? [];

                if ($this->getCountryColumn()) {
                    $country = $column->getCountryState();
                }

                $phoneNumberFormat = $format->toLibPhoneNumberFormat();
                $formatted = phone(number: $state, country: $country, format: $phoneNumberFormat);

                if ($phoneNumberFormat === PhoneNumberFormat::RFC3966) {
                    $national = phone(
                        number: $state,
                        country: $country,
                        format: PhoneNumberFormat::NATIONAL,
                    );

                    return new HtmlString(sprintf('<a href="%s" dir="ltr">%s</a>', e((string) $formatted), e((string) $national)));
                }

                return new HtmlString(sprintf('<span dir="ltr">%s</span>', e((string) $formatted)));
            } catch (NumberParseException) {
                return $state;
            }
        })->when($format === PhoneInputNumberType::RFC3966, fn (PhoneColumn $column): PhoneColumn => $column->disabledClick());
    }

    /**
     * @return array<int, mixed> | mixed | null
     */
    public function getCountryState(): mixed
    {
        $record = $this->getRecord();
        $column = $this->getCountryColumn();

        if ((! $record) || (! $column)) {
            return null;
        }

        $state = data_get($record, $column);

        if ($state !== null) {
            return $state;
        }

        if (! $this->hasRelationship($record)) {
            return null;
        }

        $relationship = $this->getRelationship($record);

        if (! $relationship) {
            return null;
        }

        $relationshipAttribute = str($column)->afterLast('.')->value();

        $states = collect($this->getRelationshipResults($record))
            ->filter(fn (Model $relatedRecord): bool => array_key_exists($relationshipAttribute, $relatedRecord->attributesToArray()))
            ->pluck($relationshipAttribute)
            ->filter(fn (mixed $state): bool => filled($state))
            ->when($this->isDistinctList(), fn (Collection $states): Collection => $states->unique())
            ->values();

        return $states->isEmpty() ? null : $states->all();
    }
}
