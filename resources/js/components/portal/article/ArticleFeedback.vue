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
      of the licensor in the software. Any use of the licensor's trademarks is subject
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
    import { HandThumbDownIcon, HandThumbUpIcon } from '@heroicons/vue/24/solid';

    defineProps({
        feedback: {
            type: Boolean,
            default: null,
        },
        helpfulVotePercentage: {
            type: Number,
            default: null,
        },
    });

    const emit = defineEmits(['toggle-feedback']);
</script>

<template>
    <div class="max-w-5xl mx-auto w-full flex flex-wrap items-center gap-4 border border-gray-200 rounded-lg p-4 bg-white">
        <span class="text-sm font-medium text-gray-700">Was this content helpful?</span>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="emit('toggle-feedback', true)"
                class="relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 focus-visible:ring-2"
                :class="
                    feedback === true
                        ? 'bg-brand-600 text-white hover:bg-brand-500 focus-visible:ring-brand-500/50'
                        : 'bg-white text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50'
                "
            >
                <HandThumbUpIcon class="size-5" />
                Yes
            </button>

            <button
                type="button"
                @click="emit('toggle-feedback', false)"
                class="relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 focus-visible:ring-2"
                :class="
                    feedback === false
                        ? 'bg-brand-600 text-white hover:bg-brand-500 focus-visible:ring-brand-500/50'
                        : 'bg-white text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50'
                "
            >
                <HandThumbDownIcon class="size-5" />
                No
            </button>
        </div>

        <span v-if="helpfulVotePercentage" class="text-sm text-gray-500">
            {{ helpfulVotePercentage }}% of visitors found this helpful.
        </span>
    </div>
</template>
