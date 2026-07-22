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

    const props = defineProps({
        context: Object,
    });

    const digits = Number(props.context.digits);
    const tmp = ref(props.context.value || '');

    function handleInput(index, e) {
        const prev = tmp.value;

        if (tmp.value.length <= index) {
            tmp.value = '' + tmp.value + e.target.value;
        } else {
            tmp.value = '' + tmp.value.substr(0, index) + e.target.value + tmp.value.substr(index + 1);
        }

        const inputs = e.target.parentElement.querySelectorAll('input');

        if (index < digits - 1 && tmp.value.length >= prev.length) {
            inputs.item(index + 1).focus();
        } else if (index > 0 && tmp.value.length < prev.length) {
            inputs.item(index - 1).focus();
        }

        if (tmp.value.length === digits) {
            props.context.node.input(tmp.value);
        } else if (tmp.value.length < digits && props.context.value !== '') {
            props.context.node.input('');
        }
    }

    function handleFocus(e) {
        e.target.select();
    }

    function handlePaste(e) {
        const paste = e.clipboardData.getData('text');
        if (typeof paste === 'string') {
            tmp.value = paste.substr(0, digits);
            const inputs = e.target.parentElement.querySelectorAll('input');
            inputs.item(tmp.value.length - 1).focus();
        }
    }
</script>

<template>
    <input
        v-for="index in digits"
        maxlength="1"
        class="ring-1 ring-gray-400 focus-within:ring-brand-500 rounded appearance-none p-2 w-8 mr-1 text-center bg-transparent focus:outline-hidden focus:shadow-none font-sans border-none text-gray-700 placeholder-gray-400"
        :value="tmp[index - 1] || ''"
        @input="handleInput(index - 1, $event)"
        @focus="handleFocus"
        @paste="handlePaste"
    />
</template>