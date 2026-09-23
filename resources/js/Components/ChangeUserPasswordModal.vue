<script setup>
import { nextTick, onBeforeUnmount, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PasswordField from './PasswordField.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    action: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:open']);

const form = useForm({
    password: '',
});

function close() {
    if (form.processing) {
        return;
    }

    emit('update:open', false);
}

function submit() {
    if (!props.user) {
        return;
    }

    form.patch(props.action || `/users/${props.user.id}/password`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
}

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        close();
    }
}

watch(
    () => props.open,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            form.reset();
            form.clearErrors();
            await nextTick();
            document.getElementById('change-user-password')?.focus();
        }
    },
);

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown);
}

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && user"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="change-password-title"
        >
            <div
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                @click="close"
            />

            <div class="relative z-10 w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 id="change-password-title" class="text-lg font-bold text-slate-800">
                            Изменить пароль
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-400">
                            {{ description || `Пользователь «${user.email}»` }}
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

                <form class="space-y-4" @submit.prevent="submit">
                    <PasswordField
                        id="change-user-password"
                        v-model="form.password"
                        :error="form.errors.password"
                        :lock-scroll="false"
                    />

                    <div class="flex flex-wrap gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                        >
                            {{ form.processing ? 'Сохранение…' : 'Сохранить пароль' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                            @click="close"
                        >
                            Отмена
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
