<template>
    <div :class="rtlClass">
        <div class="relative flex min-h-[100svh] w-full bg-[linear-gradient(180deg,#f8fbff_0%,#eef4fb_100%)] tracking-[0.3px]">
            <div class="absolute end-5 top-5">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                >
                    <LogOut class="h-4 w-4" />
                    <span>{{ $t('Logout') }}</span>
                </Link>
            </div>

            <div class="flex min-h-[100svh] w-full items-center justify-center px-4 py-16">
                <div class="w-full max-w-[860px] rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="border-b border-slate-200 pb-6">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700">
                            <Building2 class="h-5 w-5" />
                        </div>
                        <h1 class="mt-4 text-2xl font-semibold text-slate-950">{{ $t('Select organization') }}</h1>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ $t('Continue with the workspace you want to manage now.') }}
                        </p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <button
                            v-for="item in props.organizations"
                            :key="item.organization.uuid"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-start transition hover:border-slate-300 hover:bg-slate-50"
                            @click="selectOrganization(item.organization.uuid)"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700">
                                    <Building2 class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-950">{{ item.organization.name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        <template v-if="item.organization.organization_type === 'branch'">
                                            {{ $t('Branch of') }}: {{ item.organization.parent_organization?.name ?? $t('Parent organization') }}
                                        </template>
                                        <template v-else>
                                            {{ $t('Open workspace') }}
                                        </template>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5">
                                            {{ item.organization.organization_type === 'branch' ? $t('Branch') : $t('Main workspace') }}
                                        </span>
                                        <span
                                            v-if="item.access?.source === 'inherited_parent_owner'"
                                            class="rounded-full bg-sky-50 px-2 py-0.5 text-sky-700"
                                        >
                                            {{ $t('Managed from parent') }}
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700"
                                        >
                                            {{ $t('Direct member') }}
                                        </span>
                                        <span
                                            v-if="item.access?.isCurrent"
                                            class="rounded-full bg-slate-900 px-2 py-0.5 text-white"
                                        >
                                            {{ $t('Current') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <component :is="isRtl ? ChevronLeft : ChevronRight" class="h-4 w-4 shrink-0 text-slate-400" />
                        </button>

                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                            :disabled="createActionDisabled"
                            @click="openOrganizationModal"
                        >
                            <Plus class="h-4 w-4" />
                            <span>{{ $t(createActionLabel) }}</span>
                        </button>
                        <div
                            v-if="createActionNotice"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
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
