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
    defineProps({
        heading: {
            type: String,
            default: null,
        },
        description: {
            type: String,
            default: null,
        },
        icon: {
            type: [Object, Function],
            default: null,
        },
    });
</script>

<template>
    <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5">
        <header
            v-if="heading || $slots.actions"
            class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <component v-if="icon" :is="icon" class="mt-0.5 size-6 shrink-0 text-gray-400" aria-hidden="true" />
                <div class="grid gap-1">
                    <h2 v-if="heading" class="text-base font-semibold leading-6 text-gray-950">{{ heading }}</h2>
                    <p v-if="description" class="text-sm text-gray-500">{{ description }}</p>
                </div>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-3">
                <slot name="actions" />
            </div>
        </header>

        <div :class="['p-6', (heading || $slots.actions) && 'border-t border-gray-200']">
            <slot />
        </div>
    </section>
</template>
