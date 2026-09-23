<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import * as tus from 'tus-js-client';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SearchSelect from '../../Components/SearchSelect.vue';
import CreateSecretGroupModal from '../../Components/CreateSecretGroupModal.vue';
import TagInput from '../../Components/TagInput.vue';

const props = defineProps({
    tusEndpoint: {
        type: String,
        required: true,
    },
    downloadBase: {
        type: String,
        required: true,
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

const page = usePage();
const canCreateTags = computed(() => Boolean(page.props.auth?.user?.is_admin));
const groupId = ref(props.initialGroup?.id ?? null);
const selectedGroupName = ref(props.initialGroup?.name || '');
const tags = ref([...(props.initialGroup?.tags ?? [])]);
const groupModalOpen = ref(false);

function applyGroupTags(group) {
    if (!group) {
        return;
    }

    tags.value = [...(group.tags ?? [])];
}

watch(
    () => page.props.flash?.created_group,
    (group) => {
        if (!group) {
            return;
        }

        groupId.value = group.id;
        selectedGroupName.value = group.name;
        applyGroupTags(group);
    },
    { immediate: true },
);

const MAX_UPLOAD_SIZE = 5 * 1024 * 1024 * 1024;
const CHUNK_SIZE = 8 * 1024 * 1024; // must stay >= 5 MiB (S3/Spaces min part)
const RETRY_DELAYS = [0, 1000, 3000, 5000, 10000];

const fileInput = ref(null);
const isDragOver = ref(false);
const currentFile = ref(null);
const currentStorageName = ref(null);
const status = ref('Выберите файл для загрузки');
const errorMessage = ref('');
const successUrl = ref('');
const startDisabled = ref(false);

const showFileInfo = ref(false);
const showActions = ref(false);
const showProgress = ref(false);
const showError = ref(false);
const showSuccess = ref(false);

const showStart = ref(true);
const showPause = ref(false);
const showResume = ref(false);
const showCancel = ref(false);
const showReset = ref(false);

const progressPercentage = ref(0);
const progressBytes = ref('');
const progressSpeed = ref('');
const progressEta = ref('');

let upload = null;
let isPaused = false;
let speedTracker = { time: 0, bytes: 0, speed: 0 };

function formatBytes(bytes) {
    if (!bytes) return '0 Б';
    const units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatDuration(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '—';
    if (seconds < 1) return '< 1 сек';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    if (h > 0) return `${h} ч ${m} мин`;
    if (m > 0) return `${m} мин ${s} сек`;
    return `${s} сек`;
}

function randomStorageName(originalName) {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    const random = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    const dotIndex = originalName.lastIndexOf('.');
    const ext = dotIndex > 0 ? originalName.slice(dotIndex) : '';
    return `${random}${ext}`;
}

const fileMeta = computed(() => {
    if (!currentFile.value) return '';
    return `${formatBytes(currentFile.value.size)} • ${currentFile.value.type || 'неизвестный тип'}`;
});

function resetUiForNewFile(file) {
    currentFile.value = file;
    currentStorageName.value = randomStorageName(file.name);
    upload = null;
    isPaused = false;
    speedTracker = { time: 0, bytes: 0, speed: 0 };

    showFileInfo.value = true;
    showActions.value = true;
    showStart.value = true;
    showPause.value = false;
    showResume.value = false;
    showCancel.value = false;
    showReset.value = false;

    showProgress.value = false;
    progressPercentage.value = 0;
    progressBytes.value = '';
    progressSpeed.value = '';
    progressEta.value = '';

    showError.value = false;
    showSuccess.value = false;
    errorMessage.value = '';
    successUrl.value = '';

    if (file.size > MAX_UPLOAD_SIZE) {
        status.value = `Файл слишком большой: максимум ${formatBytes(MAX_UPLOAD_SIZE)}`;
        startDisabled.value = true;
    } else {
        startDisabled.value = false;
        status.value = 'Файл готов к загрузке';
    }
}

function handleFile(file) {
    if (!file) return;
    resetUiForNewFile(file);
}

function openFilePicker() {
    fileInput.value?.click();
}

function onFileChange(e) {
    handleFile(e.target.files?.[0]);
    e.target.value = '';
}

function onDrop(e) {
    e.preventDefault();
    isDragOver.value = false;
    handleFile(e.dataTransfer.files?.[0]);
}

function removeFile() {
    if (upload && !isPaused) {
        upload.abort();
    }
    currentFile.value = null;
    upload = null;
    showFileInfo.value = false;
    showActions.value = false;
    showProgress.value = false;
    showError.value = false;
    showSuccess.value = false;
    status.value = 'Выберите файл для загрузки';
}

function updateProgress(bytesUploaded, bytesTotal) {
    const now = performance.now();
    const percentage = bytesTotal ? Math.round((bytesUploaded / bytesTotal) * 100) : 0;

    progressPercentage.value = percentage;
    progressBytes.value = `${formatBytes(bytesUploaded)} / ${formatBytes(bytesTotal)}`;

    if (speedTracker.time) {
        const dt = (now - speedTracker.time) / 1000;
        const db = bytesUploaded - speedTracker.bytes;
        if (dt > 0.2) {
            const instant = db / dt;
            speedTracker.speed = speedTracker.speed
                ? speedTracker.speed * 0.7 + instant * 0.3
                : instant;
            speedTracker.time = now;
            speedTracker.bytes = bytesUploaded;
        }
    } else {
        speedTracker.time = now;
        speedTracker.bytes = bytesUploaded;
    }

    if (speedTracker.speed > 0) {
        progressSpeed.value = `${formatBytes(speedTracker.speed)}/с`;
        const remaining = (bytesTotal - bytesUploaded) / speedTracker.speed;
        progressEta.value = `Осталось: ${formatDuration(remaining)}`;
    } else {
        progressSpeed.value = 'вычисление скорости…';
        progressEta.value = '';
    }
}

function createUpload(file) {
    return new tus.Upload(file, {
        endpoint: props.tusEndpoint.replace(/\/?$/, '/'),
        retryDelays: RETRY_DELAYS,
        chunkSize: CHUNK_SIZE,
        storeFingerprintForResuming: true,
        removeFingerprintOnSuccess: true,
        withCredentials: true,
        metadata: {
            filename: currentStorageName.value,
            filetype: file.type || 'application/octet-stream',
            originalname: file.name,
            groupid: groupId.value ? String(groupId.value) : '',
            tags: JSON.stringify(tags.value),
        },
        onError(error) {
            showProgress.value = false;
            showStart.value = false;
            showPause.value = false;
            showResume.value = false;
            showCancel.value = false;
            showReset.value = true;
            showError.value = true;
            errorMessage.value = error?.message || String(error);
            status.value = 'Загрузка не удалась';
        },
        onProgress(bytesUploaded, bytesTotal) {
            updateProgress(bytesUploaded, bytesTotal);
        },
        onSuccess() {
            progressPercentage.value = 100;
            showStart.value = false;
            showPause.value = false;
            showResume.value = false;
            showCancel.value = false;
            showReset.value = true;
            showSuccess.value = true;
            successUrl.value = `${props.downloadBase}/${currentStorageName.value}`;
            status.value = 'Загрузка завершена';
        },
    });
}

async function startUpload() {
    if (!currentFile.value) return;

    startDisabled.value = true;
    showError.value = false;
    showSuccess.value = false;
    showProgress.value = true;
    status.value = 'Загрузка…';

    upload = createUpload(currentFile.value);

    try {
        const previousUploads = await upload.findPreviousUploads();
        if (previousUploads.length) {
            upload.resumeFromPreviousUpload(previousUploads[0]);
            status.value = 'Продолжаем ранее начатую загрузку…';
        }
    } catch {
        // not critical
    }

    showStart.value = false;
    showPause.value = true;
    showCancel.value = true;

    upload.start();
}

function pauseUpload() {
    if (!upload) return;
    upload.abort();
    isPaused = true;
    showPause.value = false;
    showResume.value = true;
    status.value = 'Загрузка приостановлена';
}

function resumeUpload() {
    if (!upload) return;
    isPaused = false;
    speedTracker = { time: 0, bytes: 0, speed: 0 };
    showResume.value = false;
    showPause.value = true;
    status.value = 'Загрузка…';
    upload.start();
}

function cancelUpload() {
    if (!upload) return;
    upload.abort(true).catch(() => {});
    upload = null;
    isPaused = false;
    showProgress.value = false;
    showStart.value = true;
    showPause.value = false;
    showResume.value = false;
    showCancel.value = false;
    showReset.value = false;
    startDisabled.value = false;
    status.value = 'Загрузка отменена';
}

function resetUpload() {
    if (currentFile.value) {
        resetUiForNewFile(currentFile.value);
    }
}

onBeforeUnmount(() => {
    if (upload && !isPaused) {
        upload.abort();
    }
});
</script>

<template>
    <AppLayout title="Загрузка файла" subtitle="TUS · до 5 ГБ" max-width="max-w-2xl">
        <input ref="fileInput" type="file" class="hidden" @change="onFileChange">

        <div class="mb-6 space-y-5">
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
                    v-model="groupId"
                    v-model:selected-label="selectedGroupName"
                    fetch-url="/file-groups/search"
                    placeholder="Выберите группу"
                    empty-text="Группы не найдены"
                    @selected="applyGroupTags"
                />
                <p class="mt-1.5 text-xs text-slate-400">Необязательно. Теги группы подставятся в файл</p>
            </div>

            <TagInput
                id="file-tags"
                v-model="tags"
                :suggestions="availableTags"
                :allow-create="canCreateTags"
                hint="Кто с этими тегами увидит файл. Можно сузить или расширить теги группы"
            />
        </div>

        <div
            role="button"
            tabindex="0"
            aria-label="Выбрать файл или перетащить его сюда"
            class="group cursor-pointer rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-6 py-12 text-center transition hover:border-indigo-500 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-indigo-500 bg-indigo-50': isDragOver }"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @dragenter.prevent="isDragOver = true"
            @dragover.prevent="isDragOver = true"
            @dragleave.prevent="isDragOver = false"
            @dragend.prevent="isDragOver = false"
            @drop="onDrop"
        >
            <svg class="mx-auto mb-4 h-14 w-14 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <div class="text-lg font-semibold text-gray-700">Выберите файл</div>
            <div class="text-sm text-gray-400">или перетащите его сюда</div>
        </div>

        <div v-if="showFileInfo" class="mt-5 flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-4">
            <div class="min-w-0">
                <div class="truncate font-semibold text-gray-800">{{ currentFile?.name }}</div>
                <div class="text-sm text-gray-500">{{ fileMeta }}</div>
            </div>
            <button
                type="button"
                aria-label="Убрать файл"
                class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600"
                @click="removeFile"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div v-if="showProgress" class="mt-5">
            <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 transition-[width] duration-300"
                    :style="{ width: `${progressPercentage}%` }"
                />
            </div>
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="font-bold text-indigo-600">{{ progressPercentage }}%</span>
                <span class="text-gray-400">{{ progressBytes }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-xs text-gray-400">
                <span>{{ progressSpeed }}</span>
                <span>{{ progressEta }}</span>
            </div>
        </div>

        <div v-if="showError" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">Не удалось загрузить файл</p>
            <p class="mt-1 break-words text-red-600">{{ errorMessage }}</p>
        </div>

        <div v-if="showSuccess" class="mt-5 rounded-xl bg-green-50 p-4 text-sm text-green-700">
            <p class="font-semibold">Файл успешно загружен ✓</p>
            <p class="mt-1 break-all text-green-600">{{ successUrl }}</p>
        </div>

        <div v-if="showActions" class="mt-6 flex flex-wrap gap-3">
            <button
                v-if="showStart"
                type="button"
                :disabled="startDisabled"
                class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0"
                @click="startUpload"
            >
                Загрузить
            </button>
            <button
                v-if="showPause"
                type="button"
                class="rounded-xl bg-gray-200 px-6 py-3 font-semibold text-gray-700 transition hover:bg-gray-300"
                @click="pauseUpload"
            >
                Пауза
            </button>
            <button
                v-if="showResume"
                type="button"
                class="rounded-xl bg-indigo-100 px-6 py-3 font-semibold text-indigo-700 transition hover:bg-indigo-200"
                @click="resumeUpload"
            >
                Продолжить
            </button>
            <button
                v-if="showCancel"
                type="button"
                class="rounded-xl bg-red-50 px-6 py-3 font-semibold text-red-600 transition hover:bg-red-100"
                @click="cancelUpload"
            >
                Отменить
            </button>
            <button
                v-if="showReset"
                type="button"
                class="rounded-xl bg-gray-200 px-6 py-3 font-semibold text-gray-700 transition hover:bg-gray-300"
                @click="resetUpload"
            >
                Загрузить другой файл
            </button>
        </div>

        <p role="status" aria-live="polite" class="mt-4 text-sm text-gray-400">{{ status }}</p>

        <CreateSecretGroupModal
            v-model:open="groupModalOpen"
            :available-tags="availableTags"
            store-url="/file-groups"
            description="Группа поможет собрать файлы в один набор"
        />
    </AppLayout>
</template>
