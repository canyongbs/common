<!--
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
-->
<script setup>
    import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
    import { computed, onUnmounted, ref, useAttrs, watch } from 'vue';

    defineOptions({ inheritAttrs: false });

    const props = defineProps({
        modelValue: {
            type: String,
            default: '',
        },
        label: {
            type: String,
            required: true,
        },
        placeholder: {
            type: String,
            default: '',
        },
        debounce: {
            type: Number,
            default: 0,
        },
    });

    const emit = defineEmits(['update:modelValue']);

    const attrs = useAttrs();

    // Layout (`class`, `style`) stays on the wrapper; every other attribute belongs to the input.
    const wrapperAttrs = computed(() => ({ class: attrs.class, style: attrs.style }));
    const inputAttrs = computed(() => {
        const { class: _class, style: _style, ...rest } = attrs;

        return rest;
    });

    const value = ref(props.modelValue);

    let timeout = null;

    // Keep the field in step when the parent resets the value, such as when filters are cleared.
    watch(
        () => props.modelValue,
        (modelValue) => {
            if (modelValue !== value.value) {
                clearTimeout(timeout);
                value.value = modelValue;
            }
        },
    );

    function update(event) {
        value.value = event.target.value;

        clearTimeout(timeout);

        if (props.debounce <= 0) {
            emit('update:modelValue', value.value);

            return;
        }

        timeout = setTimeout(() => emit('update:modelValue', value.value), props.debounce);
    }

    onUnmounted(() => clearTimeout(timeout));
</script>

<template>
    <div class="relative w-full" v-bind="wrapperAttrs">
        <MagnifyingGlassIcon
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400"
            aria-hidden="true"
        />
        <input
            type="search"
            v-bind="inputAttrs"
            :value="value"
            :aria-label="label"
            :placeholder="placeholder"
            class="w-full rounded-md border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-950/10 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600"
            @input="update"
        />
    </div>
</template>
