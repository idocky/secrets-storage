<script setup>
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ChangeUserPasswordModal from '../../Components/ChangeUserPasswordModal.vue';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const passwordModalOpen = ref(false);

const displayName = computed(() => props.profile.name || props.profile.email);
const initial = computed(() => displayName.value.charAt(0).toUpperCase());
const tags = computed(() => props.profile.tags || []);
</script>

<template>
    <AppLayout title="Профиль" :subtitle="displayName" max-width="max-w-xl">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl font-bold text-white shadow-lg shadow-indigo-500/25">
                {{ initial }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-xl font-bold text-slate-800">
                        {{ displayName }}
                    </h2>
                    <span
                        v-if="profile.is_admin"
                        class="rounded-md bg-purple-100 px-1.5 py-0.5 text-xs font-semibold text-purple-700"
                    >админ</span>
                </div>
                <p
                    v-if="profile.name && profile.name !== profile.email"
                    class="mt-0.5 truncate text-sm text-slate-400"
                >
                    {{ profile.email }}
                </p>
            </div>
        </div>

        <section class="mt-8">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                Теги
            </h3>
            <div v-if="tags.length" class="flex flex-wrap gap-2">
                <span
                    v-for="tag in tags"
                    :key="tag"
                    class="rounded-lg bg-teal-100 px-2.5 py-1 text-sm font-semibold text-teal-800"
                >{{ tag }}</span>
            </div>
            <p v-else class="text-sm text-slate-400">
                Теги не назначены
            </p>
        </section>

        <div class="mt-8 border-t border-slate-100 pt-6">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                @click="passwordModalOpen = true"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                Изменить пароль
            </button>
        </div>

        <ChangeUserPasswordModal
            v-model:open="passwordModalOpen"
            :user="profile"
            action="/profile/password"
            description="Новый пароль для вашей учётной записи"
        />
    </AppLayout>
</template>
