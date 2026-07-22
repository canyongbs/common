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
    import { ref } from 'vue';
    import BaseButton from '../../BaseButton.vue';
    import Footer from '../Footer.vue';
    import Heading from '../Heading.vue';

    const authentication = defineModel('authentication', {
        type: Object,
        required: true,
    });

    defineProps({
        title: {
            type: String,
            required: true,
        },
        headerLogo: {
            type: String,
            required: true,
        },
        footerLogo: {
            type: String,
            required: true,
        },
        appName: {
            type: String,
            required: true,
        },
        requiresAuthentication: {
            type: Boolean,
            required: true,
        },
        formKit: {
            type: [Object, Function],
            required: true,
        },
        formKitSchema: {
            type: [Object, Function],
            default: null,
        },
        registrationSchema: {
            type: Array,
            default: () => [],
        },
    });

    const emit = defineEmits(['authenticate', 'cancel']);

    const submitting = ref(false);

    function handleSubmit(formData, node) {
        submitting.value = true;
        emit('authenticate', formData, node, () => {
            submitting.value = false;
        });
    }
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
                <Heading :title="title" class="text-center" />

                <component :is="formKit" type="form" @submit="handleSubmit" v-model="authentication" :actions="false">
                    <div class="mt-8 flex flex-col gap-6">
                        <div class="-mb-4">
                            <component
                                :is="formKit"
                                type="email"
                                label="Email address"
                                name="email"
                                validation="required|email"
                                validation-visibility="submit"
                                :disabled="authentication.isRequested || authentication.registrationAllowed"
                            />
                        </div>

                        <div
                            v-if="authentication.registrationAllowed && registrationSchema.length"
                            class="flex flex-col gap-6"
                        >
                            <p class="text-sm text-gray-500">
                                You are not registered yet. Please fill in the form below to register.
                            </p>

                            <component :is="formKitSchema" :schema="registrationSchema" />
                        </div>

                        <p v-if="authentication.requestedMessage" class="text-sm text-gray-500">
                            {{ authentication.requestedMessage }}
                        </p>

                        <div v-if="authentication.isRequested" class="-mb-4">
                            <component
                                :is="formKit"
                                type="otp"
                                digits="6"
                                label="Enter the code here"
                                name="code"
                                validation="required"
                                validation-visibility="submit"
                            />
                        </div>

                        <div class="flex flex-col gap-3">
                            <BaseButton type="submit" color="primary" size="lg" class="w-full" :loading="submitting">
                                {{ authentication.isRequested ? 'Sign in' : 'Send login code' }}
                            </BaseButton>

                            <button
                                v-if="!requiresAuthentication"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 text-sm font-medium text-gray-700 outline-none hover:underline focus-visible:underline"
                                @click="emit('cancel')"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </component>
            </div>
        </main>

        <Footer :logo="footerLogo" :app-name="appName" />
    </div>
</template>
