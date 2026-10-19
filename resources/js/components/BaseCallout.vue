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
    import { CheckIcon, ExclamationTriangleIcon, InformationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
    import { computed } from 'vue';

    const props = defineProps({
        tone: {
            type: String,
            default: 'neutral',
            validator: (v) => ['success', 'warning', 'danger', 'info', 'neutral'].includes(v),
        },
        icon: {
            type: [Object, Function],
            default: null,
        },
        title: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            default: null,
        },
        role: {
            type: String,
            default: 'note',
            validator: (v) => ['note', 'status', 'alert'].includes(v),
        },
        size: {
            type: String,
            default: 'lg',
            validator: (v) => ['sm', 'lg'].includes(v),
        },
    });

    const TONES = {
        success: {
            icon: CheckIcon,
            surface: 'bg-green-50 ring-green-600/20',
            largeIcon: 'bg-green-500 text-white',
            smallIcon: 'text-green-600',
            title: 'text-green-800',
            text: 'text-green-700',
        },
        warning: {
            icon: ExclamationTriangleIcon,
            surface: 'bg-amber-50 ring-amber-600/20',
            largeIcon: 'bg-amber-400 text-white',
            smallIcon: 'text-amber-600',
            title: 'text-amber-800',
            text: 'text-amber-700',
        },
        danger: {
            icon: XMarkIcon,
            surface: 'bg-red-50 ring-red-600/20',
            largeIcon: 'bg-red-500 text-white',
            smallIcon: 'text-red-600',
            title: 'text-red-800',
            text: 'text-red-700',
        },
        info: {
            icon: InformationCircleIcon,
            surface: 'bg-blue-50 ring-blue-600/20',
            largeIcon: 'bg-blue-500 text-white',
            smallIcon: 'text-blue-600',
            title: 'text-blue-950',
            text: 'text-blue-800',
        },
        neutral: {
            icon: InformationCircleIcon,
            surface: 'bg-white ring-gray-950/5',
            largeIcon: 'bg-gray-100 text-gray-500',
            smallIcon: 'text-gray-400',
            title: 'text-gray-900',
            text: 'text-gray-600',
        },
    };

    const config = computed(() => TONES[props.tone] ?? TONES.neutral);
    const iconComponent = computed(() => props.icon ?? config.value.icon);
    const isLarge = computed(() => props.size === 'lg');
</script>

<template>
    <section
        :role="role"
        :class="[
            'flex flex-col gap-4 rounded-lg shadow-sm ring-1 ring-inset md:flex-row md:justify-between',
            isLarge ? 'px-5 py-4 md:items-center' : 'px-4 py-3',
            config.surface,
        ]"
    >
        <div :class="['flex gap-4', isLarge ? 'items-center' : 'items-start gap-3']">
            <span
                v-if="isLarge"
                :class="['flex size-11 shrink-0 items-center justify-center rounded-full', config.largeIcon]"
                aria-hidden="true"
            >
                <component :is="iconComponent" class="size-6 stroke-2" />
            </span>
            <component
                v-else
                :is="iconComponent"
                :class="['mt-0.5 size-5 shrink-0', config.smallIcon]"
                aria-hidden="true"
            />

            <div class="grid gap-0.5">
                <h2 :class="[isLarge ? 'text-lg' : 'text-sm', 'font-semibold', config.title]">{{ title }}</h2>
                <p v-if="description" :class="['text-sm text-pretty', isLarge ? 'text-gray-600' : config.text]">
                    {{ description }}
                </p>
            </div>
        </div>

        <dl v-if="$slots.meta" class="flex flex-wrap gap-x-6 gap-y-3 md:flex-nowrap">
            <slot name="meta" />
        </dl>
    </section>
</template>
