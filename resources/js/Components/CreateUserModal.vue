<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PasswordField from './PasswordField.vue';
import TagInput from './TagInput.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:open']);

const loginInput = ref(null);
const form = useForm({
    login: '',
    password: '',
    is_admin: false,
    tags: [],
});

function close() {
    if (form.processing) {
        return;
    }

    emit('update:open', false);
}

function submit() {
    form.post('/users', {
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
            form.clearErrors();
            await nextTick();
            loginInput.value?.focus();
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
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-user-title"
        >
            <div
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                @click="close"
            />

            <div class="relative z-10 w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 id="create-user-title" class="text-lg font-bold text-slate-800">
                            Новый пользователь
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-400">
                            Логин и пароль для входа в систему
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
                    <div>
                        <label for="user-login" class="mb-1.5 block text-sm font-semibold text-gray-700">
                            Логин
                        </label>
                        <input
                            id="user-login"
                            ref="loginInput"
                            v-model="form.login"
                            type="text"
                            required
                            maxlength="255"
                            autocomplete="off"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                            placeholder="user1"
                        >
                        <p v-if="form.errors.login" class="mt-1.5 text-sm font-semibold text-red-600">
                            {{ form.errors.login }}
                        </p>
                    </div>

                    <PasswordField
                        id="user-password"
                        v-model="form.password"
                        :error="form.errors.password"
                        :lock-scroll="false"
                    />

                    <TagInput
                        id="user-tags"
                        v-model="form.tags"
                        :suggestions="availableTags"
                        :error="form.errors.tags || form.errors['tags.0']"
                    />

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.is_admin"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <span class="font-semibold">Администратор</span>
                        <span class="text-gray-400">— может управлять пользователями</span>
                    </label>

                    <div class="flex flex-wrap gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                        >
                            {{ form.processing ? 'Создание…' : 'Создать пользователя' }}
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
