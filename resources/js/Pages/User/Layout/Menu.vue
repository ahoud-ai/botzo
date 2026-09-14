<template>
    <div v-if="menuIconsOnly" class="ui-menu creative-menu ui-sidebar-header ui-sidebar-header--compact flex flex-col items-center justify-center gap-y-2 px-2 h-24 mb-1">
        <Link href="/dashboard" class="shrink-0">
            <NavBrandMark variant="mobile" />
        </Link>
        <button type="button" class="ui-sidebar-collapse-btn shrink-0" :title="$t('Expand menu')" @click="toggleMenu">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3.5v17M3 9.4c0-2.24 0-3.36.436-4.216a4 4 0 0 1 1.748-1.748C6.04 3 7.16 3 9.4 3h5.2c2.24 0 3.36 0 4.216.436a4 4 0 0 1 1.748 1.748C21 6.04 21 7.16 21 9.4v5.2c0 2.24 0 3.36-.436 4.216a4 4 0 0 1-1.748 1.748C17.96 21 16.84 21 14.6 21H9.4c-2.24 0-3.36 0-4.216-.436a4 4 0 0 1-1.748-1.748C3 17.96 3 16.84 3 14.6z"/></svg>
        </button>
    </div>
    <div v-else class="ui-menu creative-menu ui-sidebar-header flex items-center justify-between px-5 pt-5 h-20 mb-1">
        <Link href="/dashboard">
            <NavBrandMark variant="desktop" />
        </Link>
        <div class="flex items-center gap-x-2">
            <button type="button" class="ui-sidebar-collapse-btn" :title="$t('Collapse menu')" @click="toggleMenu">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3.5v17M3 9.4c0-2.24 0-3.36.436-4.216a4 4 0 0 1 1.748-1.748C6.04 3 7.16 3 9.4 3h5.2c2.24 0 3.36 0 4.216.436a4 4 0 0 1 1.748 1.748C21 6.04 21 7.16 21 9.4v5.2c0 2.24 0 3.36-.436 4.216a4 4 0 0 1-1.748 1.748C17.96 21 16.84 21 14.6 21H9.4c-2.24 0-3.36 0-4.216-.436a4 4 0 0 1-1.748-1.748C3 17.96 3 16.84 3 14.6z"/></svg>
            </button>
            <span v-if="isSidebarOpen === true" @click="closeSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" width="2em" height="2em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"><path d="M5 5L12 5L19 5"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 5L12 5L19 5;M5 5L12 12L19 5"/></path><path d="M5 12H19"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 12H19;M12 12H12"/></path><path d="M5 19L12 19L19 19"><animate fill="freeze" attributeName="d" dur="0.4s" values="M5 19L12 19L19 19;M5 19L12 12L19 19"/></path></g></svg>
            </span>
        </div>
    </div>
    <div class="ui-menu flex-grow space-y-4 px-3 pt-2 overflow-y-auto">
        <div class="flex-1">
            <p class="ui-sidebar-section-label" :class="menuIconsOnly ? 'ui-sidebar-section-label--collapsed' : ''">{{ $t('Menu') }}</p>
            <ul class="space-y-1 text-sm mb-1">
                <li class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/dashboard') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/dashboard" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="14" y="3" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="14" y="12" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.75"/><rect x="3" y="16" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.75"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Dashboard') }}</span>
                    </Link>
                </li>
                <li v-if="canViewChats" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/chats') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/chats" class="flex items-center justify-between p-2 gap-x-3 rounded-xl">
                        <div class="flex items-center gap-x-3">
                            <span class="ui-sidebar-icon-chip" :class="menuIconsOnly && parseInt(unreadMessages) > 0 ? 'ui-sidebar-icon-chip--dot' : ''"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Chats') }}</span>
                        </div>
                        <span v-if="parseInt(unreadMessages) > 0 && !menuIconsOnly" class="ui-sidebar-badge">{{ unreadMessages }}</span>
                    </Link>
                </li>
                <li v-if="canViewContacts" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/contact') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/contacts" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Contacts') }}</span>
                    </Link>
                </li>
                <li v-if="canViewCampaigns" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/campaign') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/campaigns" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m3 11 18-5v12L3 14v-3z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Campaigns') }}</span>
                    </Link>
                </li>
                <li v-if="canViewTemplates" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/template') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/templates" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v4a2 2 0 0 0 2 2h4M9 13h6M9 17h6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Message templates') }}</span>
                    </Link>
                </li>
                <li v-if="canViewAutomations" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/automation') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/automation/basic" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Automation') }}</span>
                    </Link>
                </li>
            </ul>

            <p class="ui-sidebar-section-label ui-sidebar-section-label--spaced" :class="menuIconsOnly ? 'ui-sidebar-section-label--collapsed' : ''">{{ $t('Account') }}</p>
            <div class="px-4">
                <hr class="ui-sidebar-divider" :class="menuIconsOnly ? '' : 'ui-sidebar-divider--hidden'">
            </div>

            <ul class="pb-2 space-y-1 text-sm">
                <li v-if="canManageSettings" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/settings') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/settings" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="10" cy="4" r="2" stroke="currentColor" stroke-width="1.75"/><circle cx="8" cy="12" r="2" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="20" r="2" stroke="currentColor" stroke-width="1.75"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Settings') }}</span>
                    </Link>
                </li>
                <li v-if="canViewBilling" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/billing') || $page.url.startsWith('/subscription') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/billing" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M2 10h20M6 15h4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Billing and subscription') }}</span>
                    </Link>
                </li>
                <li class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/meta-verification') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/meta-verification" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Meta verification') }}</span>
                    </Link>
                </li>
                <li class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/support') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/support" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="17" r="0.9" fill="currentColor"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Support') }}</span>
                    </Link>
                </li>
                <li v-if="canAccessDeveloperTools" class="ui-sidebar-item rounded-xl px-2 truncate" :class="$page.url.startsWith('/developer-tools') ? 'ui-sidebar-item--active' : ''">
                    <Link rel="noopener noreferrer" href="/developer-tools/access-tokens" class="flex items-center p-2 gap-x-3 rounded-xl">
                        <span class="ui-sidebar-icon-chip"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="ui-sidebar-item-label" :class="menuIconsOnly ? 'ui-sidebar-item-label--collapsed' : ''">{{ $t('Developer Tools') }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>

    <div class="ui-sidebar-footer">
        <div v-if="menuIconsOnly === false" @click="switchTeams()" class="menu-workspace-card cursor-pointer">
            <span class="menu-workspace-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 21V7a1 1 0 0 1 1-1h5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2h3a1 1 0 0 1 1 1v13M4 21h16M4 21H2m18 0h2M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="flex min-w-0 flex-1 flex-col">
                <span class="menu-workspace-name truncate">{{ props.organization.name }}</span>
                <span class="menu-workspace-meta truncate">{{ currentWorkspaceContext || $t('Workspace') }}</span>
            </div>
            <svg class="shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m8 10l4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>

        <div v-if="!menuIconsOnly" class="ui-sidebar-utility-row">
            <ThemeToggle />
            <LangToggle :languages="languages" :currentLanguage="currentLanguage" />
        </div>
        <div v-else class="mb-2 flex flex-col items-center gap-2">
            <ThemeToggle />
        </div>

        <Dropdown :align="'top-start'" class="ui-sidebar-profile-dropdown">
            <div class="ui-sidebar-profile-card cursor-pointer" :class="!menuIconsOnly ? 'justify-between' : 'justify-center'">
                <div class="flex min-w-0 items-center gap-x-2">
                    <div class="ui-sidebar-profile-avatar">
                        <img v-if="user.avatar" class="h-full w-full rounded-full object-cover" :src="'/media/' + user.avatar" alt="">
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" d="M19.998 18c.002-.164.002-.331.002-.5c0-2.485-3.582-4.5-8-4.5s-8 2.015-8 4.5S4 22 12 22c2.231 0 3.84-.157 5-.437"/></g></svg>
                    </div>
                    <div v-if="!menuIconsOnly" class="min-w-0">
                        <h2 class="ui-sidebar-profile-name truncate">{{ user.first_name + ' ' + user.last_name }}</h2>
                    </div>
                </div>
                <svg v-if="!menuIconsOnly" class="shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m8 10l4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <template #items>
                <DropdownItemGroup>
                    <DropdownItem as="button" @click="openModal">
                        <span class="flex items-center gap-x-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" d="M19.998 18c.002-.164.002-.331.002-.5c0-2.485-3.582-4.5-8-4.5s-8 2.015-8 4.5S4 22 12 22c2.231 0 3.84-.157 5-.437"/></g></svg>
                            {{ $t('View profile') }}
                        </span>
                    </DropdownItem>
                    <MenuItem v-slot="{ active }">
                        <Link href="/logout" method="post" as="button" type="button" :class="[active ? 'ui-dropdown-item ui-dropdown-item-active' : 'ui-dropdown-item', 'group flex w-full items-center gap-x-2 rounded-md px-2 py-2 text-sm ui-sidebar-logout-item']">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 15l3-3m0 0l-3-3m3 3H4m5-4.751V7.2c0-1.12 0-1.68.218-2.108c.192-.377.497-.682.874-.874C10.52 4 11.08 4 12.2 4h4.6c1.12 0 1.68 0 2.107.218c.377.192.683.497.875.874c.218.427.218.987.218 2.105v9.607c0 1.118 0 1.677-.218 2.104a2.002 2.002 0 0 1-.875.874c-.427.218-.986.218-2.104.218h-4.606c-1.118 0-1.678 0-2.105-.218a2 2 0 0 1-.874-.874C9 18.48 9 17.92 9 16.8v-.05"/></svg>
                            {{ $t('Logout') }}
                        </Link>
                    </MenuItem>
                </DropdownItemGroup>
            </template>
        </Dropdown>
    </div>

    <Modal :label="$t('Switch workspaces')" :isOpen="isLocationSwitchModalOpen" @close="isLocationSwitchModalOpen = false">
        <div class="mt-2 grid grid-cols-1 gap-x-6">
            <div class="pt-3 space-y-2 text-sm">
                <div v-for="(item, index) in props.organizations" :key="index" @click="selectOrganization(item.organization.uuid)" class="ws-switch-row">
                    <div class="flex items-center gap-x-2">
                        <span class="ws-switch-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" fill-rule="evenodd"><path d="M24 0v24H0V0h24ZM12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035c-.01-.004-.019-.001-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427c-.002-.01-.009-.017-.017-.018Zm.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093c.012.004.023 0 .029-.008l.004-.014l-.034-.614c-.003-.012-.01-.02-.02-.022Zm-.715.002a.023.023 0 0 0-.027.006l-.006.014l-.034.614c0 .012.007.02.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01l-.184-.092Z"/><path fill="currentColor" d="M17 3.722v5.497l2.864.716A1.5 1.5 0 0 1 21 11.39V19a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2v-7.69a1.5 1.5 0 0 1 .83-1.343L7 8.382V6.347a1.5 1.5 0 0 1 .973-1.405l7-2.625A1.5 1.5 0 0 1 17 3.722Zm-2 .721l-6 2.25V19h6V4.443Zm2 6.838V19h2v-7.22l-2-.5Zm-10-.663l-2 1V19h2v-8.382Z"/></g></svg>
                        </span>
                        <div>
                            <h3 class="ws-switch-name">{{ item.organization.name }}</h3>
                            <div v-if="item.organization.organization_type === 'branch'" class="mt-1 text-xs ui-text-muted">
                                {{ $t('Branch of') }}: {{ item.organization.parent_organization?.name ?? $t('Parent organization') }}
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="ws-switch-tag">
                                    {{ item.organization.organization_type === 'branch' ? $t('Branch') : $t('Main workspace') }}
                                </span>
                                <span
                                    v-if="item.access?.source === 'inherited_parent_owner'"
                                    class="ws-switch-tag ws-switch-tag--info"
                                >
                                    {{ $t('Managed from parent') }}
                                </span>
                                <span
                                    v-else
                                    class="ws-switch-tag ws-switch-tag--success"
                                >
                                    {{ $t('Direct member') }}
                                </span>
                                <span
                                    v-if="item.access?.isCurrent"
                                    class="ws-switch-tag ws-switch-tag--current"
                                >
                                    {{ $t('Current') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="ui-text-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20"><path fill="currentColor" fill-rule="evenodd" d="M5 2.643v14.765c.092.32.299.511.619.572c.32.061.633-.024.94-.255l8.107-6.993A.944.944 0 0 0 15 10a.94.94 0 0 0-.334-.73L6.58 2.295c-.232-.197-.639-.383-1.061-.253c-.282.087-.455.287-.519.6"/></svg>
                    </span>
                </div>
                <div
                    @click="openOrganizationModal()"
                    :class="[
                        'ws-switch-row ws-switch-row--action py-3',
                        branchCreationBlocked ? 'ws-switch-row--blocked' : '',
                    ]"
                >
                    <div class="w-full">
                        <h3 class="text-center">{{ branchCreationContext ? $t('Add branch') : $t('Create Organization') }}</h3>
                    </div>
                </div>
                <div
                    v-if="branchCreationBlocked && branchCreationBlockingMessage"
                    class="ws-switch-warning"
                >
                    {{ branchCreationBlockingMessage }}
                </div>
            </div>
            <div class="mt-4 border-t border-[var(--ui-border)] pt-4">
                <button type="button" @click="isLocationSwitchModalOpen = false" class="inline-flex justify-center rounded-md border border-transparent ui-bg-soft ui-text-muted px-4 py-2 text-sm hover:bg-[var(--ui-border)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-secondary)] focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
            </div>
        </div>
    </Modal>
    <ProfileModal :user="props.user" :organization="props.organization" :isOpen="isOpen" role="user" :languages="languages" @close="closeModal()"/>
    <OrganizationModal v-model:modelValue="isOpenOrganizationModal" :type="branchCreationContext"/>
</template>
<script setup>
    import axios from "axios";
    import { Link, useForm, usePage } from "@inertiajs/vue3";
    import { defineProps, ref, computed } from "vue";
    import { MenuItem } from '@headlessui/vue';
    import Modal from '@/Components/Modal.vue';
    import ProfileModal from '@/Components/ProfileModal.vue';
    import OrganizationModal from '@/Components/OrganizationModal.vue';
    import LangToggle from '@/Components/LangToggle.vue';
    import NavBrandMark from '@/Components/NavBrandMark.vue';
    import ThemeToggle from '@/Components/ThemeToggle.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';

    const props = defineProps(['config', 'user', 'organization', 'organizations', 'isSidebarOpen', 'unreadMessages']);
    const page = usePage();
    const languages = computed(() => page.props.languages);
    const currentLanguage = computed(() => page.props.currentLanguage);
    const branchCreationContext = computed(() => page.props.branchCreationContext ?? null);
    const {
        workspaceAccess,
        hasInheritedParentAuthority,
        canViewBilling,
        hasPermission,
    } = useWorkspaceAccess();
    const branchCreationBlocked = computed(() => Boolean(
        branchCreationContext.value
        && branchCreationContext.value.type === 'branch'
        && branchCreationContext.value.canCreateBranch === false
    ));
    const branchCreationBlockingMessage = computed(() => (
        branchCreationBlocked.value
            ? (branchCreationContext.value?.blockingMessage || '')
            : ''
    ));
    const isOpen = ref(false);
    const isLocationSwitchModalOpen = ref(false);
    const showDropdown1 = ref(false);
    const isOpenOrganizationModal = ref(false);
    const menuIconsOnly = ref(localStorage.getItem('MenuOpen') === 'true');

    // Permission checks for menu items
    const canViewChats = computed(() => hasPermission('chats.view_all'));
    const canViewContacts = computed(() => hasPermission('contacts.view_all'));
    const canViewCampaigns = computed(() => hasPermission('campaigns.view_all'));
    const canViewTemplates = computed(() => hasPermission('message_templates.view_all'));
    const canViewAutomations = computed(() => hasPermission('automations.view_all'));
    const canManageSettings = computed(() => hasPermission('settings.manage'));
    const canAccessDeveloperTools = computed(() => hasPermission('developer_tools.view'));
    const currentWorkspaceContext = computed(() => {
        if (!props.organization) {
            return '';
        }

        if (props.organization.organization_type === 'branch') {
            if (hasInheritedParentAuthority.value) {
                const inheritedLabel = currentLanguage.value === 'ar'
                    ? 'إدارة موروثة من المؤسسة الأم'
                    : 'Managed from parent organization';

                return workspaceAccess.value?.billingOwner?.name
                    ? `${workspaceAccess.value.billingOwner.name} - ${inheritedLabel}`
                    : inheritedLabel;
            }

            return currentLanguage.value === 'ar' ? 'مساحة عمل فرعية' : 'Branch workspace';
        }

        return currentLanguage.value === 'ar' ? 'مساحة العمل الرئيسية' : 'Main workspace';
    });

    const emit = defineEmits(['closeSidebar']);

    const form = useForm({
        uuid: null,
    })

    const closeSidebar = () => {
        emit('closeSidebar', true);
    }

    const toggleMenu = () => {
        menuIconsOnly.value = !menuIconsOnly.value;
        localStorage.setItem('MenuOpen', menuIconsOnly.value)
    }

    defineExpose({
        menuIconsOnly
    });

    const toggleDropdown = (type) => {
        if(type === 'dropdown1'){
            showDropdown1.value = !showDropdown1.value;
        }
    }

    const closeModal = () => {
        isOpen.value = false
    }

    const openModal = () => {
        isOpen.value = true;
        emit('closeSidebar', true);
    }

    const switchTeams = () => {
        isLocationSwitchModalOpen.value = true; 
        emit('closeSidebar', true);
    }

    const openOrganizationModal = () => {
        if (branchCreationBlocked.value) {
            return;
        }

        isOpenOrganizationModal.value = true;
    }

    const selectOrganization = (uuid) => {
        form.uuid = uuid;
        submitForm();
    }

    const submitForm = async () => {
        form.post('/select-organization', {
            preserveScroll: true,
            onFinish: isLocationSwitchModalOpen.value = false,
        })
    };
</script>

<style scoped>
.menu-workspace-card {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.7rem;
    border-radius: 0.85rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.menu-workspace-card:hover {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface-soft));
    border-color: color-mix(in srgb, var(--ui-secondary) 24%, var(--ui-border));
    transform: translateY(-1px);
}

.menu-workspace-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border-radius: 0.6rem;
    color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 14%, transparent);
}

.menu-workspace-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-text);
}

.menu-workspace-meta {
    font-size: 0.74rem;
    color: var(--ui-muted);
}

.ws-switch-row {
    display: flex;
    gap: 2rem;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.4rem 0.5rem;
    border-radius: 0.7rem;
    border: 1px solid var(--ui-border);
    cursor: pointer;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.ws-switch-row:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.ws-switch-row--action {
    justify-content: center;
    background: var(--ui-surface-soft);
}

.ws-switch-row--action:hover {
    background: color-mix(in srgb, var(--ui-secondary) 10%, var(--ui-surface-soft));
}

.ws-switch-row--blocked {
    cursor: not-allowed;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
}

.ws-switch-row--blocked:hover {
    background: var(--ui-surface-soft);
}

.ws-switch-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    border-radius: 0.6rem;
    background: var(--ui-surface-soft);
    color: var(--ui-secondary);
}

.ws-switch-name {
    color: var(--ui-text);
    font-weight: 600;
}

.ws-switch-tag {
    border-radius: 999px;
    padding: 0.1rem 0.55rem;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
}

.ws-switch-tag--info {
    background: color-mix(in srgb, var(--ui-primary) 14%, transparent);
    color: var(--ui-primary);
}

.ws-switch-tag--success {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    color: color-mix(in srgb, var(--ui-success) 70%, var(--ui-text));
}

.ws-switch-tag--current {
    background: var(--ui-secondary);
    color: #fff;
}

.ws-switch-warning {
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 45%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    padding: 0.7rem 1rem;
    font-size: 0.85rem;
    color: color-mix(in srgb, var(--ui-warning) 70%, var(--ui-text));
}
</style>

