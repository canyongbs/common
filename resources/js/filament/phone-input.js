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

import * as locales from 'intl-tel-input/i18n';
import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import '../../css/phone-input.css';

export default function phoneInputFormComponent({
    options,
    locale,
    state,
    countryState = undefined,
    intlTelInputSelectedCountryCookieName,
    key,
    isLive,
    isLiveDebounced,
    isLiveOnBlur,
    liveDebounce,
}) {
    return {
        state,
        countryState,
        key,
        input: null,
        intlTelInput: null,
        intlTelInputSelectedCountryCookieName,
        options: {},

        async init() {
            await new Promise((resolve) => setTimeout(resolve, 150));

            this.initOptions();
            this.applyGeoIpLookup();

            this.input = this.$refs.input;
            this.intlTelInput = intlTelInput(this.input, this.options);

            this.patchHandleAutoCountry();
            this.configureCountryListAccessibility();
            this.setState(this.state);

            this.input.addEventListener('countrychange', () => {
                const countryData = this.intlTelInput.getSelectedCountryData();

                if (!countryData.iso2) {
                    return;
                }

                setCookie(this.intlTelInputSelectedCountryCookieName, countryData.iso2.toUpperCase());
                this.formatState();
                this.$nextTick(() => this.commitLiveState());
            });

            this.input.addEventListener('input', () => {
                this.formatState();
                this.commitLiveState();
            });

            this.input.addEventListener('blur', () => {
                this.formatState();

                if (isLiveOnBlur) {
                    this.commitState();
                }
            });

            this.input.addEventListener('focus', () => {
                const format = this.options.focusNumberFormat || false;

                if (format !== false) {
                    this.input.value = this.intlTelInput.getNumber(intlTelInput.utils.numberFormat[format]);
                }
            });

            this.$watch('state', (value) => this.setState(value));

            document.addEventListener('phoneInput:isDisabled', (event) => {
                if (event.detail.key === this.key) {
                    this.applyDisabledState(event.detail.isDisabled);
                }
            });
        },

        destroy() {
            this.intlTelInput?.destroy();
        },

        configureCountryListAccessibility() {
            const countryList = this.input.closest('.iti')?.querySelector('.iti__country-list[role="listbox"]');

            if (!countryList) {
                return;
            }

            countryList.tabIndex = this.input.disabled ? -1 : 0;
        },

        applyDisabledState(isDisabled) {
            this.input.disabled = isDisabled;

            const itiContainer = this.input.closest('.iti');
            const countryButton = itiContainer?.querySelector('button.iti__selected-country');

            if (countryButton) {
                countryButton.disabled = isDisabled;
                countryButton.tabIndex = isDisabled ? -1 : 0;
            }

            this.configureCountryListAccessibility();
        },

        patchHandleAutoCountry() {
            const iti = this.intlTelInput;

            if (iti.ui?.selectedCountryInner) {
                return;
            }

            iti.handleAutoCountry = () => {
                if (this.options.initialCountry === 'auto' && intlTelInput.autoCountry) {
                    iti.defaultCountry = intlTelInput.autoCountry;

                    if (!iti.getSelectedCountryData().iso2) {
                        iti.setCountry(iti.defaultCountry);
                    }

                    iti.resolveAutoCountryPromise();
                }
            };
        },

        initOptions() {
            this.options = {
                ...options,
                i18n: {
                    ...(locales[locale] ?? {}),
                    ...options.i18n,
                },
            };
        },

        async commitLiveState() {
            if (!isLiveOnBlur && (isLive || isLiveDebounced)) {
                await this.$nextTick();

                const value = this.intlTelInput.getNumber(intlTelInput.utils.numberFormat.E164);
                const selectedCountry = this.intlTelInput.getSelectedCountryData().iso2?.toUpperCase();

                if (this.state !== value || (this.countryState && this.countryState !== selectedCountry)) {
                    return;
                }

                if (isLiveDebounced) {
                    Alpine.debounce(() => this.commitState(), liveDebounce)();
                } else if (isLive) {
                    this.commitState();
                }
            }
        },

        setState(value) {
            if (value) {
                this.intlTelInput.setNumber(value.valueOf());
                this.$nextTick(() => this.formatState());
                this.updateCountryState();
            } else if (value === null) {
                this.input.value = '';
                this.state = null;
            }
        },

        formatState() {
            const displayNumberFormat = this.options.displayNumberFormat || 'E164';
            const inputNumberFormat = this.options.inputNumberFormat || 'E164';

            this.state = this.intlTelInput.getNumber(intlTelInput.utils.numberFormat[inputNumberFormat]) || null;

            if (this.options.formatAsYouType !== true) {
                this.input.value =
                    this.intlTelInput.getNumber(intlTelInput.utils.numberFormat[displayNumberFormat]) || null;
            }
        },

        updateCountryState(value = undefined) {
            if (countryState === undefined) {
                return;
            }

            if (value !== undefined) {
                this.countryState = value;

                return;
            }

            this.countryState = this.intlTelInput.getSelectedCountryData().iso2?.toUpperCase();
            window.duskCountryValue = this.countryState;
        },

        commitState() {
            if (JSON.stringify(this.$wire.__instance.canonical) !== JSON.stringify(this.$wire.__instance.ephemeral)) {
                this.$wire.$commit();
            }
        },

        applyGeoIpLookup() {
            if (!this.options.performIpLookup) {
                return;
            }

            this.options.geoIpLookup = async function (success, failure) {
                const country = getCookie(this.intlTelInputSelectedCountryCookieName);

                if (country) {
                    success(country);

                    return;
                }

                try {
                    const { country } = await this.$wire.callSchemaComponentMethod(this.key, 'handleLookup');

                    window.phoneInputGeoIpLookup = true;
                    this.$nextTick(() => {
                        success(country);
                        setCookie(this.intlTelInputSelectedCountryCookieName, country);
                    });
                } catch (error) {
                    failure(error);
                }
            }.bind(this);
        },
    };
}

function setCookie(cookieName, cookieValue, expiryDays = null, path = null, domain = null) {
    let cookie = `${cookieName}=${cookieValue};`;

    if (expiryDays) {
        const date = new Date();
        date.setTime(date.getTime() + expiryDays * 24 * 60 * 60 * 1000);
        cookie += `expires=${date.toUTCString()};`;
    }

    if (path) {
        cookie += `path=${path};`;
    }

    if (domain) {
        cookie += `domain=${domain};`;
    }

    document.cookie = cookie;
}

function getCookie(cookieName) {
    const name = `${cookieName}=`;

    for (const cookie of document.cookie.split(';')) {
        const trimmedCookie = cookie.trim();

        if (trimmedCookie.startsWith(name)) {
            return trimmedCookie.slice(name.length);
        }
    }

    return '';
}
