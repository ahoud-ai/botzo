<template>
    <AppLayout>
        <UiPageHeader :title="$t('Language translations:') + ' ' + props.language.name" :subtitle="$t('Translate your language')">
            <template #actions>
                <Link href="/admin/languages" class="lgs-btn lgs-btn--ghost">{{ $t('Back') }}</Link>
                <button type="button" @click="isOpenModal = true" class="lgs-btn lgs-btn--solid">{{ $t('Bulk edit translations') }}</button>
            </template>
        </UiPageHeader>

        <!-- Table Component-->
        <LangTranslationsTable :rows="props.rows" :filters="props.filters" :language="props.language" class="mt-6" />

        <!-- Import Modal-->
        <Modal :label="$t('Bulk Edit')" :isOpen="isOpenModal" :closeBtn="true" @close="isOpenModal = false">
            <ol class="lgs-steps">
                <li>
                    <span class="lgs-step-text">{{ $t('Download .xlsx file with all translation strings.') }}</span>
                    <a class="lgs-step-link" :href="'/admin/languages/'+ props.language.code +'/export'">{{ $t('Click here to download') }}</a>
                </li>
                <li>
                    <span class="lgs-step-text">{{ $t('Edit the xlsx file with your own translations') }}</span>
                </li>
                <li>
                    <span class="lgs-step-text">{{ $t('Reupload the edited xlsx file below') }}</span>
                </li>
            </ol>

            <div class="lgs-dropzone" @dragover.prevent @drop="handleDrop">
                <input
                    type="file"
                    class="sr-only"
                    accept=".xlsx"
                    ref="fileInput"
                    id="file-upload"
                    @change="handleFileUpload($event)"
                />
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" class="lgs-dropzone-icon"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
                <label for="file-upload" class="lgs-dropzone-label">{{ $t('Click to upload a file') }}</label>
                <p class="lgs-dropzone-sub">{{ $t('Or drag and drop') }}</p>
                <p class="lgs-dropzone-hint">{{ $t('XLSX files only') }}</p>
            </div>

            <div v-if="uploads.length" class="lgs-uploads">
                <div v-for="upload in uploads" :key="upload.name" class="lgs-upload-row">
                    <div class="min-w-0 flex-1">
                        <p class="lgs-upload-name">{{ upload.name }}</p>
                        <p class="lgs-upload-status">{{ upload.progress !== 100 ? upload.progress + '%' : $t('Complete') }}</p>
                    </div>
                    <div v-if="upload.progress !== 100" class="lgs-upload-track">
                        <div class="lgs-upload-fill" :style="{ width: `${upload.progress}%` }"></div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="isOpenModal = false" class="lgs-btn lgs-btn--ghost">{{ $t('Close') }}</button>
            </div>
        </Modal>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "../Layout/App.vue";
    import { ref } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { Link, useForm } from "@inertiajs/vue3";
    import LangTranslationsTable from '@/Components/Tables/LangTranslationsTable.vue';
    import Modal from '@/Components/Modal.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';

    const props = defineProps({ language: Object, rows: Object, filters: String });
    const { t } = useI18n();
    const uploads = ref([]);
    const isOpenModal = ref(false);

    const handleFileUpload = (event) => {
        const files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            uploadFile(files[i]);
        }
    };

    const handleDrop = (event) => {
        event.preventDefault();
        const files = event.dataTransfer.files;
        for (let i = 0; i < files.length; i++) {
            uploadFile(files[i]);
        }
    };

    const uploadFile = (file) => {
        if (!['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(file.type)) {
            alert(t('please select a CSV or XLSX file'));
            return;
        }
        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const progress = Math.round((event.loaded / event.total) * 100);
                const fileIndex = uploads.value.findIndex((item) => item.name === file.name);
                uploads.value[fileIndex].progress = progress;
            }
        });

        xhr.open('POST', '/admin/languages/'+ props.language.code +'/import');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

        xhr.onload = () => {
            if (xhr.status === 200) {
                // Handle success
            } else {
                // Handle error
            }
        };

        xhr.send(formData);

        uploads.value.push({
            name: file.name,
            progress: 0,
        });
    };
</script>

<style scoped>
.lgs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.lgs-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.lgs-btn--solid:hover {
    filter: brightness(1.05);
}

.lgs-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.lgs-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.lgs-steps {
    list-style: decimal;
    margin: 0 0 1rem;
    padding-inline-start: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.lgs-step-link {
    display: block;
    margin-top: 0.15rem;
    font-weight: 600;
    color: var(--ui-secondary);
    text-decoration: underline;
}

.lgs-dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    border: 2px dashed var(--ui-border-strong);
    border-radius: 1rem;
    background: var(--ui-surface-soft);
    padding: 2rem 1rem;
    text-align: center;
}

.lgs-dropzone-icon {
    color: var(--ui-muted);
}

.lgs-dropzone-label {
    cursor: pointer;
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--ui-secondary);
}

.lgs-dropzone-sub {
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.lgs-dropzone-hint {
    font-size: 0.72rem;
    color: var(--ui-muted);
}

.lgs-uploads {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.lgs-upload-row {
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.6rem 0.85rem;
}

.lgs-upload-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ui-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lgs-upload-status {
    margin-top: 0.15rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.lgs-upload-track {
    margin-top: 0.4rem;
    height: 0.35rem;
    border-radius: 999px;
    background: var(--ui-border);
    overflow: hidden;
}

.lgs-upload-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--ui-secondary);
    transition: width 200ms ease;
}
</style>
