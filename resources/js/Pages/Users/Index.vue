<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import CreateUserModal from '../../Components/CreateUserModal.vue';
import EditUserModal from '../../Components/EditUserModal.vue';
import ChangeUserPasswordModal from '../../Components/ChangeUserPasswordModal.vue';

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const editUser = ref(null);
const passwordModalOpen = ref(false);
const passwordUser = ref(null);
const isAdmin = computed(() => Boolean(user.value?.is_admin));

const subtitle = computed(() => `Всего: ${props.users.length}`);

function openEditModal(item) {
    if (!isAdmin.value) {
        return;
    }

    editUser.value = item;
    editModalOpen.value = true;
}

function openPasswordModal(item) {
    if (!isAdmin.value) {
        return;
    }

    passwordUser.value = item;
    passwordModalOpen.value = true;
}

function destroyUser(item) {
    if (!isAdmin.value || item.id === user.value?.id) {
        return;
    }

    const label = item.name && item.name !== item.email
        ? `${item.name} (${item.email})`
        : (item.name || item.email);

    if (!confirm(`Удалить пользователя «${label}»?`)) {
        return;
    }

    router.delete(`/users/${item.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="Пользователи" :subtitle="subtitle" max-width="max-w-3xl">
        <template #actions>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                @click="createModalOpen = true"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="hidden sm:inline">Новый пользователь</span>
                <span class="sm:hidden">Создать</span>
            </button>
        </template>

        <div
            v-if="!users.length"
            class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-12 text-center"
        >
            <div class="text-lg font-semibold text-gray-700">Пользователей пока нет</div>
            <div class="mt-1 text-sm text-gray-400">Создайте первого пользователя</div>
            <button
                type="button"
                class="mt-6 inline-block rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                @click="createModalOpen = true"
            >
                Создать пользователя
            </button>
        </div>

        <section v-else>
            <h2 class="mb-4 text-lg font-bold text-gray-800">Все пользователи</h2>

            <ul class="space-y-3">
                <li
                    v-for="item in users"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 p-4"
                >
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-1">
                                <span class="max-w-full truncate font-semibold text-gray-800">{{ item.name || item.email }}</span>
                                <span
                                    v-if="item.is_admin"
                                    class="rounded-md bg-purple-100 px-1.5 py-0.5 text-xs font-semibold text-purple-700"
                                >админ</span>
                                <span
                                    v-if="item.id === user?.id"
                                    class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-xs font-semibold text-indigo-700"
                                >вы</span>
                                <span
                                    v-for="tag in (item.tags || [])"
                                    :key="tag"
                                    class="rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
                                >{{ tag }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-gray-400">
                                <template v-if="item.name && item.name !== item.email">{{ item.email }} · </template>
                                создан {{ item.created_at }}
                            </div>
                        </div>
                    </div>
                    <div v-if="isAdmin" class="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-lg bg-white p-2 text-slate-600 ring-1 ring-slate-200 transition hover:bg-indigo-50 hover:text-indigo-700 hover:ring-indigo-100"
                            title="Редактировать"
                            :aria-label="`Редактировать ${item.name || item.email}`"
                            @click="openEditModal(item)"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-white p-2 text-slate-600 ring-1 ring-slate-200 transition hover:bg-indigo-50 hover:text-indigo-700 hover:ring-indigo-100"
                            title="Изменить пароль"
                            :aria-label="`Изменить пароль ${item.email}`"
                            @click="openPasswordModal(item)"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                        <button
                            v-if="item.id !== user?.id"
                            type="button"
                            class="rounded-lg bg-red-50 p-2 text-red-600 ring-1 ring-red-100 transition hover:bg-red-100 hover:text-red-700"
                            title="Удалить"
                            :aria-label="`Удалить ${item.name || item.email}`"
                            @click="destroyUser(item)"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <CreateUserModal v-model:open="createModalOpen" :available-tags="availableTags" />
        <EditUserModal v-model:open="editModalOpen" :user="editUser" :available-tags="availableTags" />
        <ChangeUserPasswordModal v-model:open="passwordModalOpen" :user="passwordUser" />
    </AppLayout>
</template>
