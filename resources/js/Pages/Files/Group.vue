<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FilesTable from '../../Components/FilesTable.vue';
import EditableTagChips from '../../Components/EditableTagChips.vue';

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
    files: {
        type: Object,
        required: true,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
});

const uploadHref = computed(() => `/upload?group=${props.group.id}`);

const subtitle = computed(() => {
    const total = props.files.total ?? 0;

    if (total === 0) {
        return 'В этой группе пока нет файлов';
    }

    if (props.files.last_page > 1) {
        return `${pluralize(total, ['файл', 'файла', 'файлов'])} · страница ${props.files.current_page} из ${props.files.last_page}`;
    }

    return pluralize(total, ['файл', 'файла', 'файлов']);
});

function pluralize(count, forms) {
    const abs = Math.abs(count) % 100;
    const last = abs % 10;
    let form = forms[2];

    if (abs < 11 || abs > 19) {
        if (last === 1) {
            form = forms[0];
        } else if (last >= 2 && last <= 4) {
            form = forms[1];
        }
    }

    return `${count} ${form}`;
}

function updateTags(tags) {
    router.patch(`/file-groups/${props.group.id}/tags`, { tags }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function destroyGroup() {
    if (!confirm(`Удалить группу «${props.group.name}»? Файлы останутся без группы.`)) {
        return;
    }

    router.delete(`/file-groups/${props.group.id}`);
}
</script>

<template>
    <AppLayout :title="group.name" :subtitle="subtitle" max-width="max-w-5xl">
        <template #actions>
            <div class="flex items-center gap-2">
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                >
                    К списку
                </Link>
                <Link
                    :href="uploadHref"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">Загрузить файл</span>
                    <span class="sm:hidden">Загрузить</span>
                </Link>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/50 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/25">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ group.name }}</div>
                    <EditableTagChips
                        class="mt-1.5"
                        :tags="group.tags || []"
                        :suggestions="availableTags"
                        @update="updateTags"
                    />
                </div>
            </div>
            <button
                type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                @click="destroyGroup"
            >
                Удалить группу
            </button>
        </div>

        <FilesTable
            :files="files"
            empty-title="В группе пока пусто"
            empty-text="Загрузите файл в эту группу"
            :create-href="uploadHref"
        />
    </AppLayout>
</template>
