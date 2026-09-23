<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SearchSelect from '../../Components/SearchSelect.vue';
import CreateSecretGroupModal from '../../Components/CreateSecretGroupModal.vue';
import PasswordField from '../../Components/PasswordField.vue';
import TagInput from '../../Components/TagInput.vue';

const props = defineProps({
    environments: {
        type: Array,
        required: true,
    },
    secretTypes: {
        type: Array,
        required: true,
    },
    defaultType: {
        type: String,
        default: 'password',
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    initialGroup: {
        type: Object,
        default: null,
    },
});

const ENV_LABELS = {
    dev: 'Dev',
    staging: 'Staging',
    production: 'Production',
};

const form = useForm({
    type: props.defaultType,
    key: '',
    value: '',
    description: '',
    environment: [],
    secret_group_id: props.initialGroup?.id ?? null,
    tags: [...(props.initialGroup?.tags ?? [])],
    files: [],
});

const selectedGroupName = ref(props.initialGroup?.name || '');
const groupModalOpen = ref(false);
const fileInput = ref(null);
const isDragOver = ref(false);

function bindFileInput(el) {
    fileInput.value = el;
}
const page = usePage();
const canCreateTags = computed(() => Boolean(page.props.auth?.user?.is_admin));

const selectedType = computed(() => {
    return props.secretTypes.find((item) => item.slug === form.type) ?? props.secretTypes[0] ?? null;
});

const selectedFields = computed(() => selectedType.value?.fields ?? []);

function fieldError(name) {
    if (name === 'files') {
        return form.errors.files || form.errors['files.0'];
    }

    return form.errors[name];
}

watch(
    () => form.type,
    (next, prev) => {
        if (prev === undefined || next === prev) {
            return;
        }

        form.value = '';
        form.description = '';
        form.files = [];
        form.clearErrors('value', 'files', 'description', 'files.0');
    },
);

function applyGroupTags(group) {
    if (!group) {
        return;
    }

    form.tags = [...(group.tags ?? [])];
}

watch(
    () => page.props.flash?.created_group,
    (group) => {
        if (!group) {
            return;
        }

        form.secret_group_id = group.id;
        selectedGroupName.value = group.name;
        applyGroupTags(group);
    },
    { immediate: true },
);

function toggleEnvironment(env) {
    const idx = form.environment.indexOf(env);
    if (idx === -1) {
        form.environment.push(env);
    } else {
        form.environment.splice(idx, 1);
    }
}

function isEnvSelected(env) {
    return form.environment.includes(env);
}

function formatBytes(bytes) {
    if (!bytes) return '0 Б';
    const units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function addFiles(fileList) {
    const incoming = Array.from(fileList || []);
    if (!incoming.length) {
        return;
    }

    const existing = new Set(form.files.map((file) => `${file.name}:${file.size}:${file.lastModified}`));
    const next = incoming.filter((file) => !existing.has(`${file.name}:${file.size}:${file.lastModified}`));

    form.files = [...form.files, ...next];
}

function onFilesSelected(e) {
    addFiles(e.target.files);
    e.target.value = '';
}

function onDrop(e) {
    e.preventDefault();
    isDragOver.value = false;
    addFiles(e.dataTransfer.files);
}

function removeFile(index) {
    form.files = form.files.filter((_, i) => i !== index);
}

function submit() {
    form.post('/secrets', {
        forceFormData: form.files.length > 0,
    });
}
</script>

<template>
    <AppLayout title="Новый секрет" subtitle="Тип, ключ и доступ" max-width="max-w-2xl">
        <template #actions>
            <Link
                href="/secrets"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
            >
                К списку
            </Link>
        </template>

        <div
            v-if="Object.keys(form.errors).length"
            class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            <ul class="list-disc space-y-1 pl-4">
                <li v-for="(message, key) in form.errors" :key="key" class="font-semibold">
                    {{ message }}
                </li>
            </ul>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <label for="type" class="mb-1.5 block text-sm font-semibold text-gray-700">Тип</label>
                <select
                    id="type"
                    v-model="form.type"
                    required
                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                >
                    <option
                        v-for="secretType in secretTypes"
                        :key="secretType.slug"
                        :value="secretType.slug"
                    >
                        {{ secretType.label }}
                    </option>
                </select>
                <p v-if="form.errors.type" class="mt-1.5 text-sm font-semibold text-red-600">
                    {{ form.errors.type }}
                </p>
            </div>

            <div>
                <label for="key" class="mb-1.5 block text-sm font-semibold text-gray-700">Ключ</label>
                <input
                    id="key"
                    v-model="form.key"
                    type="text"
                    required
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                    placeholder="Пароль от базы продакшена"
                >
                <p class="mt-1.5 text-xs text-slate-400">Любое читаемое название: кириллица, латиница, пробелы, обычные знаки</p>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label class="block text-sm font-semibold text-gray-700">Группа</label>
                    <button
                        type="button"
                        class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-700"
                        @click="groupModalOpen = true"
                    >
                        Создать группу
                    </button>
                </div>
                <SearchSelect
                    v-model="form.secret_group_id"
                    v-model:selected-label="selectedGroupName"
                    fetch-url="/secret-groups/search"
                    placeholder="Выберите группу"
                    empty-text="Группы не найдены"
                    @selected="applyGroupTags"
                />
                <p class="mt-1.5 text-xs text-slate-400">Необязательно. Теги группы подставятся в секрет</p>
            </div>

            <TagInput
                id="secret-tags"
                v-model="form.tags"
                :suggestions="availableTags"
                :allow-create="canCreateTags"
                :error="form.errors.tags || form.errors['tags.0']"
                hint="Кто с этими тегами увидит секрет. Можно сузить или расширить теги группы"
            />

            <template v-for="field in selectedFields" :key="field.name">
                <div v-if="field.widget === 'password'">
                    <PasswordField
                        :id="field.name"
                        v-model="form[field.name]"
                        :label="field.label"
                        placeholder="пароль или API key"
                        :required="field.required"
                        :error="fieldError(field.name)"
                    />
                    <p v-if="field.hint && !fieldError(field.name)" class="mt-1.5 text-xs text-slate-400">
                        {{ field.hint }}
                    </p>
                </div>

                <div v-else-if="field.widget === 'textarea'">
                    <label :for="field.name" class="mb-1.5 block text-sm font-semibold text-gray-700">
                        {{ field.label }}
                    </label>
                    <textarea
                        :id="field.name"
                        v-model="form[field.name]"
                        :required="field.required"
                        rows="6"
                        autocomplete="off"
                        spellcheck="false"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/30"
                    />
                    <p v-if="fieldError(field.name)" class="mt-1.5 text-sm font-semibold text-red-600">
                        {{ fieldError(field.name) }}
                    </p>
                    <p v-else-if="field.hint" class="mt-1.5 text-xs text-slate-400">{{ field.hint }}</p>
                </div>

                <div v-else-if="field.widget === 'files'">
                    <div class="mb-1.5 text-sm font-semibold text-gray-700">{{ field.label }}</div>
                    <p v-if="field.hint" class="mb-3 text-xs text-slate-400">{{ field.hint }}</p>
                    <input
                        :ref="bindFileInput"
                        type="file"
                        multiple
                        class="hidden"
                        @change="onFilesSelected"
                    >
                    <div
                        role="button"
                        tabindex="0"
                        aria-label="Выбрать файлы или перетащить их сюда"
                        class="cursor-pointer rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-8 text-center transition hover:border-indigo-500 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-400/30"
                        :class="{ 'border-indigo-500 bg-indigo-50': isDragOver }"
                        @click="fileInput?.click()"
                        @keydown.enter.prevent="fileInput?.click()"
                        @keydown.space.prevent="fileInput?.click()"
                        @dragenter.prevent="isDragOver = true"
                        @dragover.prevent="isDragOver = true"
                        @dragleave.prevent="isDragOver = false"
                        @dragend.prevent="isDragOver = false"
                        @drop="onDrop"
                    >
                        <svg class="mx-auto mb-3 h-10 w-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <div class="text-sm font-semibold text-gray-700">Выберите файлы</div>
                        <div class="mt-0.5 text-xs text-gray-400">или перетащите их сюда</div>
                    </div>
                    <ul v-if="form.files.length" class="mt-3 space-y-2">
                        <li
                            v-for="(file, index) in form.files"
                            :key="`${file.name}-${file.size}-${file.lastModified}`"
                            class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800">{{ file.name }}</div>
                                <div class="text-xs text-slate-400">{{ formatBytes(file.size) }}</div>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
                                :aria-label="`Убрать ${file.name}`"
                                @click="removeFile(index)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </li>
                    </ul>
                    <p v-if="fieldError('files')" class="mt-1.5 text-sm font-semibold text-red-600">
                        {{ fieldError('files') }}
                    </p>
                </div>
            </template>

            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Окружения</div>
                <p class="mb-3 text-xs text-slate-400">Где можно использовать этот секрет</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="env in environments"
                        :key="env"
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                        :class="isEnvSelected(env)
                            ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/20'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="toggleEnvironment(env)"
                    >
                        {{ ENV_LABELS[env] || env }}
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                >
                    {{ form.processing ? 'Сохранение…' : 'Создать секрет' }}
                </button>
                <Link
                    href="/secrets"
                    class="rounded-xl bg-slate-100 px-6 py-3 font-semibold text-slate-700 transition hover:bg-slate-200"
                >
                    Отмена
                </Link>
            </div>
        </form>

        <CreateSecretGroupModal v-model:open="groupModalOpen" :available-tags="availableTags" />
    </AppLayout>
</template>
