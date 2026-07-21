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
    /**
     * `@formkit/vue` only exists in each portal's own node_modules, not the
     * root project shared by `common`, so `FormKit` is injected via the
     * `formKit` prop here instead of being imported directly.
     */
    import { ref } from 'vue';
    import LoginSubmitActions from './LoginSubmitActions.vue';

    const authentication = defineModel('authentication', {
        type: Object,
        required: true,
    });

    defineProps({
        formKit: {
            type: [Object, Function],
            required: true,
        },
        requiresAuthentication: {
            type: Boolean,
            required: true,
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

            <slot name="registration" />

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

            <LoginSubmitActions
                :is-requested="authentication.isRequested"
                :requires-authentication="requiresAuthentication"
                :submitting="submitting"
                @cancel="emit('cancel')"
            />
        </div>
    </component>
</template>
