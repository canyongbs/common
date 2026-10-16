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
    import { ChevronDownIcon } from '@heroicons/vue/16/solid';

    defineProps({
        modelValue: {
            type: String,
            default: null,
        },
        options: {
            type: Array,
            required: true,
        },
        label: {
            type: String,
            required: true,
        },
    });

    defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative inline-block w-full">
        <select
            :value="modelValue"
            :aria-label="label"
            class="w-full appearance-none rounded-md border-0 bg-white py-2 pl-3 pr-9 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand-600"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <ChevronDownIcon
            class="pointer-events-none absolute right-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400"
            aria-hidden="true"
        />
    </div>
</template>
