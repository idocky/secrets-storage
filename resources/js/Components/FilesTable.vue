<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const FILE_DND_TYPE = 'application/x-uploader-file';

const props = defineProps({
    files: {
        type: Object,
        required: true,
    },
    emptyTitle: {
        type: String,
        default: 'Файлов пока нет',
    },
    emptyText: {
        type: String,
        default: 'Загрузите первый файл, чтобы он появился здесь',
    },
    createHref: {
        type: String,
        default: '/upload',
    },
    createLabel: {
        type: String,
        default: 'Загрузить файл',
    },
    enableDrag: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['file-drag-start', 'file-drag-end']);

const draggingStorageName = ref(null);

/** @type {Record<string, boolean>} */
const copying = reactive({});
/** @type {Record<string, boolean>} */
const copied = reactive({});

function destroyFile(file) {
    if (!confirm(`Удалить файл «${file.original_name}»? Файл будет удалён из базы и из Spaces.`)) {
        return;
    }

    router.delete(`/files/${file.storage_name}`);
}

function togglePublic(file) {
    router.patch(`/files/${file.storage_name}/public`, {
        is_public: !file.is_public,
    }, {
        preserveScroll: true,
    });
}

async function copyDownloadLink(file) {
    const key = file.storage_name;
    copying[key] = true;

    try {
        const url = file.download_url.startsWith('http')
            ? file.download_url
            : new URL(file.download_url, window.location.origin).href;

        await navigator.clipboard.writeText(url);
        copied[key] = true;
        setTimeout(() => {
            copied[key] = false;
        }, 1500);
    } catch {
        // ignore
    } finally {
        copying[key] = false;
    }
}

function pageLinks() {
    const current = props.files.current_page;
    const last = props.files.last_page;
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    const pages = [];

    for (let p = start; p <= end; p++) {
        pages.push(p);
    }

    return pages;
}

function pageUrl(pageNum) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(pageNum));
    return url.pathname + url.search;
}

function onDragStart(event, file) {
    if (!props.enableDrag) {
        event.preventDefault();
        return;
    }

    const target = event.target;
    if (target instanceof Element && target.closest('button, a, input, [role="switch"]')) {
        event.preventDefault();
        return;
    }

    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData(FILE_DND_TYPE, file.storage_name);
    event.dataTransfer.setData('text/plain', file.storage_name);
    draggingStorageName.value = file.storage_name;
    emit('file-drag-start', file);
}

function onDragEnd() {
    draggingStorageName.value = null;
    emit('file-drag-end');
}
</script>

<template>
    <div
        v-if="!files.data?.length"
        class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-12 text-center"
    >
        <svg class="mx-auto mb-4 h-14 w-14 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
        </svg>
        <div class="text-lg font-semibold text-gray-700">{{ emptyTitle }}</div>
        <div class="mt-1 text-sm text-gray-400">{{ emptyText }}</div>
        <Link
            :href="createHref"
            class="mt-6 inline-block rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
        >
            {{ createLabel }}
        </Link>
    </div>

    <template v-else>
        <ul class="space-y-3">
            <li
                v-for="file in files.data"
                :key="file.storage_name"
                :draggable="enableDrag"
                class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-4 transition hover:bg-indigo-50/60"
                :class="{
                    'cursor-grab select-none active:cursor-grabbing': enableDrag,
                    'opacity-50 ring-2 ring-indigo-300': draggingStorageName === file.storage_name,
                }"
                @dragstart="onDragStart($event, file)"
                @dragend="onDragEnd"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        v-if="enableDrag"
                        class="mt-2.5 shrink-0 text-slate-300"
                        title="Перетащить в группу"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm8-12a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                    </div>
                    <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2">
                            <div class="truncate font-semibold text-gray-800" :title="file.original_name">
                                {{ file.original_name }}
                            </div>
                            <span
                                v-if="file.is_public"
                                class="shrink-0 rounded-md bg-green-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700"
                            >
                                public
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <Link
                                v-if="file.group"
                                :href="`/files/groups/${file.group.id}`"
                                class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                            >{{ file.group.name }}</Link>
                            <span
                                v-for="tag in (file.tags || [])"
                                :key="tag"
                                class="rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
                            >{{ tag }}</span>
                        </div>
                        <div class="mt-0.5 text-sm text-gray-500">
                            {{ file.human_size }}
                            <template v-if="file.mime_type"> · {{ file.mime_type }}</template>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-400">
                            {{ file.created_at }}
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="file.is_public"
                        :title="file.is_public ? 'Сделать приватным' : 'Сделать публичным'"
                        :aria-label="file.is_public ? `Сделать «${file.original_name}» приватным` : `Сделать «${file.original_name}» публичным`"
                        class="relative h-7 w-12 shrink-0 rounded-full transition focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                        :class="file.is_public ? 'bg-green-500' : 'bg-slate-300'"
                        @click="togglePublic(file)"
                    >
                        <span
                            class="absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                            :class="file.is_public ? 'translate-x-5' : 'translate-x-0'"
                        />
                    </button>

                    <button
                        type="button"
                        class="rounded-lg p-2 transition"
                        :class="copied[file.storage_name]
                            ? 'bg-green-100 text-green-700'
                            : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-700'"
                        :title="copied[file.storage_name] ? 'Скопировано' : 'Копировать ссылку'"
                        :aria-label="`Копировать ссылку на ${file.original_name}`"
                        :disabled="copying[file.storage_name]"
                        @click="copyDownloadLink(file)"
                    >
                        <svg v-if="!copied[file.storage_name]" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>

                    <a
                        :href="file.download_url"
                        draggable="false"
                        class="rounded-lg bg-indigo-100 p-2 text-indigo-700 transition hover:bg-indigo-200"
                        title="Скачать"
                        :aria-label="`Скачать ${file.original_name}`"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </a>

                    <button
                        type="button"
                        class="rounded-lg bg-red-50 p-2 text-red-600 transition hover:bg-red-100 hover:text-red-700"
                        title="Удалить"
                        :aria-label="`Удалить ${file.original_name}`"
                        @click="destroyFile(file)"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </li>
        </ul>

        <div v-if="files.last_page > 1" class="mt-8 border-t border-gray-100 pt-6">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-gray-400">
                    Показаны {{ files.from }}–{{ files.to }} из {{ files.total }}
                </p>
                <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Пагинация">
                    <span
                        v-if="files.current_page <= 1"
                        class="cursor-not-allowed rounded-lg px-3 py-2 text-sm font-medium text-gray-300"
                    >←</span>
                    <Link
                        v-else
                        :href="pageUrl(files.current_page - 1)"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                        rel="prev"
                    >←</Link>

                    <template v-for="p in pageLinks()" :key="p">
                        <span
                            v-if="p === files.current_page"
                            class="rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/20"
                            aria-current="page"
                        >{{ p }}</span>
                        <Link
                            v-else
                            :href="pageUrl(p)"
                            class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-indigo-50 hover:text-indigo-600"
                        >{{ p }}</Link>
                    </template>

                    <Link
                        v-if="files.current_page < files.last_page"
                        :href="pageUrl(files.current_page + 1)"
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
    </template>
</template>
