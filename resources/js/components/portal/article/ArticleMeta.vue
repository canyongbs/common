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
    import { ClockIcon, EyeIcon } from '@heroicons/vue/20/solid';

    defineProps({
        viewCount: {
            type: Number,
            required: true,
        },
        lastUpdated: {
            type: String,
            required: true,
        },
    });
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center gap-y-1 gap-x-4">
        <div class="flex items-center gap-x-1.5">
            <EyeIcon class="size-4 shrink-0" aria-hidden="true" />
            <span>{{ viewCount }} Views</span>
        </div>
        <div class="flex items-center gap-x-1.5">
            <ClockIcon class="size-4 shrink-0" aria-hidden="true" />
            <span>Last updated: {{ lastUpdated }}</span>
        </div>
    </div>
</template>
