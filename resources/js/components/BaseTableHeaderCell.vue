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
    import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon } from '@heroicons/vue/16/solid';
    import { computed } from 'vue';

    const props = defineProps({
        scope: {
            type: String,
            default: 'col',
        },
        sortable: {
            type: Boolean,
            default: false,
        },
        sortDirection: {
            type: String,
            default: null,
            validator: (v) => v === null || ['asc', 'desc'].includes(v),
        },
    });

    const emit = defineEmits(['sort']);

    const ariaSort = computed(() => {
        if (!props.sortable) {
            return undefined;
        }

        if (props.sortDirection === 'asc') {
            return 'ascending';
        }

        return props.sortDirection === 'desc' ? 'descending' : 'none';
    });
</script>

<template>
    <th
        :scope="scope"
        :aria-sort="ariaSort"
        class="px-4 py-3 text-xs font-semibold text-gray-500 sm:first:ps-6 sm:last:pe-6"
    >
        <button
            v-if="sortable"
            type="button"
            class="group inline-flex items-center gap-1 whitespace-nowrap rounded-sm text-left hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            @click="emit('sort')"
        >
            <slot />
            <ChevronUpIcon v-if="sortDirection === 'asc'" class="size-4 text-gray-700" aria-hidden="true" />
            <ChevronDownIcon v-else-if="sortDirection === 'desc'" class="size-4 text-gray-700" aria-hidden="true" />
            <ChevronUpDownIcon
                v-else
                class="size-4 text-gray-300 transition-colors group-hover:text-gray-500"
                aria-hidden="true"
            />
        </button>

        <slot v-else />
    </th>
</template>
