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
    import Footer from '../Footer.vue';

    defineProps({
        headerLogo: {
            type: String,
            required: true,
        },
        appName: {
            type: String,
            required: true,
        },
        footerLogo: {
            type: String,
            required: true,
        },
    });
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-gray-50 text-gray-950 antialiased">
        <div
            class="sticky top-0 z-10 flex justify-center items-center w-full border-b border-gray-200 flex-shrink-0 p-4 bg-gray-50"
        >
            <img :src="headerLogo" class="h-9 block" :alt="appName" />
        </div>

        <main class="mx-auto flex flex-1 justify-center items-center w-full px-4 md:px-6 lg:px-8 max-w-screen-lg py-8">
            <div class="w-full max-w-md">
                <slot />
            </div>
        </main>

        <Footer :logo="footerLogo" :app-name="appName" />
    </div>
</template>
