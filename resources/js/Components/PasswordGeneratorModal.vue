<script setup>
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    lockScroll: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:open', 'generated']);

const lengthId = useId();
const genLength = ref(16);
const genDigits = ref(true);
const genSymbols = ref(true);

const charset = computed(() => {
    let chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    if (genDigits.value) {
        chars += '0123456789';
    }

    if (genSymbols.value) {
        chars += '!@#$%^&*()-_=+[]{};:,.?';
    }

    return chars;
});

function close() {
    emit('update:open', false);
}

function generatePassword() {
    const length = Math.min(32, Math.max(8, Number(genLength.value) || 16));
    const chars = charset.value;
    const bytes = new Uint8Array(length);
    crypto.getRandomValues(bytes);

    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars[bytes[i] % chars.length];
    }

    emit('generated', result);
    close();
}

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        event.stopImmediatePropagation();
        close();
    }
}

watch(
    () => props.open,
    (open) => {
        if (!props.lockScroll) {
            return;
        }

        document.body.style.overflow = open ? 'hidden' : '';
    },
);

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown, true);
}

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown, true);

    if (props.lockScroll) {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[110] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="generator-title"
        >
            <div
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                @click="close"
            />

            <div class="relative z-10 w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 id="generator-title" class="text-lg font-bold text-slate-800">
                            Генератор пароля
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-400">
                            Настройте параметры и вставьте в поле
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Закрыть"
                        @click="close"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label :for="lengthId" class="text-sm font-semibold text-gray-700">Длина</label>
                            <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-mono text-sm font-bold text-indigo-700 tabular-nums">
                                {{ genLength }}
                            </span>
                        </div>
                        <input
                            :id="lengthId"
                            v-model.number="genLength"
                            type="range"
                            min="8"
                            max="32"
                            step="1"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-indigo-600"
                        >
                        <div class="mt-1.5 flex justify-between text-xs text-slate-400">
                            <span>8</span>
                            <span>32</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                v-model="genDigits"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="font-semibold">Цифры</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                v-model="genSymbols"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="font-semibold">Символы</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                        @click="generatePassword"
                    >
                        Сгенерировать
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                        @click="close"
                    >
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
