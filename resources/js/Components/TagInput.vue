<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    suggestions: {
        type: Array,
        default: () => [],
    },
    error: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: 'tags',
    },
    allowCreate: {
        type: Boolean,
        default: true,
    },
    hint: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

const unusedSuggestions = computed(() => {
    const selected = new Set(props.modelValue.map((tag) => tag.toLowerCase()));
    const query = draft.value.trim().toLowerCase();

    return props.suggestions
        .filter((tag) => !selected.has(String(tag).toLowerCase()))
        .filter((tag) => !query || String(tag).toLowerCase().includes(query))
        .slice(0, 8);
});

function resolveName(name) {
    const trimmed = name.trim().replace(/\s+/g, ' ');
    const existing = props.suggestions.find(
        (tag) => String(tag).toLowerCase() === trimmed.toLowerCase(),
    );

    return existing ?? trimmed;
}

function addTag(name) {
    const tag = resolveName(name);

    if (!tag || tag.length > 50) {
        return;
    }

    if (!props.allowCreate) {
        const known = props.suggestions.some(
            (item) => String(item).toLowerCase() === tag.toLowerCase(),
        );

        if (!known) {
            draft.value = '';
            return;
        }
    }

    if (props.modelValue.some((item) => item.toLowerCase() === tag.toLowerCase())) {
        draft.value = '';
        return;
    }

    emit('update:modelValue', [...props.modelValue, tag]);
    draft.value = '';
}

function removeTag(name) {
    emit('update:modelValue', props.modelValue.filter((tag) => tag !== name));
}

function onKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag(draft.value);
        return;
    }

    if (event.key === 'Backspace' && !draft.value && props.modelValue.length) {
        removeTag(props.modelValue[props.modelValue.length - 1]);
        return;
    }

    if (event.key === 'Escape' && draft.value) {
        event.stopPropagation();
        draft.value = '';
    }
}

function onBlur() {
    if (draft.value.trim()) {
        addTag(draft.value);
    }
}
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-semibold text-gray-700">
            Теги
        </label>
        <div
            class="flex min-h-[3.25rem] flex-wrap items-center gap-1.5 rounded-xl border bg-white px-3 py-2 outline-none transition focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-400/30"
            :class="error ? 'border-red-300' : 'border-gray-200'"
        >
            <span
                v-for="tag in modelValue"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-md bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-800"
            >
                {{ tag }}
                <button
                    type="button"
                    class="rounded text-teal-700 transition hover:text-teal-950"
                    :aria-label="`Удалить тег ${tag}`"
                    @click="removeTag(tag)"
                >
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <input
                :id="id"
                v-model="draft"
                type="text"
                maxlength="50"
                autocomplete="off"
                class="min-w-[7rem] flex-1 border-0 bg-transparent py-1 text-sm text-gray-800 outline-none placeholder:text-slate-400"
                placeholder="новый тег"
                @keydown="onKeydown"
                @blur="onBlur"
            >
        </div>
        <p v-if="error" class="mt-1.5 text-sm font-semibold text-red-600">
            {{ error }}
        </p>
        <p v-else class="mt-1.5 text-xs text-slate-400">
            {{ hint || (allowCreate ? 'Enter или запятая — добавить тег' : 'Можно выбрать только существующие теги') }}
        </p>
        <div v-if="unusedSuggestions.length" class="mt-2 flex flex-wrap gap-1.5">
            <button
                v-for="tag in unusedSuggestions"
                :key="tag"
                type="button"
                class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600 transition hover:bg-teal-100 hover:text-teal-800"
                @mousedown.prevent="addTag(tag)"
            >
                {{ tag }}
            </button>
        </div>
    </div>
</template>
