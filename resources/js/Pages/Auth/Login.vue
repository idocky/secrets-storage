<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Вход" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-500 to-purple-700 flex items-center justify-center p-5 font-sans">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 sm:p-12">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-1">Вход</h1>
                <p class="text-sm text-gray-400">Войдите, чтобы управлять файлами</p>
            </div>

            <div v-if="form.errors.login" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">
                <p class="font-semibold">{{ form.errors.login }}</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="login" class="mb-1.5 block text-sm font-semibold text-gray-700">Логин</label>
                    <input
                        id="login"
                        v-model="form.login"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-400/30"
                        placeholder="admin"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-gray-700">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-400/30"
                        placeholder="••••••••"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    Запомнить меня
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                >
                    {{ form.processing ? 'Вход…' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>
