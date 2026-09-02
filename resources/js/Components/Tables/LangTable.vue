<script setup>
    import { computed, ref } from 'vue';
    import AlertModal from '@/Components/AlertModal.vue';
    import { router, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import Pagination from '@/Components/Pagination.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t } = useI18n();

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        defaultLanguage: {
            type: String,
            default: null,
        },
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const emit = defineEmits(['edit']);

    const form = useForm({ 'test': null });
    const search = ref('');
    const togglingId = ref(null);

    function edit(id) {
        emit('edit', id);
    }

    const deleteAction = (key) => {
        form.delete('/admin/languages/' + key);
    }

    const setDefaultAction = (code) => {
        router.post('/admin/languages/' + code + '/default', {}, { preserveScroll: true });
    }

    const isDefault = (item) => props.defaultLanguage === item.code;
    const isDeleteLocked = (item) => isDefault(item) || item.code === 'en';

    const toggleStatus = (item) => {
        if (isDefault(item) || togglingId.value === item.id) {
            return;
        }

        togglingId.value = item.id;
        router.put('/admin/languages/' + item.id, {
            name: item.name,
            code: item.code,
            is_rtl: item.is_rtl,
            status: item.status === 'active' ? 'inactive' : 'active',
        }, {
            preserveScroll: true,
            onFinish: () => {
                togglingId.value = null;
            },
        });
    }

    const capitalizeFirstLetter = (str) => {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
    };

    const FLAG_MAP = {
        en: '🇬🇧', ar: '🇸🇦', fr: '🇫🇷', es: '🇪🇸', de: '🇩🇪', it: '🇮🇹',
        pt: '🇵🇹', ru: '🇷🇺', tr: '🇹🇷', nl: '🇳🇱', pl: '🇵🇱', sv: '🇸🇪',
        zh: '🇨🇳', ja: '🇯🇵', ko: '🇰🇷', hi: '🇮🇳', ur: '🇵🇰', fa: '🇮🇷',
        he: '🇮🇱', id: '🇮🇩', ms: '🇲🇾', th: '🇹🇭', vi: '🇻🇳', el: '🇬🇷',
        cs: '🇨🇿', da: '🇩🇰', fi: '🇫🇮', no: '🇳🇴', ro: '🇷🇴', uk: '🇺🇦',
    };
    const flagFor = (code) => FLAG_MAP[String(code || '').toLowerCase().slice(0, 2)] ?? '🌐';

    const filteredRows = computed(() => {
        const term = search.value.trim().toLowerCase();
        if (!term) {
            return props.rows.data;
        }

        return props.rows.data.filter((item) => {
            return item.name.toLowerCase().includes(term) || item.code.toLowerCase().includes(term);
        });
    });
</script>
<template>
    <div class="lng-toolbar">
        <div class="ui-table-search lng-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input v-model="search" type="text" class="ui-table-search-input" :placeholder="$t('Search here')">
        </div>
        <span class="lng-count">{{ filteredRows.length }} {{ $t('Languages') }}</span>
    </div>

    <template v-if="filteredRows.length">
        <div class="lng-grid">
            <article v-for="item in filteredRows" :key="item.id" class="lng-card" :class="{ 'lng-card--inactive': item.status !== 'active' }">
                <div class="lng-card-top">
                    <span class="lng-flag">{{ flagFor(item.code) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="lng-name">{{ item.name }}</h3>
                            <span v-if="isDefault(item)" class="lng-badge-default">{{ $t('Default Language') }}</span>
                        </div>
                        <span class="lng-code">{{ item.code.toUpperCase() }}</span>
                    </div>
                    <Dropdown :align="'right'">
                        <button type="button" class="lng-kebab-btn" :aria-label="$t('Actions')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/></svg>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem v-if="!isDefault(item)" as="button" @click="setDefaultAction(item.code)">{{ $t('Set as default') }}</DropdownItem>
                                <DropdownItem :href="'/admin/languages/' + item.code + '/translations'">{{ $t('Translate') }}</DropdownItem>
                                <DropdownItem as="button" @click="edit(item.id)">{{ $t('Edit') }}</DropdownItem>
                                <DropdownItem v-if="!isDeleteLocked(item)" as="button" @click="openAlert(item.id)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </div>

                <div class="lng-meta-row">
                    <span class="lng-meta-chip">{{ item.is_rtl == 1 ? $t('RTL') : $t('LTR') }}</span>
                </div>

                <div class="lng-card-footer">
                    <div class="lng-toggle-row" :title="isDefault(item) ? $t('The default language must stay active') : ''">
                        <FormToggleSwitch
                            :modelValue="item.status === 'active'"
                            :disabled="isDefault(item) || togglingId === item.id"
                            @update:modelValue="toggleStatus(item)"
                        />
                        <span class="lng-toggle-label">{{ item.status === 'active' ? $t('Active') : $t('Inactive') }}</span>
                    </div>
                    <span v-if="isDeleteLocked(item)" class="lng-lock-note" :title="$t('The default language cannot be deleted')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v2"/></svg>
                    </span>
                </div>
            </article>
        </div>

        <Pagination class="lng-pagination" :pagination="rows.meta"/>
    </template>

    <UiEmptyState
        v-else
        :title="$t('No languages found')"
    >
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 5h7m0 0h4m-4 0v2m0 0c0 4-2.5 7-6 8.5M11 7c1.2 3.5 3.5 6 6.5 7.5M4 19l4-9l4 9m-6.5-3h5"/></svg>
        </template>
    </UiEmptyState>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.lng-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    margin-bottom: 1.25rem;
}

.lng-search {
    max-width: 22rem;
    flex: 1;
}

.lng-count {
    flex-shrink: 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.lng-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1.1rem;
    align-items: stretch;
}

@media (min-width: 768px) {
    .lng-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1280px) {
    .lng-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.lng-card {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    padding: 1.25rem;
    transition: border-color 200ms ease, transform 200ms ease, box-shadow 200ms ease, opacity 200ms ease;
}

.lng-card:hover {
    border-color: var(--ui-secondary);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-3px);
}

.lng-card--inactive {
    opacity: 0.72;
}

.lng-card-top {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.lng-flag {
    flex-shrink: 0;
    font-size: 1.8rem;
    line-height: 1;
}

.lng-name {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ui-text);
}

.lng-code {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--ui-muted);
}

.lng-badge-default {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    color: var(--ui-secondary);
}

.lng-kebab-btn {
    display: inline-flex;
    height: 2.35rem;
    width: 2.35rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.lng-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.lng-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.lng-meta-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.2rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.lng-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: auto;
    border-top: 1px solid var(--ui-border);
    padding-top: 0.85rem;
}

.lng-toggle-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.lng-toggle-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-text);
}

.lng-lock-note {
    display: inline-flex;
    color: var(--ui-muted);
}

.lng-pagination {
    margin-top: 1.25rem;
}
</style>
