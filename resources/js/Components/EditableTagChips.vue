<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
    suggestions: {
        type: Array,
        default: () => [],
    },
    allowCreate: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update']);

const pickerOpen = ref(false);
const query = ref('');
const highlight = ref(0);
const root = ref(null);
const inputEl = ref(null);

const selected = computed(() => props.tags.map((tag) => String(tag)));

const selectedSet = computed(() => new Set(selected.value.map((tag) => tag.toLowerCase())));

const unusedSuggestions = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.suggestions
        .map((tag) => String(tag))
        .filter((tag) => !selectedSet.value.has(tag.toLowerCase()))
        .filter((tag) => !q || tag.toLowerCase().includes(q));
});

const canCreateQuery = computed(() => {
    if (!props.allowCreate) {
        return false;
    }

    const q = query.value.trim().replace(/\s+/g, ' ');

    if (!q || q.length > 50) {
        return false;
    }

    const lower = q.toLowerCase();

    if (selectedSet.value.has(lower)) {
        return false;
    }

    return !props.suggestions.some((tag) => String(tag).toLowerCase() === lower);
});

watch(query, () => {
    highlight.value = 0;
});

const options = computed(() => {
    const items = unusedSuggestions.value.map((tag) => ({
        type: 'existing',
        value: tag,
        label: tag,
    }));

    if (canCreateQuery.value) {
        items.push({
            type: 'create',
            value: query.value.trim().replace(/\s+/g, ' '),
            label: `Создать «${query.value.trim().replace(/\s+/g, ' ')}»`,
        });
    }

    return items;
});

function persist(next) {
    emit('update', next);
}

function removeTag(name) {
    if (props.disabled) {
        return;
    }

    persist(selected.value.filter((tag) => tag !== name));
}

function addTag(name) {
    const tag = name.trim().replace(/\s+/g, ' ');

    if (!tag || tag.length > 50 || props.disabled) {
        return;
    }

    if (selectedSet.value.has(tag.toLowerCase())) {
        closePicker();
        return;
    }

    persist([...selected.value, tag]);
    closePicker();
}

async function openPicker() {
    if (props.disabled || pickerOpen.value) {
        return;
    }

    pickerOpen.value = true;
    query.value = '';
    highlight.value = 0;
    await nextTick();
    inputEl.value?.focus();
}

function closePicker() {
    pickerOpen.value = false;
    query.value = '';
    highlight.value = 0;
}

function choose(option) {
    addTag(option.value);
}

function onSearchKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closePicker();
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
        const option = options.value[highlight.value] ?? options.value[0];
        if (option) {
            choose(option);
        }
    }
}

function onDocumentPointerDown(event) {
    if (!root.value?.contains(event.target)) {
        closePicker();
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('mousedown', onDocumentPointerDown);
}

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentPointerDown);
});
</script>

<template>
    <div ref="root" class="relative flex min-w-0 flex-wrap items-center gap-1">
        <span
            v-for="tag in selected"
            :key="tag"
            class="inline-flex items-center gap-1 rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
        >
            {{ tag }}
            <button
                type="button"
                class="rounded text-teal-700 transition hover:text-teal-950 disabled:cursor-not-allowed disabled:opacity-40"
                :aria-label="`Открепить тег ${tag}`"
                :disabled="disabled"
                @click="removeTag(tag)"
            >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </span>

        <div class="relative">
            <button
                type="button"
                class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-md border border-dashed border-teal-400 px-1 text-xs font-bold text-teal-700 transition hover:bg-teal-100 disabled:cursor-not-allowed disabled:opacity-40"
                title="Добавить тег"
                aria-label="Добавить тег"
                :aria-expanded="pickerOpen"
                :disabled="disabled"
                @click="pickerOpen ? closePicker() : openPicker()"
            >
                +
            </button>

            <div
                v-if="pickerOpen"
                class="absolute left-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10"
            >
                <div class="border-b border-slate-100 p-2">
                    <input
                        ref="inputEl"
                        v-model="query"
                        type="text"
                        maxlength="50"
                        autocomplete="off"
                        spellcheck="false"
                        class="w-full rounded-lg border border-gray-200 bg-slate-50 px-3 py-2 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-400/30"
                        placeholder="Найти или создать"
                        @keydown="onSearchKeydown"
                        @click.stop
                    >
                </div>
                <ul class="max-h-48 overflow-y-auto py-1" role="listbox">
                    <li
                        v-if="!options.length"
                        class="px-3 py-2.5 text-sm text-slate-400"
                    >
                        {{ query.trim() ? 'Ничего не найдено' : 'Нет доступных тегов' }}
                    </li>
                    <li
                        v-for="(option, index) in options"
                        v-else
                        :key="`${option.type}-${option.value}`"
                        role="option"
                        class="cursor-pointer px-3 py-2 text-sm transition"
                        :class="index === highlight
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-slate-700 hover:bg-slate-50'"
                        @mousedown.prevent="choose(option)"
                        @mouseenter="highlight = index"
                    >
                        {{ option.label }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
