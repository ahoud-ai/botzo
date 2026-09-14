<template>
    <div :class="rtlClass" class="orgsel-shell">
        <div class="orgsel-bg">
            <div class="orgsel-logout">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    type="button"
                    class="orgsel-btn orgsel-btn--ghost"
                >
                    <LogOut class="h-4 w-4" />
                    <span>{{ $t('Logout') }}</span>
                </Link>
            </div>

            <div class="orgsel-center">
                <div class="orgsel-card ui-fade-up">
                    <div class="orgsel-header">
                        <NavBrandMark variant="desktop" />
                        <h1 class="orgsel-title">{{ $t('Select organization') }}</h1>
                        <p class="orgsel-subtitle">
                            {{ $t('Continue with the workspace you want to manage now.') }}
                        </p>
                    </div>

                    <div class="orgsel-list">
                        <button
                            v-for="item in props.organizations"
                            :key="item.organization.uuid"
                            type="button"
                            class="orgsel-item"
                            :class="{ 'orgsel-item--current': item.access?.isCurrent }"
                            @click="selectOrganization(item.organization.uuid)"
                        >
                            <div class="orgsel-item-main">
                                <span class="orgsel-item-icon">
                                    <Building2 class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="orgsel-item-name">{{ item.organization.name }}</div>
                                    <div class="orgsel-item-meta">
                                        <template v-if="item.organization.organization_type === 'branch'">
                                            {{ $t('Branch of') }}: {{ item.organization.parent_organization?.name ?? $t('Parent organization') }}
                                        </template>
                                        <template v-else>
                                            {{ $t('Open workspace') }}
                                        </template>
                                    </div>
                                    <div class="orgsel-item-chips">
                                        <span class="orgsel-chip orgsel-chip--neutral">
                                            {{ item.organization.organization_type === 'branch' ? $t('Branch') : $t('Main workspace') }}
                                        </span>
                                        <span
                                            v-if="item.access?.source === 'inherited_parent_owner'"
                                            class="orgsel-chip ui-chip-info"
                                        >
                                            {{ $t('Managed from parent') }}
                                        </span>
                                        <span
                                            v-else
                                            class="orgsel-chip ui-chip-success"
                                        >
                                            {{ $t('Direct member') }}
                                        </span>
                                        <span
                                            v-if="item.access?.isCurrent"
                                            class="orgsel-chip orgsel-chip--current"
                                        >
                                            {{ $t('Current') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <component :is="isRtl ? ChevronLeft : ChevronRight" class="orgsel-item-chevron" />
                        </button>

                        <button
                            type="button"
                            class="orgsel-create"
                            :disabled="createActionDisabled"
                            @click="openOrganizationModal"
                        >
                            <Plus class="h-4 w-4" />
                            <span>{{ $t(createActionLabel) }}</span>
                        </button>
                        <div
                            v-if="createActionNotice"
                            class="orgsel-notice"
                        >
                            {{ $t(createActionNotice) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <OrganizationModal v-model:modelValue="isOpenModal" :type="props.branchCreationContext" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Building2, ChevronLeft, ChevronRight, LogOut, Plus } from 'lucide-vue-next';
import NavBrandMark from '@/Components/NavBrandMark.vue';
import OrganizationModal from '@/Components/OrganizationModal.vue';
import { useRtl } from '@/Composables/useRtl';

const { rtlClass, isRtl } = useRtl();

const props = defineProps({
    organizations: Object,
    branchCreationContext: {
        type: Object,
        default: null,
    },
});

const isOpenModal = ref(false);
const hasOrganizations = computed(() => Array.isArray(props.organizations) && props.organizations.length > 0);
const branchCreationBlocked = computed(() => Boolean(
    props.branchCreationContext
    && props.branchCreationContext.type === 'branch'
    && props.branchCreationContext.canCreateBranch === false,
));
const createActionDisabled = computed(() => branchCreationBlocked.value || (hasOrganizations.value && !props.branchCreationContext));
const createActionLabel = computed(() => {
    if (props.branchCreationContext) {
        return 'Add branch';
    }

    return hasOrganizations.value ? 'Select a workspace first' : 'Create workspace';
});
const branchCreationBlockingMessage = computed(() => (
    branchCreationBlocked.value
        ? (props.branchCreationContext?.blockingMessage || '')
        : ''
));
const createActionNotice = computed(() => {
    if (branchCreationBlockingMessage.value) {
        return branchCreationBlockingMessage.value;
    }

    if (hasOrganizations.value && !props.branchCreationContext) {
        return 'Select a workspace first, then add a branch from that workspace context.';
    }

    if (!hasOrganizations.value) {
        return 'Create your first workspace or wait for an invitation to continue.';
    }

    return '';
});

const form = useForm({
    uuid: null,
});

const openOrganizationModal = () => {
    if (createActionDisabled.value) {
        return;
    }

    isOpenModal.value = true;
};

const selectOrganization = (uuid) => {
    form.uuid = uuid;

    submitForm();
};

const submitForm = async () => {
    form.post('/select-organization', {
        preserveScroll: true,
    });
};
</script>

<style scoped>
.orgsel-shell {
    min-height: 100svh;
}

.orgsel-bg {
    position: relative;
    display: flex;
    min-height: 100svh;
    width: 100%;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--ui-surface) 94%, var(--ui-primary) 6%), var(--ui-surface) 55%);
}

.orgsel-logout {
    position: absolute;
    inset-inline-end: 1.25rem;
    top: 1.25rem;
    z-index: 1;
}

.orgsel-center {
    display: flex;
    min-height: 100svh;
    width: 100%;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
}

.orgsel-card {
    width: 100%;
    max-width: 860px;
    border-radius: 28px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-2);
    padding: 1.75rem;
}

@media (min-width: 768px) {
    .orgsel-card {
        padding: 2.25rem;
    }
}

.orgsel-header {
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 1.5rem;
}

.orgsel-title {
    margin-top: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ui-text);
}

.orgsel-subtitle {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: var(--ui-muted);
}

.orgsel-list {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.orgsel-item {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem 1.25rem;
    text-align: start;
    transition: border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.orgsel-item:hover {
    border-color: var(--ui-secondary);
    box-shadow: var(--ui-shadow-2);
    transform: translateY(-2px);
}

.orgsel-item--current {
    border-color: color-mix(in srgb, var(--ui-secondary) 45%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-secondary) 5%, var(--ui-surface));
}

.orgsel-item-main {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.85rem;
}

.orgsel-item-icon {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    height: 2.75rem;
    width: 2.75rem;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--ui-secondary) 14%, transparent);
    color: var(--ui-secondary);
}

.orgsel-item-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--ui-text);
}

.orgsel-item-meta {
    margin-top: 0.15rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.orgsel-item-chips {
    margin-top: 0.55rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
}

.orgsel-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
}

.orgsel-chip--neutral {
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    color: var(--ui-muted);
}

.orgsel-chip--current {
    background: var(--ui-secondary);
    color: #fff;
}

.orgsel-item-chevron {
    height: 1rem;
    width: 1rem;
    flex-shrink: 0;
    color: var(--ui-muted);
}

.orgsel-create {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 1.1rem;
    border: 1.5px dashed var(--ui-border-strong);
    background: var(--ui-surface-soft);
    padding: 1rem 1.25rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ui-text);
    transition: border-color 180ms ease, background-color 180ms ease;
}

.orgsel-create:hover:not(:disabled) {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 6%, var(--ui-surface-soft));
}

.orgsel-create:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.orgsel-notice {
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    color: color-mix(in srgb, var(--ui-warning) 80%, var(--ui-text));
}

.orgsel-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.85rem;
    padding: 0.55rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.orgsel-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
}

.orgsel-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
    color: var(--ui-text);
}
</style>
