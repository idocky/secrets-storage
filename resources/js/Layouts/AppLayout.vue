<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessages from '../Components/FlashMessages.vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    maxWidth: {
        type: String,
        default: 'max-w-4xl',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);

const currentUrl = computed(() => {
    const url = page.url || '';
    return url.split('?')[0] || '/';
});

const profileActive = computed(() => currentUrl.value === '/profile' || currentUrl.value.startsWith('/profile/'));
const displayName = computed(() => user.value?.name || user.value?.email || '?');
const avatarInitial = computed(() => displayName.value.charAt(0).toUpperCase());

const navItems = computed(() => {
    const items = [
        {
            href: '/',
            label: 'Файлы',
            match: (url) => url === '/' || url.startsWith('/upload') || url.startsWith('/files/'),
            icon: 'files',
        },
        {
            href: '/secrets',
            label: 'Секреты',
            match: (url) => url === '/secrets' || url.startsWith('/secrets/'),
            icon: 'secrets',
        },
    ];

    if (user.value?.is_admin) {
        items.push({
            href: '/users',
            label: 'Пользователи',
            match: (url) => url === '/users' || url.startsWith('/users/'),
            icon: 'users',
        });
    }

    return items;
});

function isActive(item) {
    return item.match(currentUrl.value);
}

function logout() {
    router.post('/logout');
}

function closeSidebar() {
    sidebarOpen.value = false;
}

watch(
    () => page.url,
    () => {
        sidebarOpen.value = false;
    },
);
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-slate-100 font-sans text-gray-800">
        <!-- Mobile top bar -->
        <div class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
            <button
                type="button"
                class="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100"
                aria-label="Открыть меню"
                @click="sidebarOpen = true"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-bold text-slate-800">{{ title }}</div>
                <div v-if="subtitle" class="truncate text-xs text-slate-400">{{ subtitle }}</div>
            </div>
            <div class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"
            @click="closeSidebar"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="truncate text-base font-bold text-slate-800">Uploader</div>
                    <div class="truncate text-xs text-slate-400">TUS · до 5 ГБ</div>
                </div>
                <button
                    type="button"
                    class="ml-auto rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 lg:hidden"
                    aria-label="Закрыть меню"
                    @click="closeSidebar"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Основная навигация">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                    :class="isActive(item)
                        ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/25'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                >
                    <!-- Files icon -->
                    <svg v-if="item.icon === 'files'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <!-- Secrets icon -->
                    <svg v-else-if="item.icon === 'secrets'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    <!-- Users icon -->
                    <svg v-else-if="item.icon === 'users'" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="border-t border-slate-100 p-3">
                <Link
                    href="/profile"
                    class="mb-2 flex items-center gap-3 rounded-xl px-3 py-3 transition"
                    :class="profileActive
                        ? 'bg-indigo-50 ring-1 ring-indigo-100'
                        : 'bg-slate-50 hover:bg-slate-100'"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                        :class="profileActive ? 'bg-indigo-200 text-indigo-700' : 'bg-indigo-100 text-indigo-600'"
                    >
                        {{ avatarInitial }}
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800" :title="displayName">
                            {{ displayName }}
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ user?.is_admin ? 'Администратор' : 'Пользователь' }}
                        </div>
                    </div>
                </Link>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    @click="logout"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Выйти
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-72">
            <main class="min-h-screen p-4 sm:p-6 lg:p-8">
                <div :class="['mx-auto', maxWidth]">
                    <header class="mb-6 hidden items-start justify-between gap-4 lg:flex">
                        <div class="min-w-0">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                                {{ title }}
                            </h1>
                            <p v-if="subtitle" class="mt-1 text-sm text-slate-500">
                                {{ subtitle }}
                            </p>
                            <slot name="header" />
                        </div>
                        <div class="shrink-0">
                            <slot name="actions" />
                        </div>
                    </header>

                    <FlashMessages />

                    <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-8">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
