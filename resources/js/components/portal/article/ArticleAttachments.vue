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
    import { PaperClipIcon } from '@heroicons/vue/20/solid';

    defineProps({
        attachments: {
            type: Array,
            required: true,
        },
    });
</script>

<template>
    <div class="max-w-5xl w-full mx-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Article Resources</h3>
        <div class="divide-y divide-gray-200">
            <div v-for="(attachment, index) in attachments" :key="index" class="flex items-center gap-x-3 py-3">
                <PaperClipIcon class="size-5 shrink-0 text-gray-400" aria-hidden="true" />
                <a
                    :href="attachment.url"
                    class="text-sm font-medium text-brand-600 hover:text-brand-500 underline"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ attachment.name }}
                </a>
            </div>
        </div>
    </div>
</template>
