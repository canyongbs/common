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
    import DOMPurify from 'dompurify';

    defineProps({
        content: {
            type: String,
            required: true,
        },
    });
</script>

<template>
    <div
        class="prose max-w-5xl w-full mx-auto prose-p:leading-snug! prose-p:my-2.5! prose-headings:my-4! prose-hr:my-5! prose-ul:my-3! prose-ol:my-3! prose-li:my-0! [&_li>p]:my-1! [&_td_p]:my-3! [&_th_p]:my-3! prose-table:w-full! prose-table:my-6 prose-table:border-separate prose-table:border-spacing-0 prose-table:rounded-lg prose-table:border prose-table:border-gray-200 prose-table:overflow-hidden prose-table:shadow-xs prose-td:border-b prose-td:border-gray-100 prose-td:align-middle prose-td:px-6 prose-td:py-2 prose-td:text-left prose-td:text-gray-700 prose-th:border-none prose-th:bg-brand-600 prose-th:px-6 prose-th:py-2 prose-th:text-left prose-th:font-bold prose-th:text-white [&_tr:last-child_td]:border-b-[3px]! [&_tr:last-child_td]:border-brand-600! even:prose-tr:bg-gray-50"
        v-html="
            DOMPurify.sanitize(content, {
                ADD_TAGS: ['iframe', 'video', 'source'],
                ADD_ATTR: [
                    'allow',
                    'allowfullscreen',
                    'frameborder',
                    'controls',
                    'target',
                    'rel',
                    'data-video-embed',
                    'data-video-type',
                    'data-video-src',
                    'data-video-width',
                    'data-video-height',
                    'data-cols',
                    'data-col-span',
                    'data-from-breakpoint',
                    'data-color',
                    'data-id',
                ],
            })
        "
    ></div>
</template>
