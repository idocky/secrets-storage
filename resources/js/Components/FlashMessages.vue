<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div v-if="success" class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-700" role="status">
        <p class="font-semibold">{{ success }}</p>
    </div>

    <div v-if="error" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">
        <p class="font-semibold">{{ error }}</p>
    </div>
</template>
