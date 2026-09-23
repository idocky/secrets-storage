<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    secrets: {
        type: Object,
        required: true,
    },
    emptyTitle: {
        type: String,
        default: 'Секретов пока нет',
    },
    emptyText: {
        type: String,
        default: 'Создайте первый секрет: пароль, файл или заметку',
    },
    createHref: {
        type: String,
        default: '/secrets/create',
    },
});

const ENV_COLUMNS = [
    { key: 'dev', label: 'Dev' },
    { key: 'staging', label: 'Staging' },
    { key: 'production', label: 'Production' },
];

function pageLinks() {
    const current = props.secrets.current_page;
    const last = props.secrets.last_page;
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

/** @type {Record<number, { visible: boolean, loading: boolean, value: string|null, error: string|null }>} */
const revealState = reactive({});

function stateFor(id) {
    if (!revealState[id]) {
        revealState[id] = {
            visible: false,
            loading: false,
            value: null,
            error: null,
        };
    }
    return revealState[id];
}

function getCsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function toggleReveal(secret) {
    const state = stateFor(secret.id);

    if (state.visible) {
        state.visible = false;
        return;
    }

    if (state.value !== null) {
        state.visible = true;
        state.error = null;
        return;
    }

    state.loading = true;
    state.error = null;

    try {
        const response = await fetch(`/secrets/${secret.id}/reveal`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Не удалось получить значение');
        }

        const data = await response.json();
        state.value = data.value;
        state.visible = true;
    } catch (e) {
        state.error = e.message || 'Ошибка';
        state.visible = false;
    } finally {
        state.loading = false;
    }
}

async function copyValue(secret) {
    const state = stateFor(secret.id);

    if (state.value === null) {
        await toggleReveal(secret);
    }

    if (state.value === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(state.value);
    } catch {
        // ignore clipboard failures
    }
}

function destroySecret(secret) {
    if (!confirm(`Удалить секрет «${secret.key}»?`)) {
        return;
    }

    router.delete(`/secrets/${secret.id}`);
}

function isNote(secret) {
    return secret.type === 'note';
}

function noteRevealRows(value) {
    const lines = String(value ?? '').split('\n').length;
    return Math.min(16, Math.max(3, lines));
}
</script>

<template>
    <div
        v-if="!secrets.data?.length"
        class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-12 text-center"
    >
        <svg class="mx-auto mb-4 h-14 w-14 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
        <div class="text-lg font-semibold text-gray-700">{{ emptyTitle }}</div>
        <div class="mt-1 text-sm text-gray-400">{{ emptyText }}</div>
        <Link
            :href="createHref"
            class="mt-6 inline-block rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl"
        >
            Создать секрет
        </Link>
    </div>

    <template v-else>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <th class="pb-3 pr-4 font-semibold">Ключ</th>
                        <th class="px-2 pb-3 text-center font-semibold">Dev</th>
                        <th class="px-2 pb-3 text-center font-semibold">Staging</th>
                        <th class="px-2 pb-3 text-center font-semibold">Production</th>
                        <th class="pb-3 pl-4 pr-2 font-semibold">Значение</th>
                        <th class="pb-3 pl-2 text-right font-semibold">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="secret in secrets.data"
                        :key="secret.id"
                        class="border-b border-slate-100 last:border-0"
                    >
                        <td class="py-4 pr-4 align-middle">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="font-semibold text-slate-800">{{ secret.key }}</div>
                                <span
                                    class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="{
                                        'bg-indigo-50 text-indigo-700': secret.type === 'password',
                                        'bg-amber-50 text-amber-800': secret.type === 'file',
                                        'bg-violet-50 text-violet-800': secret.type === 'note',
                                        'bg-slate-100 text-slate-600': !['password', 'file', 'note'].includes(secret.type),
                                    }"
                                >{{ secret.type_label || secret.type }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <Link
                                    v-if="secret.group"
                                    :href="`/secrets/groups/${secret.group.id}`"
                                    class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                >{{ secret.group.name }}</Link>
                                <span
                                    v-for="tag in (secret.tags || [])"
                                    :key="tag"
                                    class="rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
                                >{{ tag }}</span>
                                <span class="text-xs text-slate-400">{{ secret.created_at }}</span>
                            </div>
                        </td>
                        <td
                            v-for="col in ENV_COLUMNS"
                            :key="col.key"
                            class="px-2 py-4 text-center align-middle"
                        >
                            <span
                                v-if="secret.environment[col.key]"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-green-50 text-green-600"
                                :title="`${col.label}: да`"
                                aria-label="да"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span
                                v-else
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-500"
                                :title="`${col.label}: нет`"
                                aria-label="нет"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </span>
                        </td>
                        <td class="py-4 pl-4 pr-2 align-middle">
                            <p
                                v-if="secret.description"
                                class="mb-2 max-w-xs whitespace-pre-wrap break-words text-sm text-slate-600 line-clamp-3"
                                :title="secret.description"
                            >{{ secret.description }}</p>
                            <div v-if="secret.has_value">
                                <textarea
                                    v-if="isNote(secret) && stateFor(secret.id).visible && !stateFor(secret.id).loading"
                                    :value="stateFor(secret.id).value"
                                    readonly
                                    :rows="noteRevealRows(stateFor(secret.id).value)"
                                    spellcheck="false"
                                    autocomplete="off"
                                    class="block w-full min-w-[16rem] max-w-lg resize-y rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-xs leading-relaxed text-slate-700 outline-none"
                                    :aria-label="`Secure Note: ${secret.key}`"
                                />
                                <div
                                    v-else
                                    class="flex min-w-[10rem] max-w-xs items-center gap-2"
                                >
                                    <code
                                        class="block flex-1 truncate rounded-lg bg-slate-50 px-2.5 py-1.5 font-mono text-xs text-slate-700"
                                        :title="stateFor(secret.id).visible ? stateFor(secret.id).value : '••••••••'"
                                    >
                                        <template v-if="stateFor(secret.id).loading">…</template>
                                        <template v-else-if="stateFor(secret.id).visible">{{ stateFor(secret.id).value }}</template>
                                        <template v-else>••••••••</template>
                                    </code>
                                </div>
                            </div>
                            <p v-if="stateFor(secret.id).error" class="mt-1 text-xs text-red-600">
                                {{ stateFor(secret.id).error }}
                            </p>
                            <ul
                                v-if="secret.files?.length"
                                class="space-y-1"
                                :class="{ 'mt-2': secret.has_value || secret.description }"
                            >
                                <li
                                    v-for="file in secret.files"
                                    :key="file.id"
                                >
                                    <a
                                        :href="file.download_url"
                                        class="inline-flex max-w-xs items-center gap-1.5 rounded-lg bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                        :title="`${file.original_name} · ${file.human_size}`"
                                    >
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <span class="truncate">{{ file.original_name }}</span>
                                        <span class="shrink-0 font-medium text-indigo-400">{{ file.human_size }}</span>
                                    </a>
                                </li>
                            </ul>
                        </td>
                        <td class="py-4 pl-2 align-middle">
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    v-if="secret.has_value"
                                    type="button"
                                    class="rounded-lg bg-slate-100 p-2 text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                                    :title="stateFor(secret.id).visible ? 'Скрыть' : 'Показать'"
                                    :aria-label="stateFor(secret.id).visible ? 'Скрыть значение' : 'Показать значение'"
                                    :disabled="stateFor(secret.id).loading"
                                    @click="toggleReveal(secret)"
                                >
                                    <svg v-if="!stateFor(secret.id).visible" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                                <button
                                    v-if="secret.has_value"
                                    type="button"
                                    class="rounded-lg bg-slate-100 p-2 text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                                    title="Копировать"
                                    aria-label="Копировать значение"
                                    @click="copyValue(secret)"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-red-50 p-2 text-red-600 transition hover:bg-red-100 hover:text-red-700"
                                    title="Удалить"
                                    :aria-label="`Удалить ${secret.key}`"
                                    @click="destroySecret(secret)"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="secrets.last_page > 1" class="mt-8 border-t border-gray-100 pt-6">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-gray-400">
                    Показаны {{ secrets.from }}–{{ secrets.to }} из {{ secrets.total }}
                </p>
                <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Пагинация">
                    <span
                        v-if="secrets.current_page <= 1"
                        class="cursor-not-allowed rounded-lg px-3 py-2 text-sm font-medium text-gray-300"
                    >←</span>
                    <Link
                        v-else
                        :href="pageUrl(secrets.current_page - 1)"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                        rel="prev"
                    >←</Link>

                    <template v-for="p in pageLinks()" :key="p">
                        <span
                            v-if="p === secrets.current_page"
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
                        v-if="secrets.current_page < secrets.last_page"
                        :href="pageUrl(secrets.current_page + 1)"
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
