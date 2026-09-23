<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import CreateSecretGroupModal from '../../Components/CreateSecretGroupModal.vue';
import FilesTable from '../../Components/FilesTable.vue';

const props = defineProps({
    groups: {
        type: Object,
        required: true,
    },
    files: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ q: '' }),
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
});

const FILE_DND_TYPE = 'application/x-uploader-file';

const groupModalOpen = ref(false);
const query = ref(props.filters.q ?? '');
const isDraggingFile = ref(false);
const dragOverGroupId = ref(null);
const movingFile = ref(false);

let searchTimer = null;

const isSearching = computed(() => (props.filters.q ?? '').trim() !== '');
const groupsTotal = computed(() => props.groups.total ?? 0);
const isEmpty = computed(() => !props.groups.data?.length && !props.files.data?.length);

const subtitle = computed(() => {
    if (isEmpty.value && !isSearching.value) {
        return 'Пока нет загруженных файлов';
    }

    if (isEmpty.value && isSearching.value) {
        return 'Ничего не найдено';
    }

    const parts = [];

    if (groupsTotal.value > 0) {
        parts.push(pluralize(groupsTotal.value, ['группа', 'группы', 'групп']));
    }

    const filesTotal = props.files.total ?? 0;
    if (filesTotal > 0) {
        parts.push(pluralize(filesTotal, ['файл', 'файла', 'файлов']));
    }

    if (isSearching.value) {
        return parts.length ? `Найдено: ${parts.join(' · ')}` : 'Ничего не найдено';
    }

    if (props.groups.last_page > 1) {
        parts.push(`группы ${props.groups.current_page}/${props.groups.last_page}`);
    }

    if (!groupsTotal.value && props.files.last_page > 1) {
        return `Всего: ${filesTotal} · страница ${props.files.current_page} из ${props.files.last_page}`;
    }

    return parts.join(' · ');
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

function applySearch(value) {
    const q = value.trim();

    router.get('/', q ? { q } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function submitSearch() {
    clearTimeout(searchTimer);
    applySearch(query.value);
}

function clearSearch() {
    query.value = '';
    clearTimeout(searchTimer);
    applySearch('');
}

watch(query, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if ((value.trim()) === (props.filters.q ?? '').trim()) {
            return;
        }

        applySearch(value);
    }, 250);
});

watch(
    () => props.filters.q,
    (q) => {
        const next = q ?? '';
        if (next !== query.value) {
            query.value = next;
        }
    },
);

function groupPageLinks() {
    const current = props.groups.current_page;
    const last = props.groups.last_page;
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    const pages = [];

    for (let p = start; p <= end; p++) {
        pages.push(p);
    }

    return pages;
}

function groupPageUrl(pageNum) {
    const url = new URL(window.location.href);
    url.searchParams.set('groups_page', String(pageNum));
    return url.pathname + url.search;
}

function onFileDragStart() {
    isDraggingFile.value = true;
}

function onFileDragEnd() {
    isDraggingFile.value = false;
    dragOverGroupId.value = null;
}

function onGroupDragOver(event, group) {
    if (!isDraggingFile.value) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverGroupId.value = group.id;
}

function onGroupDragLeave(event, group) {
    const related = event.relatedTarget;
    if (related instanceof Node && event.currentTarget.contains(related)) {
        return;
    }

    if (dragOverGroupId.value === group.id) {
        dragOverGroupId.value = null;
    }
}

function droppedStorageName(event) {
    return event.dataTransfer.getData(FILE_DND_TYPE)
        || event.dataTransfer.getData('text/plain')
        || '';
}

function onGroupDrop(event, group) {
    const wasDragging = isDraggingFile.value;
    const storageName = droppedStorageName(event);

    isDraggingFile.value = false;
    dragOverGroupId.value = null;

    if (!wasDragging || !storageName || movingFile.value) {
        return;
    }

    const file = props.files.data?.find((item) => item.storage_name === storageName);
    if (!file || file.group?.id === group.id) {
        return;
    }

    movingFile.value = true;

    router.patch(`/files/${encodeURIComponent(storageName)}/group`, {
        file_group_id: group.id,
    }, {
        preserveScroll: true,
        onFinish: () => {
            movingFile.value = false;
        },
    });
}

function groupDropClass(group) {
    if (dragOverGroupId.value === group.id) {
        return 'border-indigo-500 bg-indigo-100 shadow-md shadow-indigo-500/20 ring-2 ring-indigo-400/70';
    }

    if (isDraggingFile.value) {
        return 'border-dashed border-indigo-300 bg-indigo-50/80';
    }

    return 'border-slate-200 bg-slate-50/70 hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-indigo-50/60 hover:shadow-md hover:shadow-indigo-500/10';
}
</script>

<template>
    <AppLayout title="Загруженные файлы" :subtitle="subtitle" max-width="max-w-5xl">
        <template #actions>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                    @click="groupModalOpen = true"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="hidden sm:inline">Новая группа</span>
                    <span class="sm:hidden">Группа</span>
                </button>
                <Link
                    href="/upload"
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

        <form class="mb-6" @submit.prevent="submitSearch">
            <label for="files-search" class="sr-only">Поиск</label>
            <div class="relative">
                <svg
                    class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input
                    id="files-search"
                    v-model="query"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-11 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                    placeholder="Поиск по имени файла или названию группы"
                >
                <button
                    v-if="query"
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Очистить поиск"
                    @click="clearSearch"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </form>

        <div
            v-if="isEmpty && !isSearching"
            class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-12 text-center"
        >
            <svg class="mx-auto mb-4 h-14 w-14 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
            <div class="text-lg font-semibold text-gray-700">Файлов пока нет</div>
            <div class="mt-1 text-sm text-gray-400">Создайте группу или загрузите первый файл</div>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <button
                    type="button"
                    class="rounded-xl bg-slate-100 px-6 py-3 font-semibold text-slate-700 transition hover:bg-slate-200"
                    @click="groupModalOpen = true"
                >
                    Новая группа
                </button>
                <Link
                    href="/upload"
                    class="inline-block rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
                >
                    Перейти к загрузке
                </Link>
            </div>
        </div>

        <div
            v-else-if="isEmpty && isSearching"
            class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center"
        >
            <div class="text-lg font-semibold text-gray-700">Ничего не найдено</div>
            <div class="mt-1 text-sm text-gray-400">
                По запросу «{{ filters.q }}» нет групп и файлов
            </div>
        </div>

        <div v-else class="space-y-6">
            <div v-if="groups.data?.length">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="group in groups.data"
                        :key="group.id"
                        class="rounded-2xl border transition"
                        :class="groupDropClass(group)"
                        @dragover="onGroupDragOver($event, group)"
                        @dragenter="onGroupDragOver($event, group)"
                        @dragleave="onGroupDragLeave($event, group)"
                        @drop.prevent="onGroupDrop($event, group)"
                    >
                        <Link
                            :href="`/files/groups/${group.id}`"
                            class="flex items-center gap-4 p-4"
                            :class="{ 'pointer-events-none': isDraggingFile }"
                        >
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 flex-wrap items-center gap-1">
                                    <span class="truncate font-semibold text-slate-800">{{ group.name }}</span>
                                    <span
                                        v-for="tag in (group.tags || [])"
                                        :key="tag"
                                        class="rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
                                    >{{ tag }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-slate-400">
                                    <template v-if="dragOverGroupId === group.id">Отпустите, чтобы переместить</template>
                                    <template v-else>{{ pluralize(group.files_count, ['файл', 'файла', 'файлов']) }}</template>
                                </div>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <div v-if="groups.last_page > 1" class="mt-4">
                    <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <p class="text-sm text-gray-400">
                            Группы {{ groups.from }}–{{ groups.to }} из {{ groups.total }}
                        </p>
                        <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Пагинация групп">
                            <span
                                v-if="groups.current_page <= 1"
                                class="cursor-not-allowed rounded-lg px-3 py-2 text-sm font-medium text-gray-300"
                            >←</span>
                            <Link
                                v-else
                                :href="groupPageUrl(groups.current_page - 1)"
                                class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                                rel="prev"
                            >←</Link>

                            <template v-for="p in groupPageLinks()" :key="p">
                                <span
                                    v-if="p === groups.current_page"
                                    class="rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/20"
                                    aria-current="page"
                                >{{ p }}</span>
                                <Link
                                    v-else
                                    :href="groupPageUrl(p)"
                                    class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-indigo-50 hover:text-indigo-600"
                                >{{ p }}</Link>
                            </template>

                            <Link
                                v-if="groups.current_page < groups.last_page"
                                :href="groupPageUrl(groups.current_page + 1)"
                                class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                                rel="next"
                            >→</Link>
                            <span
                                v-else
                                class="cursor-not-allowed rounded-lg px-3 py-2 text-sm font-medium text-gray-300"
                            >→</span>
                        </nav>
                    </div>
                </div>
            </div>

            <div v-if="groups.data?.length && (files.data?.length || files.total)">
                <p class="text-center text-xs text-slate-400">
                    {{ isDraggingFile ? 'Отпустите файл над группой' : 'Перетащите файл на группу, чтобы переместить его внутрь' }}
                </p>
            </div>

            <FilesTable
                v-if="files.data?.length || files.total"
                :files="files"
                :enable-drag="!!groups.data?.length"
                @file-drag-start="onFileDragStart"
                @file-drag-end="onFileDragEnd"
            />
        </div>

        <CreateSecretGroupModal
            v-model:open="groupModalOpen"
            :available-tags="availableTags"
            store-url="/file-groups"
            description="Группа поможет собрать файлы в один набор"
        />
    </AppLayout>
</template>
