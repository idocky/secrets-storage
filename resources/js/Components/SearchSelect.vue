<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    selectedLabel: {
        type: String,
        default: '',
    },
    fetchUrl: {
        type: String,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Выберите…',
    },
    emptyText: {
        type: String,
        default: 'Ничего не найдено',
    },
    clearable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'update:selectedLabel', 'selected']);

const open = ref(false);
const query = ref('');
const loading = ref(false);
const options = ref([]);
const highlight = ref(-1);
const root = ref(null);
const inputEl = ref(null);
const listboxId = `search-select-${Math.random().toString(36).slice(2, 9)}`;

let debounceTimer = null;
let abortController = null;

const hasValue = computed(() => props.modelValue !== null && props.modelValue !== '');

const buttonLabel = computed(() => {
    if (hasValue.value && props.selectedLabel) {
        return props.selectedLabel;
    }

    return props.placeholder;
});

function getCsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function loadOptions(q) {
    abortController?.abort();
    abortController = new AbortController();
    loading.value = true;

    try {
        const url = new URL(props.fetchUrl, window.location.origin);
        if (q) {
            url.searchParams.set('q', q);
        }

        const response = await fetch(url.pathname + url.search, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            signal: abortController.signal,
        });

        if (!response.ok) {
            throw new Error('Не удалось загрузить список');
        }

        const data = await response.json();
        options.value = Array.isArray(data.groups) ? data.groups : [];
        highlight.value = options.value.length ? 0 : -1;
    } catch (e) {
        if (e.name !== 'AbortError') {
            options.value = [];
            highlight.value = -1;
        }
    } finally {
        loading.value = false;
    }
}

async function restoreLabel() {
    if (!hasValue.value || props.selectedLabel) {
        return;
    }

    try {
        const url = new URL(props.fetchUrl, window.location.origin);
        url.searchParams.set('id', String(props.modelValue));

        const response = await fetch(url.pathname + url.search, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const group = (data.groups || []).find((item) => String(item.id) === String(props.modelValue));

        if (group) {
            emit('update:selectedLabel', group.name);
            emit('selected', group);
        }
    } catch {
        // ignore restore failures
    }
}

async function openSelect() {
    if (open.value) {
        return;
    }

    open.value = true;
    query.value = '';
    await loadOptions('');
    await nextTick();
    inputEl.value?.focus();
}

function closeSelect() {
    open.value = false;
    query.value = '';
    highlight.value = -1;
}

function selectOption(option) {
    emit('update:modelValue', option.id);
    emit('update:selectedLabel', option.name);
    emit('selected', option);
    closeSelect();
}

function clearSelection(event) {
    event.stopPropagation();
    emit('update:modelValue', null);
    emit('update:selectedLabel', '');
    emit('selected', null);
    closeSelect();
}

function onDocumentPointerDown(event) {
    if (!root.value?.contains(event.target)) {
        closeSelect();
    }
}

function onTriggerKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openSelect();
    }
}

function onSearchKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeSelect();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!options.value.length) {
            return;
        }
        highlight.value = (highlight.value + 1) % options.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!options.value.length) {
            return;
        }
        highlight.value = highlight.value <= 0
            ? options.value.length - 1
            : highlight.value - 1;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const option = options.value[highlight.value];
        if (option) {
            selectOption(option);
        }
    }
}

watch(query, (q) => {
    if (!open.value) {
        return;
    }

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loadOptions(q.trim());
    }, 200);
});

watch(() => props.modelValue, () => {
    restoreLabel();
});

onMounted(() => {
    document.addEventListener('mousedown', onDocumentPointerDown);
    restoreLabel();
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentPointerDown);
    clearTimeout(debounceTimer);
    abortController?.abort();
});
</script>

<template>
    <div ref="root" class="relative">
        <div
            class="flex w-full items-center gap-1 rounded-xl border bg-white pr-2 outline-none transition"
            :class="open ? 'border-indigo-400 ring-2 ring-indigo-400/30' : 'border-gray-200'"
        >
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center px-4 py-3 text-left text-sm outline-none"
                :aria-expanded="open"
                aria-haspopup="listbox"
                :aria-controls="listboxId"
                @click="open ? closeSelect() : openSelect()"
                @keydown="onTriggerKeydown"
            >
                <span
                    class="min-w-0 flex-1 truncate"
                    :class="hasValue && selectedLabel ? 'text-gray-800' : 'text-slate-400'"
                >
                    {{ buttonLabel }}
                </span>
            </button>
            <button
                v-if="clearable && hasValue"
                type="button"
                class="rounded-md p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                aria-label="Очистить"
                @click="clearSelection"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <svg
                class="mr-1 h-4 w-4 shrink-0 text-slate-400 transition"
                :class="open ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <div
            v-if="open"
            class="absolute z-30 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10"
        >
            <div class="border-b border-slate-100 p-2">
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-lg border border-gray-200 bg-slate-50 px-3 py-2 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-400/30"
                    placeholder="Поиск по названию"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="open"
                    :aria-controls="listboxId"
                    @keydown="onSearchKeydown"
                    @click.stop
                >
            </div>

            <ul
                :id="listboxId"
                class="max-h-56 overflow-y-auto py-1"
                role="listbox"
            >
                <li v-if="loading" class="px-4 py-3 text-sm text-slate-400">
                    Поиск…
                </li>
                <li
                    v-else-if="!options.length"
                    class="px-4 py-3 text-sm text-slate-400"
                >
                    {{ emptyText }}
                </li>
                <li
                    v-for="(option, index) in options"
                    v-else
                    :key="option.id"
                    role="option"
                    class="cursor-pointer px-4 py-2.5 text-sm transition"
                    :class="index === highlight
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'text-slate-700 hover:bg-slate-50'"
                    :aria-selected="String(option.id) === String(modelValue)"
                    @mousedown.prevent="selectOption(option)"
                    @mouseenter="highlight = index"
                >
                    {{ option.name }}
                </li>
            </ul>
        </div>
    </div>
</template>
