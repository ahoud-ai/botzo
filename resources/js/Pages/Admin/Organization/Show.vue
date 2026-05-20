<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div v-if="!existingOrganization" class="hidden justify-between md:flex">
                <div>
                    <h1 class="mb-1 text-xl">{{ $t('Create organization') }}</h1>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Create organization') }}</span>
                    </p>
                </div>
                <Link href="/admin/organizations" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500">{{ $t('Back') }}</Link>
            </div>

            <template v-if="existingOrganization">
                <section class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
                    <div class="absolute inset-x-0 top-0 h-32 bg-[linear-gradient(135deg,rgba(99,102,241,0.10),rgba(14,165,233,0.08),transparent)]"></div>

                    <div class="relative flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 shadow-sm">
                                <div class="flex h-20 w-20 items-center justify-center rounded-[1.2rem] bg-slate-100 text-slate-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24"><path fill="currentColor" d="M17 3.722v5.497l2.864.716A1.5 1.5 0 0 1 21 11.39V19a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2v-7.69a1.5 1.5 0 0 1 .83-1.343L7 8.382V6.347a1.5 1.5 0 0 1 .973-1.405l7-2.625A1.5 1.5 0 0 1 17 3.722Zm-2 .721l-6 2.25V19h6V4.443Zm2 6.838V19h2v-7.22l-2-.5Zm-10-.663l-2 1V19h2v-8.382Z"/></svg>
                                </div>
                            </div>
                            <div class="min-w-0 space-y-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-2xl font-semibold text-slate-950">{{ props.organization.name }}</h1>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium" :class="isBranchOrganization ? 'bg-sky-100 text-sky-700' : 'bg-indigo-100 text-indigo-700'">
                                        {{ isBranchOrganization ? $t('Branch') : $t('Main organization') }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium" :class="subscriptionTone(props.profileSummary?.subscription?.status)">
                                        {{ props.profileSummary?.subscription?.status_label ?? $t('No active subscription') }}
                                    </span>
                                    <span v-if="isParentManaged" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">{{ $t('Managed from parent subscription') }}</span>
                                </div>

                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-slate-700">{{ props.profileSummary?.subscription?.plan_name ?? $t('Not set') }}</p>
                                    <p class="text-sm text-slate-500">{{ organizationSubtitle }}</p>
                                </div>

                                <div class="flex flex-wrap gap-2.5 text-xs text-slate-600">
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                        {{ $t('Billing owner') }}: <strong class="text-slate-900">{{ props.profileSummary?.billing_owner_name ?? $t('Not set') }}</strong>
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                        {{ $t('Owner') }}: <strong class="text-slate-900">{{ props.profileSummary?.owner_name ?? $t('Not set') }}</strong>
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                        {{ $t('Renewal date') }}: <strong class="text-slate-900">{{ props.profileSummary?.subscription?.valid_until ?? $t('Not set') }}</strong>
                                    </span>
                                    <span v-if="props.profileSummary?.parent_organization_name" class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                        {{ $t('Parent organization') }}: <strong class="text-slate-900">{{ props.profileSummary.parent_organization_name }}</strong>
                                    </span>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div v-for="item in headerStats" :key="item.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                            <component :is="item.icon" class="h-3.5 w-3.5 text-slate-400" />
                                            {{ item.label }}
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Link href="/admin/organizations" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <ArrowLeft class="h-4 w-4" />
                                {{ $t('Back') }}
                            </Link>
                            <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="changeTab('edit')">
                                <PencilLine class="h-4 w-4" />
                                {{ $t('Edit') }}
                            </button>
                            <button v-if="!isParentManaged" type="button" @click="toggleFormModal()" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                                <CreditCard class="h-4 w-4" />
                                {{ $t('Create transaction') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="organizationNotice" class="relative mt-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                                <InfoIcon class="h-4 w-4" />
                            </span>
                            <p class="leading-6">{{ organizationNotice }}</p>
                        </div>
                    </div>
                </section>

                <section class="mt-6 overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                    <div class="border-b border-slate-200 px-4 py-4 md:px-6">
                        <div class="flex flex-wrap gap-2">
                            <button v-for="option in tabs" :key="option.key" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition" :class="tab === option.key ? 'bg-slate-950 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="changeTab(option.key)">
                                <component :is="option.icon" class="h-4 w-4" />
                                {{ option.label }}
                                <span v-if="option.count !== null" class="rounded-full px-2 py-0.5 text-[11px]" :class="tab === option.key ? 'bg-white/15 text-white' : 'bg-white text-slate-500'">{{ option.count }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 md:p-6">
                        <div v-show="tab === 'overview'" class="space-y-6">
                            <div class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
                                <UiSectionCard :title="$t('Summary')">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div v-for="row in overviewRows" :key="row.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                            <p class="text-xs font-medium text-slate-500">{{ row.label }}</p>
                                            <p class="mt-1 break-words text-sm font-semibold text-slate-900">{{ row.value }}</p>
                                        </div>
                                    </div>
                                    <div v-if="overviewChips.length" class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                                        <span v-for="chip in overviewChips" :key="chip.label" class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                            {{ chip.label }}: <strong class="text-slate-900">{{ chip.value }}</strong>
                                        </span>
                                    </div>
                                    <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4">
                                        <p class="text-xs font-medium text-slate-500">{{ $t('Address details') }}</p>
                                        <div v-if="addressLines.length" class="mt-2 space-y-1 text-sm text-slate-700">
                                            <p v-for="line in addressLines" :key="line">{{ line }}</p>
                                        </div>
                                        <p v-else class="mt-2 text-sm text-slate-500">{{ $t('Not set') }}</p>
                                    </div>
                                </UiSectionCard>

                                <UiSectionCard :title="$t('Billing')">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div v-for="row in commercialRows" :key="row.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                            <p class="text-xs font-medium text-slate-500">{{ row.label }}</p>
                                            <p class="mt-1 break-words text-sm font-semibold text-slate-900">{{ row.value }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 grid gap-3">
                                        <div v-for="item in latestBillingItems" :key="item.label" class="rounded-2xl border border-slate-200 px-4 py-3">
                                            <p class="text-xs font-medium text-slate-500">{{ item.label }}</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</p>
                                            <p v-if="item.meta" class="mt-1 text-xs text-slate-500">{{ item.meta }}</p>
                                        </div>
                                        <UiEmptyState v-if="!latestBillingItems.length" :title="$t('No billing yet')">
                                            <template #icon>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5 3h14a2 2 0 0 1 2 2v14l-4-3l-4 3l-4-3l-4 3V5a2 2 0 0 1 2-2m0 2v10.13l2-1.5l4 3l4-3l2 1.5V5zm3 2h8v2H8zm0 4h8v2H8z"/></svg>
                                            </template>
                                        </UiEmptyState>
                                    </div>
                                </UiSectionCard>
                            </div>
                        </div>

                        <div v-show="tab === 'branches'">
                            <UiSectionCard :title="$t('Branch workspaces')">
                                <div v-if="props.branches?.length" class="grid gap-4 lg:grid-cols-2">
                                    <article v-for="branch in props.branches" :key="branch.uuid" class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-base font-semibold text-slate-900">{{ branch.name }}</h3>
                                                    <span v-if="branch.is_current" class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700">{{ $t('Current workspace') }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-500">{{ branch.owner_name ?? $t('Not set') }}</p>
                                            </div>
                                            <Link :href="'/admin/organizations/' + branch.uuid" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100">{{ $t('Open workspace') }}</Link>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                                {{ $t('Seats') }}: <strong class="text-slate-900">{{ branch.teams_count }}</strong>
                                            </span>
                                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                                {{ branch.subscription_display?.plan_name ?? $t('Not set') }}
                                            </span>
                                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                                {{ branch.subscription_display?.valid_until ?? $t('Not set') }}
                                            </span>
                                        </div>
                                    </article>
                                </div>
                                <UiEmptyState v-else :title="$t('No branches yet')">
                                    <template #icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="currentColor" d="M5 4a2 2 0 0 0-2 2v3h2V6h4V4zm14 0h-4v2h4v3h2V6a2 2 0 0 0-2-2M5 18v-3H3v3a2 2 0 0 0 2 2h4v-2zm16-3h-2v3h-4v2h4a2 2 0 0 0 2-2zm-9-9a5 5 0 1 1 0 10a5 5 0 0 1 0-10"/></svg>
                                    </template>
                                </UiEmptyState>
                            </UiSectionCard>
                        </div>

                        <div v-show="tab === 'usage'">
                            <UiSectionCard :title="$t('Usage and limits')">
                                <div v-if="usageNotices.length" class="mb-4 space-y-3">
                                    <div
                                        v-for="notice in usageNotices"
                                        :key="notice.key"
                                        class="rounded-[0.95rem] border px-4 py-4 text-sm"
                                        :class="usageNoticeClasses(notice)"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="usageNoticeIconClasses(notice)" v-html="usageNoticeIcon(notice)"></span>
                                            <div>
                                                <h3 class="font-semibold">{{ notice.title }}</h3>
                                                <p class="mt-1 leading-6">{{ notice.message }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="usageMetrics.length" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                    <article v-for="metric in usageMetrics" :key="metric.key" class="rounded-[1rem] border p-3.5 transition-all duration-200" :class="usageMetricCardClasses(metric)">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex items-start gap-3">
                                                <span class="ui-kpi-icon ui-kpi-icon--sm shrink-0 bg-white text-primary shadow-sm shadow-slate-200/70">
                                                    <span class="[&_svg]:block" v-html="metric.icon"></span>
                                                </span>
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">{{ metric.label }}</p>
                                                    <p v-if="metric.helper" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ metric.helper }}</p>
                                                </div>
                                            </div>
                                            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="usageMetricBadgeClasses(metric)">{{ metric.used }} / {{ metric.limit < 0 ? $t('Unlimited') : metric.limit }}</span>
                                        </div>
                                        <div v-if="metric.limit >= 0" class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full transition-all duration-500" :class="usageMetricProgressClasses(metric)" :style="{ width: usagePercentage(metric) + '%' }"></div>
                                        </div>
                                    </article>
                                </div>
                                <UiEmptyState v-else :title="$t('No usage yet')">
                                    <template #icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="currentColor" d="M5 5h2v14H5zm6 6h2v8h-2zm6-4h2v12h-2z"/></svg>
                                    </template>
                                </UiEmptyState>
                            </UiSectionCard>
                        </div>

                        <div v-show="tab === 'team'">
                            <UiSectionCard :title="$t('Team')">
                                <div v-if="teamNotes.length" class="mb-4 space-y-3">
                                    <div
                                        v-for="(note, index) in teamNotes"
                                        :key="`${note.level}-${index}`"
                                        class="rounded-[1rem] border px-4 py-3 text-sm"
                                        :class="note.level === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-sky-200 bg-sky-50 text-sky-900'"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-2xl" :class="note.level === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'">
                                                <InfoIcon class="h-4 w-4" />
                                            </span>
                                            <p class="leading-6">{{ note.message }}</p>
                                        </div>
                                    </div>
                                </div>
                                <UserTable :rows="props.users" :filters="props.filters" :type="'user'" :showRole="true" :showDeleteBtn="false"/>
                            </UiSectionCard>
                        </div>

                        <div v-show="tab === 'billing'" class="space-y-6">
                            <UiSectionCard :title="$t('Billing')">
                                <div class="rounded-[1rem] border px-4 py-4 text-sm leading-7" :class="isParentManaged ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-sky-200 bg-sky-50 text-sky-900'">
                                    <span v-if="isParentManaged">
                                        {{ $t('Billing transactions for branches are managed from the parent organization.') }}
                                    </span>
                                    <span v-else>
                                        {{ $t('Invoices and transactions stay here.') }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <UiStatCard v-for="item in billingKpis" :key="item.title" class="ui-kpi--compact" :title="item.title" :value="item.value">
                                        <template #icon>
                                            <span class="[&_svg]:block" v-html="item.icon"></span>
                                        </template>
                                    </UiStatCard>
                                </div>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div v-for="row in billingWorkspaceRows" :key="row.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-medium text-slate-500">{{ row.label }}</p>
                                        <p class="mt-1 break-words text-sm font-semibold text-slate-900">{{ row.value }}</p>
                                    </div>
                                </div>
                            </UiSectionCard>

                            <UiSectionCard :title="$t('Invoices')">
                                <BillingInvoiceTable
                                    :rows="props.invoices"
                                    :view-base-path="`/admin/organizations/${props.organization.uuid}/invoices`"
                                    :print-base-path="`/admin/organizations/${props.organization.uuid}/invoices`"
                                    :download-base-path="`/admin/organizations/${props.organization.uuid}/invoices`"
                                />
                            </UiSectionCard>

                            <UiSectionCard :title="$t('Billing activity')">
                                <BillingTable :rows="props.billingActivity" :filters="props.filters" :uuid="props.organization.uuid"/>
                            </UiSectionCard>
                        </div>

                        <div v-show="tab === 'edit'">
                            <form @submit.prevent="submitForm()" class="space-y-6">
                                <UiSectionCard :title="$t('Details')">
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                                        <FormInput v-model="form.name" :name="$t('Name')" :error="form.errors.name" :type="'text'" :class="'xl:col-span-6 sm:col-span-2'"/>
                                        <FormSelect v-if="form.organization_type !== 'branch'" v-model="form.plan" :name="$t('Subscription plan')" :error="form.errors.plan" :options="roleOptions()" :type="'text'" :class="'xl:col-span-3 sm:col-span-1'"/>
                                        <FormSelect v-model="form.organization_type" :name="$t('Organization type')" :error="form.errors.organization_type" :options="organizationTypeOptions" :disabled="existingOrganization" :type="'text'" :class="'xl:col-span-3 sm:col-span-1'"/>
                                        <FormSelect v-if="form.organization_type === 'branch'" v-model="form.parent_organization_uuid" :name="$t('Parent organization')" :error="form.errors.parent_organization_uuid" :options="parentOrganizationOptions()" :type="'text'" :class="'xl:col-span-6 sm:col-span-2'"/>
                                        <div v-if="form.organization_type === 'branch'" class="xl:col-span-6 sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                            {{ $t('Branches inherit subscription, features, and limits from the selected parent organization.') }}
                                        </div>
                                        <div v-if="existingOrganization" class="xl:col-span-6 sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                            {{ $t('Changing organization type directly is disabled to protect billing ownership and workspace hierarchy.') }}
                                        </div>
                                    </div>
                                </UiSectionCard>

                                <UiSectionCard :title="$t('Address details')">
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                                        <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" :type="'text'" :class="'xl:col-span-6 sm:col-span-2'"/>
                                        <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" :type="'text'" :class="'xl:col-span-2 sm:col-span-1'"/>
                                        <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" :type="'text'" :class="'xl:col-span-2 sm:col-span-1'"/>
                                        <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" :type="'text'" :class="'xl:col-span-2 sm:col-span-1'"/>
                                        <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" :type="'text'" :class="'xl:col-span-3 sm:col-span-1'"/>
                                    </div>
                                </UiSectionCard>

                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                                        <PencilLine class="h-4 w-4" />
                                        {{ $t('Save') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </template>

            <template v-else>
                <form @submit.prevent="submitForm()" class="space-y-6">
                    <UiSectionCard :title="$t('Organization details')" :subtitle="$t('Create organization')">
                        <div class="border-b border-slate-100 py-5 sm:flex">
                            <div class="mb-1 hidden sm:block sm:w-[40%]"><h3 class="text-sm tracking-[0px]">{{ $t('Organization details') }}</h3></div>
                            <div class="sm:flex sm:w-[60%] gap-x-6">
                                <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                                    <FormInput v-model="form.name" :name="$t('Name')" :error="form.errors.name" :type="'text'" :class="'sm:col-span-6'"/>
                                    <FormSelect v-if="form.organization_type !== 'branch'" v-model="form.plan" :name="$t('Subscription plan')" :error="form.errors.plan" :options="roleOptions()" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormSelect v-model="form.organization_type" :name="$t('Organization type')" :error="form.errors.organization_type" :options="organizationTypeOptions" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormSelect v-if="form.organization_type === 'branch'" v-model="form.parent_organization_uuid" :name="$t('Parent organization')" :error="form.errors.parent_organization_uuid" :options="parentOrganizationOptions()" :type="'text'" :class="'sm:col-span-6'"/>
                                    <div v-if="form.organization_type === 'branch'" class="sm:col-span-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $t('Branches inherit subscription, features, and limits from the selected parent organization.') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-slate-100 py-5 sm:flex">
                            <div class="mb-1 hidden sm:block sm:w-[40%]">
                                <h3 class="text-sm tracking-[0px]">{{ $t('User details') }}</h3>
                                <p class="text-sm text-gray-500">{{ $t('Enter the details of the main administrative user of this organization') }}</p>
                            </div>
                            <div class="sm:w-[60%]">
                                <div class="mb-4 flex justify-between gap-x-2 rounded-lg bg-primary p-1 text-white sm:w-[80%]">
                                    <button type="button" class="w-[50%] rounded-lg px-1 py-2 text-sm" :class="{ 'bg-white text-black': form.create_user === 1 }" @click="switchUserType(1)">{{ $t('Add user') }}</button>
                                    <button type="button" class="w-[50%] rounded-lg px-1 py-2 text-sm" :class="{ 'bg-white text-black': form.create_user === 0 }" @click="switchUserType(0)">{{ $t('Select existing user') }}</button>
                                </div>
                                <div v-if="form.create_user === 1" class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                                    <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormPhoneInput v-model="form.phone" :allowed-countries="allowedPhoneCountries" :name="$t('Phone')" :error="form.errors.phone" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" :type="'password'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" :type="'password'" :class="'sm:col-span-3'"/>
                                </div>
                                <div v-else class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                                    <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'sm:col-span-6'"/>
                                </div>
                            </div>
                        </div>

                        <div class="py-5 sm:flex">
                            <div class="mb-1 hidden sm:block w-[40%]"><h3 class="text-sm tracking-[0px]">{{ $t('Address details') }}</h3></div>
                            <div class="sm:flex sm:w-[60%] gap-x-6">
                                <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                                    <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" :type="'text'" :class="'sm:col-span-6'"/>
                                    <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" :type="'text'" :class="'sm:col-span-3'"/>
                                    <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" :type="'text'" :class="'sm:col-span-3'"/>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4"><button type="submit" class="flex items-center gap-x-4 rounded-md bg-black px-3 py-2 text-sm text-white shadow-sm hover:bg-slate-600">{{ $t('Save') }}</button></div>
                    </UiSectionCard>
                </form>
            </template>
        </div>
    </AppLayout>

    <Modal v-if="!isParentManaged" :label="$t('Create transaction')" :isOpen="isOpenFormModal" @close="isOpenFormModal = false">
        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <form @submit.prevent="submitForm1()">
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:col-span-4 sm:grid-cols-6">
                    <FormSelect v-model="form1.type" :name="$t('Transaction type')" :error="form1.errors.type" :options="typeOptions" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form1.amount" :name="$t('Amount')" :error="form1.errors.amount" :type="'number'" :class="'sm:col-span-3'"/>
                    <FormSelect v-if="form1.type === 'payment'" v-model="form1.method" :name="$t('Payment method')" :error="form1.errors.method" :options="paymentOptions" :class="'sm:col-span-6'"/>
                    <FormInput v-else v-model="form1.description" :name="$t('Description')" :error="form1.errors.description" :type="'text'" :class="'sm:col-span-6'"/>
                </div>
                <div class="mt-6 rounded-lg bg-red-800 px-2 py-1"><p class="flex items-center gap-x-2 text-[12px] text-white"><svg class="text-white" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="currentColor" fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5m0 9a1 1 0 1 0 0-2a1 1 0 0 0 0 2" clip-rule="evenodd"/></svg><span>{{ $t('You can\'t undo this transaction once you save it') }}</span></p></div>
                <div class="mt-6 flex">
                    <button type="button" @click="toggleFormModal()" class="me-4 inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200">{{ $t('Cancel') }}</button>
                    <button type="submit" :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white', { 'opacity-50': isLoading }]" :disabled="isLoading">
                        <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
    import AppLayout from './../Layout/App.vue';
    import { computed, ref, watch } from 'vue';
    import { Link, useForm, usePage } from '@inertiajs/vue3';
    import FormInput from '@/Components/FormInput.vue';
    import FormPhoneInput from '@/Components/FormPhoneInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import BillingTable from '@/Components/Tables/BillingTable.vue';
    import BillingInvoiceTable from '@/Components/Tables/BillingInvoiceTable.vue';
    import Modal from '@/Components/Modal.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import UiStatCard from '@/Components/UI/UiStatCard.vue';
    import UserTable from '@/Components/Tables/UserTable.vue';
    import { ArrowLeft, BarChart3, Building2, CreditCard, GitBranch, Info as InfoIcon, Landmark, PencilLine, Users2 } from 'lucide-vue-next';
    import { useI18n } from 'vue-i18n';

    const { t } = useI18n();
    const props = defineProps({
        showAddBtn: { type: Boolean, default: true },
        title: String,
        organization: Object,
        users: Object,
        invoices: Object,
        billingActivity: Object,
        plans: { type: Array, default: () => [] },
        parentOrganizations: { type: Array, default: () => [] },
        profileSummary: { type: Object, default: null },
        teamSummary: { type: Object, default: null },
        usageSummary: { type: Object, default: null },
        billingSummary: { type: Object, default: null },
        branches: { type: Array, default: () => [] },
        filters: Object,
        mode: String,
    });
    const allowedPhoneCountries = Array.isArray(usePage().props.phoneCountries) ? usePage().props.phoneCountries : [];
    const existingOrganization = computed(() => Boolean(props.organization));
    const isParentManaged = computed(() => props.organization?.subscription_managed_by_parent === true);
    const isBranchOrganization = computed(() => props.organization?.organization_type === 'branch');
    const allowedTabs = ['overview', 'branches', 'usage', 'team', 'billing', 'edit'];
    const tab = ref(props.organization && allowedTabs.includes(props.filters?.tab) ? props.filters.tab : (props.organization ? 'overview' : 'organization'));
    const isOpenFormModal = ref(false);
    const isLoading = ref(false);
    const iconMap = {
        plan: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 2h10a2 2 0 0 1 2 2v16l-7-3l-7 3V4a2 2 0 0 1 2-2m0 2v12.97l5-2.14l5 2.14V4zm5 2a3 3 0 1 1 0 6a3 3 0 0 1 0-6"/></svg>',
        renewal: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 15H5V10h14zm-6-7h5v5h-5z"/></svg>',
        workspaces: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M10 3H6a2 2 0 0 0-2 2v4h2V5h4zm8 0h-4v2h4v4h2V5a2 2 0 0 0-2-2M6 15H4v4a2 2 0 0 0 2 2h4v-2H6zm12 4h-4v2h4a2 2 0 0 0 2-2v-4h-2zm-6-8a4 4 0 1 0 0 .001z"/></svg>',
        team: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M16 11c1.66 0 2.99-1.57 2.99-3.5S17.66 4 16 4s-3 1.57-3 3.5s1.34 3.5 3 3.5m-8 0c1.66 0 2.99-1.57 2.99-3.5S9.66 4 8 4S5 5.57 5 7.5S6.34 11 8 11m0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.9 1.97 3.45V20h6v-3.5c0-2.33-4.67-3.5-7-3.5"/></svg>',
        balance: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2h-2V5H6v14h12v-2h2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm13 4a3 3 0 1 1 0 6h-5V9z"/></svg>',
        transactions: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M13 3l5 5l-5 5V9H6V7h7zm-2 18l-5-5l5-5v4h7v2h-7z"/></svg>',
        invoices: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h10a2 2 0 0 1 2 2v14l-2-1l-2 1l-2-1l-2 1l-2-1l-2 1V5a2 2 0 0 1 2-2m0 2v10.76l.4-.2l1.6-.8l2 1l2-1l2 1l1.6-.8l.4.2V5zm2 2h6v2H9zm0 4h6v2H9z"/></svg>',
        payments: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M21 6H3V4h18zm0 4H3v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zm-4 5h-4v-2h4z"/></svg>',
        branches: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 7h10v4h2V7a2 2 0 0 0-2-2h-4V2h-2v3H7a2 2 0 0 0-2 2v4h2zm-2 6v4a2 2 0 0 0 2 2h4v3h2v-3h4a2 2 0 0 0 2-2v-4h-2v4H7v-4z"/></svg>',
        users: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a4 4 0 1 0-4-4a4 4 0 0 0 4 4m0 2c-4.33 0-8 2.17-8 5v1h16v-1c0-2.83-3.67-5-8-5"/></svg>',
        contacts: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5a2 2 0 0 0-2 2v14l4-3h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2m-7 3a3 3 0 1 1-3 3a3 3 0 0 1 3-3m4 8H8v-1c0-1.33 2.67-2 4-2s4 .67 4 2z"/></svg>',
        campaigns: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="m3 11l18-5v2l-8 2.22V18l-4-2v-4.67L3 13z"/></svg>',
        messages: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v14l-4-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2m2 5h12V7H6zm0 4h8v-2H6z"/></svg>',
        canned_replies: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16v12H5.17L4 17.17zm2 2v6h10V6zm11 13H7v-2h10z"/></svg>',
        ai_text: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2m-1 15H9v-2h2zm4-4H9V7h6z"/></svg>',
        ai_audio: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M14 3.23v17.54a1 1 0 0 1-1.64.77L7 17H3a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1h4l5.36-4.54A1 1 0 0 1 14 3.23m3.54 4.05l1.42-1.42A8 8 0 0 1 21 12a8 8 0 0 1-2.04 5.14l-1.42-1.42A6 6 0 0 0 19 12a6 6 0 0 0-1.46-4.72"/></svg>',
        ai_system_key: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 14a3 3 0 1 1 2.83-4H20v2h-2v2h-2v-2h-2v-2H9.83A3 3 0 0 1 7 14m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2m10 10a3 3 0 0 1-2.83-2H4v-2h10.17A3 3 0 1 1 17 22m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2"/></svg>',
        active_flows: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3a4 4 0 0 1 4 4c0 .73-.2 1.41-.55 2H13.5A3.5 3.5 0 1 1 17 12.5V14a4 4 0 1 1-2 0v-1.5A3.5 3.5 0 0 1 11.5 9H8.45C8.8 9.59 9 10.27 9 11a4 4 0 1 1-2-3.45A4 4 0 0 1 7 3m0 2a2 2 0 1 0 2 2a2 2 0 0 0-2-2m8.5 5a1.5 1.5 0 1 0 1.5 1.5A1.5 1.5 0 0 0 15.5 10M5 15a2 2 0 1 0 2 2a2 2 0 0 0-2-2m10 2a2 2 0 1 0 2 2a2 2 0 0 0-2-2"/></svg>',
        flow_runs: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M11 2v2.07A8.001 8.001 0 0 0 4.07 11H2a10 10 0 0 1 9-8.93M13 2a10 10 0 0 1 9 8.93h-2.07A8.001 8.001 0 0 0 13 4.07zM4.07 13A8.001 8.001 0 0 0 11 19.93V22a10 10 0 0 1-8.93-9zm15.86 0H22a10 10 0 0 1-9 9v-2.07A8.001 8.001 0 0 0 19.93 13M12 8l4 4l-4 4l-1.41-1.41L12.17 13H8v-2h4.17l-1.58-1.59z"/></svg>',
        default: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M11 17h2v-6h-2zm0-8h2V7h-2zm1 13C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10s-4.48 10-10 10"/></svg>',
    };
    const iconSvg = (name) => iconMap[name] ?? iconMap.default;
    const teamMemberCount = computed(() => props.teamSummary?.members_count ?? props.profileSummary?.team_members_count ?? 0);
    const teamNotes = computed(() => Array.isArray(props.teamSummary?.notes) ? props.teamSummary.notes : []);
    const tabs = computed(() => ([
        { key: 'overview', label: t('Overview'), icon: Building2, count: null },
        { key: 'branches', label: t('Branch workspaces'), icon: GitBranch, count: props.profileSummary?.branches_count ?? 0 },
        { key: 'usage', label: t('Usage and limits'), icon: BarChart3, count: null },
        { key: 'team', label: t('Team'), icon: Users2, count: teamMemberCount.value },
        { key: 'billing', label: t('Billing'), icon: CreditCard, count: null },
        { key: 'edit', label: t('Edit'), icon: PencilLine, count: null },
    ]));
    const getAddressDetail = (value, key) => value ? (JSON.parse(value)?.[key] ?? null) : null;
    const form = useForm({
        name: props.organization?.name,
        plan: props.organization?.effective_subscription?.plan?.uuid ?? props.organization?.subscription?.plan?.uuid,
        organization_type: props.organization?.organization_type ?? 'main',
        parent_organization_uuid: props.organization?.parent_organization?.uuid ?? null,
        create_user: 1,
        first_name: null,
        last_name: null,
        email: null,
        phone: null,
        password: null,
        password_confirmation: null,
        street: getAddressDetail(props.organization?.address, 'street'),
        city: getAddressDetail(props.organization?.address, 'city'),
        state: getAddressDetail(props.organization?.address, 'state'),
        zip: getAddressDetail(props.organization?.address, 'zip'),
        country: getAddressDetail(props.organization?.address, 'country'),
    });
    const typeOptions = ref([{ value: 'credit', label: t('Credit') }, { value: 'debit', label: t('Debit') }, { value: 'payment', label: t('Payment') }]);
    const paymentOptions = ref([{ value: 'manual', label: t('Manual') }, { value: 'bank', label: t('Bank') }]);
    const organizationTypeOptions = ref([{ value: 'main', label: t('Main organization') }, { value: 'branch', label: t('Branch') }]);
    const form1 = useForm({ uuid: props.organization?.uuid, type: null, amount: null, method: null, description: null });
    const roleOptions = () => props.plans.map((option) => ({ value: option.uuid, label: `${option.name} (${option.period === 'monthly' ? t('Monthly') : t('Yearly')})` }));
    const parentOrganizationOptions = () => props.parentOrganizations.map((option) => ({ value: option.uuid, label: option.name, disableTranslation: true }));
    const organizationSubtitle = computed(() => {
        const values = Array.from(new Set([
            props.profileSummary?.owner_name,
            props.profileSummary?.billing_owner_name,
        ].filter((value) => String(value ?? '').trim() !== '')));

        return values.length ? values.join(' • ') : t('Not set');
    });
    const organizationNotice = computed(() => {
        if (isParentManaged.value) {
            return t('Billing transactions for branches are managed from the parent organization.');
        }

        if (isBranchOrganization.value) {
            return t('Branches inherit subscription, features, and limits from the selected parent organization.');
        }

        return null;
    });
    const headerStats = computed(() => ([
        { label: t('Family workspaces'), value: props.profileSummary?.family_workspaces_count ?? 1, icon: Building2 },
        { label: t('Team members'), value: teamMemberCount.value, icon: Users2 },
        { label: t('Branches'), value: props.profileSummary?.branches_count ?? 0, icon: GitBranch },
        { label: t('Account balance'), value: props.billingSummary?.account_balance ?? t('Not set'), icon: Landmark },
    ]));
    const overviewRows = computed(() => ([
        { label: t('Organization type'), value: props.profileSummary?.organization_type_label ?? t('Not set') },
        { label: t('Owner'), value: props.profileSummary?.owner_name ?? t('Not set') },
        { label: t('Billing owner'), value: props.profileSummary?.billing_owner_name ?? t('Not set') },
        { label: t('Parent organization'), value: props.profileSummary?.parent_organization_name ?? t('Not linked') },
        { label: t('Updated at'), value: props.profileSummary?.updated_at ?? t('Not set') },
    ]));
    const overviewChips = computed(() => ([
        { label: t('Branches'), value: props.profileSummary?.branches_count ?? 0 },
        { label: t('Family workspaces'), value: props.profileSummary?.family_workspaces_count ?? 1 },
        { label: t('Created at'), value: props.profileSummary?.created_at ?? t('Not set') },
    ]));
    const commercialRows = computed(() => ([
        { label: t('Subscription plan'), value: props.profileSummary?.subscription?.plan_name ?? t('Not set') },
        { label: t('Status'), value: props.profileSummary?.subscription?.status_label ?? t('No active subscription') },
        { label: t('Renewal date'), value: props.profileSummary?.subscription?.valid_until ?? t('Not set') },
        { label: t('Billing'), value: isParentManaged.value ? t('Managed from parent subscription') : t('Billing workspace') },
    ]));
    const billingKpis = computed(() => ([
        { title: t('Account balance'), value: props.billingSummary?.account_balance ?? t('Not set'), icon: iconSvg('balance') },
        { title: t('Transactions'), value: props.billingSummary?.transactions_count ?? 0, icon: iconSvg('transactions') },
        { title: t('Invoices'), value: props.billingSummary?.invoices_count ?? 0, icon: iconSvg('invoices') },
        { title: t('Payments'), value: props.billingSummary?.payments_count ?? 0, icon: iconSvg('payments') },
    ]));
    const latestBillingItems = computed(() => {
        const items = [];

        if (props.billingSummary?.latest_invoice) {
            items.push({
                label: t('Latest invoice'),
                value: props.billingSummary.latest_invoice.total,
                meta: [props.billingSummary.latest_invoice.plan_name, props.billingSummary.latest_invoice.created_at].filter(Boolean).join(' • '),
            });
        }

        if (props.billingSummary?.latest_payment) {
            items.push({
                label: t('Latest payment'),
                value: props.billingSummary.latest_payment.amount,
                meta: [props.billingSummary.latest_payment.method_label, props.billingSummary.latest_payment.created_at].filter(Boolean).join(' • '),
            });
        }

        if (props.billingSummary?.latest_transaction) {
            items.push({
                label: t('Latest transaction'),
                value: props.billingSummary.latest_transaction.amount,
                meta: [props.billingSummary.latest_transaction.description, props.billingSummary.latest_transaction.created_at].filter(Boolean).join(' • '),
            });
        }

        return items;
    });
    const billingWorkspaceRows = computed(() => ([
        { label: t('Billing owner'), value: props.profileSummary?.billing_owner_name ?? t('Not set') },
        { label: t('Subscription plan'), value: props.profileSummary?.subscription?.plan_name ?? t('Not set') },
        { label: t('Renewal date'), value: props.profileSummary?.subscription?.valid_until ?? t('Not set') },
        { label: t('Organization type'), value: props.profileSummary?.organization_type_label ?? t('Not set') },
    ]));
    const usageMetrics = computed(() => (props.usageSummary?.metrics ?? []).map((metric) => ({
        ...metric,
        icon: iconSvg(metric.key),
    })));
    const usageNotices = computed(() => props.usageSummary?.notices ?? []);
    const addressLines = computed(() => {
        const address = props.profileSummary?.address ?? {};
        return [address.street, address.city, address.state, address.zip, address.country].filter(Boolean);
    });
    const changeTab = (value) => {
        tab.value = value;

        if (!props.organization || typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (value === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', value);
        }

        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    };
    const switchUserType = (value) => {
        form.create_user = value;
        if (value === 0) {
            form.first_name = null; form.last_name = null; form.email = null; form.phone = null; form.password = null; form.password_confirmation = null;
        } else {
            form.email = null;
        }
    };
    watch(() => form.organization_type, (value) => {
        if (value !== 'branch') form.parent_organization_uuid = null;
        else form.plan = null;
    });
    const usagePercentage = (metric) => (!metric?.limit || metric.limit < 1) ? 0 : Math.min(100, Math.round((metric.used / metric.limit) * 100));
    const usageMetricCardClasses = (metric) => {
        if (metric.status === 'exceeded') return 'border-red-200 bg-red-50/70';
        if (metric.status === 'warning') return 'border-amber-200 bg-amber-50/70';
        return 'border-slate-200 bg-slate-50';
    };
    const usageMetricBadgeClasses = (metric) => {
        if (metric.status === 'exceeded') return 'bg-red-100 text-red-700';
        if (metric.status === 'warning') return 'bg-amber-100 text-amber-700';
        if (metric.status === 'unlimited') return 'bg-slate-100 text-slate-700';
        return 'bg-white text-slate-700';
    };
    const usageMetricProgressClasses = (metric) => {
        if (metric.status === 'exceeded') return 'bg-red-500';
        if (metric.status === 'warning') return 'bg-amber-500';
        return 'bg-primary';
    };
    const subscriptionTone = (statusKey) => {
        if (statusKey === 'active' || statusKey === 'trial') return 'bg-emerald-100 text-emerald-700';
        if (statusKey === 'expired' || statusKey === 'inactive' || statusKey === 'none') return 'bg-amber-100 text-amber-700';
        return 'bg-slate-100 text-slate-700';
    };
    const usageNoticeClasses = (notice) => {
        if (notice.type === 'danger') return 'border-red-200 bg-red-50 text-red-950';
        if (notice.type === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
        return 'border-sky-200 bg-sky-50 text-sky-950';
    };
    const usageNoticeIconClasses = (notice) => {
        if (notice.type === 'danger') return 'bg-red-100 text-red-700';
        if (notice.type === 'warning') return 'bg-amber-100 text-amber-700';
        return 'bg-sky-100 text-sky-700';
    };
    const usageNoticeIcon = (notice) => {
        if (notice.type === 'danger') {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2L1 21h22zm0 4.84L19.53 19H4.47zM11 10h2v5h-2zm0 6h2v2h-2z"/></svg>';
        }

        if (notice.type === 'warning') {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z"/></svg>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M11 9h2V7h-2zm0 8h2v-6h-2zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2"/></svg>';
    };
    const submitForm = async () => {
        const url = props.organization ? window.location.pathname : '/admin/organizations';
        form[props.organization ? 'put' : 'post'](url, { preserveScroll: true });
    };
    const toggleFormModal = () => {
        if (isParentManaged.value) return;
        isOpenFormModal.value = !isOpenFormModal.value;
    };
    const submitForm1 = async () => {
        form1.post('/admin/billing', {
            preserveScroll: true,
            onSuccess: () => { toggleFormModal(); changeTab('billing'); },
        });
    };
</script>
