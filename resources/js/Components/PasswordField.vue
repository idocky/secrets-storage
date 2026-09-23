<script setup>
import { ref } from 'vue';
import PasswordGeneratorModal from './PasswordGeneratorModal.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: 'password',
    },
    label: {
        type: String,
        default: 'Пароль',
    },
    placeholder: {
        type: String,
        default: 'минимум 8 символов',
    },
    required: {
        type: Boolean,
        default: true,
    },
    error: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'new-password',
    },
    lockScroll: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const visible = ref(false);
const generatorOpen = ref(false);

function onGenerated(value) {
    emit('update:modelValue', value);
    visible.value = true;
}
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-semibold text-gray-700">
            {{ label }}
        </label>
        <div class="flex items-stretch gap-2">
            <div class="relative min-w-0 flex-1">
                <input
                    :id="id"
                    :value="modelValue"
                    :type="visible ? 'text' : 'password'"
                    :required="required"
                    :autocomplete="autocomplete"
                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 pr-12 font-mono text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                    :placeholder="placeholder"
                    @input="emit('update:modelValue', $event.target.value)"
                >
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-slate-600"
                    :aria-label="visible ? 'Скрыть' : 'Показать'"
                    @click="visible = !visible"
                >
                    <svg v-if="!visible" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>

            <button
                type="button"
                class="inline-flex h-[3.25rem] w-[3.25rem] shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 transition hover:bg-indigo-100 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                title="Генератор пароля"
                aria-label="Открыть генератор пароля"
                @click="generatorOpen = true"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </button>
        </div>
        <p v-if="error" class="mt-1.5 text-sm font-semibold text-red-600">
            {{ error }}
        </p>

        <PasswordGeneratorModal
            v-model:open="generatorOpen"
            :lock-scroll="lockScroll"
            @generated="onGenerated"
        />
    </div>
</template>
