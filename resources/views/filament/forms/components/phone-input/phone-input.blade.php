{{--
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
--}}

@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Enums\VerticalAlignment;
    use Illuminate\Support\Js;

    $hasInlineLabel = $hasInlineLabel();
    $id = $getId();
    $isConcealed = $isConcealed();
    $isDisabled = $isDisabled();
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixLabel = $getSuffixLabel();
    $statePath = $getStatePath();
    $key = $getKey();
    $isLive = $isLive();
    $isLiveOnBlur = $isLiveOnBlur();
    $isLiveDebounced = $isLiveDebounced();
    $liveDebounce = $getLiveDebounce();
    $cssUrl = FilamentAsset::getStyleHref("phone-input", package: "common");
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :inline-label-vertical-alignment="VerticalAlignment::Center"
>
    <x-slot name="label" @class([
        "sm:pt-1.5" => $hasInlineLabel,
    ])>
        {{ $getLabel() }}
    </x-slot>

    <x-filament::input.wrapper
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class(['fi-fo-phone-input'])"
        :disabled="$isDisabled"
        :inline-prefix="$isPrefixInline"
        :inline-suffix="$isSuffixInline"
        :prefix="$prefixLabel"
        :prefix-actions="$prefixActions"
        :prefix-icon="$prefixIcon"
        :prefix-icon-color="$getPrefixIconColor()"
        :suffix="$suffixLabel"
        :suffix-actions="$suffixActions"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$getSuffixIconColor()"
        :valid="! $errors->has($statePath)"
        x-data="{
            isDisabled: {{ $isDisabled ? 'true' : 'false' }},
            init() {
                $nextTick(() => $dispatch('phoneInput:isDisabled', { key: '{{ $key }}', isDisabled: this.isDisabled }));
            },
        }"
        x-load-css="[{{ Js::from($cssUrl) }}]"
    >
        <div class="inline-flex w-full" dusk="phone-input.{{ $id }}" wire:ignore>
            <div
                class="w-full"
                x-data="phoneInputFormComponent({
                    options: {
                        allowDropdown: @js($isAllowDropdown()),
                        autoPlaceholder: @js($getAutoPlaceholder()),
                        containerClass: @js($getContainerClass()),
                        countryOrder: @js($getCountryOrder()),
                        countrySearch: @js($isCountrySearch()),
                        customPlaceholder: @js($getCustomPlaceholder()),
                        dropdownContainer: @js($getDropdownContainer()),
                        excludeCountries: @js($getExcludeCountries()),
                        fixDropdownWidth: @js($isFixDropdownWidth()),
                        formatAsYouType: @js($isFormatAsYouType()),
                        formatOnDisplay: @js($isFormatOnDisplay()),
                        i18n: @js($getI18n()),
                        initialCountry: @js($getInitialCountry()),
                        nationalMode: @js($isNationalMode()),
                        onlyCountries: @js($getOnlyCountries()),
                        performIpLookup: @js($canPerformIpLookup()),
                        placeholderNumberType: @js($getPlaceholderNumberType()),
                        separateDialCode: @js($isSeparateDialCode()),
                        showFlags: @js($isShowFlags()),
                        strictMode: @js($isStrictMode()),
                        useFullscreenPopup: @js($isUseFullscreenPopup()),
                        displayNumberFormat: @js($getDisplayNumberFormat()),
                        focusNumberFormat: @js($getFocusNumberFormat()),
                        inputNumberFormat: @js($getInputNumberFormat()),
                        ...@js($getCustomOptions()),
                    },
                    locale: @js($getLocale()),
                    intlTelInputSelectedCountryCookieName: @js($getCookieName()),
                    state: $wire.$entangle('{{ $statePath }}'),
                    key: @js($key),
                    isLive: @js($isLive),
                    isLiveDebounced: @js($isLiveDebounced),
                    isLiveOnBlur: @js($isLiveOnBlur),
                    liveDebounce: @js($liveDebounce),
                    @if ($hasCountryStatePath() && ($countryStatePath = $getCountryStatePath()))
                        countryState: $wire.$entangle('{{ $countryStatePath }}'),
                    @endif
                })"
                x-load
                x-load-src="{{ FilamentAsset::getAlpineComponentSrc("phone-input", package: "common") }}"
            >
                <x-filament::input
                    x-ref="input"
                    :attributes="
                        \Filament\Support\prepare_inherited_attributes($getExtraInputAttributeBag())
                            ->merge([
                                'autofocus' => $isAutofocused(),
                                'disabled' => $isDisabled,
                                'id' => $id,
                                'inlinePrefix' => $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel)),
                                'inlineSuffix' => $isSuffixInline && (count($suffixActions) || $suffixIcon || filled($suffixLabel)),
                                'placeholder' => $getPlaceholder(),
                                'required' => $isRequired() && (! $isConcealed),
                                'type' => 'tel',
                            ], escape: false)
                    "
                />
            </div>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
