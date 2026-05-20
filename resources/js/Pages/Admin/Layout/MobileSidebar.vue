<template>
    <div class="ui-mobile-topbar creative-mobile-topbar sticky top-0 ui-layer-content w-full border-b px-4 py-4 flex items-center justify-between md:hidden">
        <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-primary hover:text-primary"
            :aria-label="$t('Open menu')"
            :title="$t('Open menu')"
            @click="isSidebarOpen = true"
        >
            <MenuIcon class="h-5 w-5" />
        </button>

        <h3 class="min-w-0 flex-1 px-3 text-center text-lg font-bold text-slate-900 truncate">{{ props.title }}</h3>

        <div class="flex h-11 w-11 items-center justify-center">
            <Link
                v-if="props.displayCreateBtn"
                :href="createHref"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-primary transition hover:bg-primary hover:text-white"
                :aria-label="$t('Create')"
                :title="$t('Create')"
            >
                <Plus class="h-5 w-5" />
            </Link>
            <button
                v-else
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-primary hover:text-primary"
                :aria-label="$t('View profile')"
                :title="$t('View profile')"
                @click="isSidebarOpen = true"
            >
                <UserRound class="h-5 w-5" />
            </button>
        </div>
    </div>

    <aside
        class="ui-mobile-drawer ui-layer-drawer creative-mobile-drawer fixed top-0 h-full w-full overflow-hidden transition-all duration-300 ease-in-out md:hidden"
        :class="[drawerPlacementClass, isSidebarOpen ? 'translate-x-0' : drawerClosedClass]"
    >
        <Menu :config="props.config" :user="props.user" :isSidebarOpen="isSidebarOpen" @closeSidebar="isSidebarOpen = false" />
    </aside>
</template>

<script setup>
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed, defineProps, ref } from "vue";
    import { Menu as MenuIcon, Plus, UserRound } from 'lucide-vue-next';
    import { useRtl } from '@/Composables/useRtl';
    import Menu from './Menu.vue';

    const props = defineProps({
        title: {
            type: String,
            default: '',
        },
        displayCreateBtn: {
            type: [Boolean, String],
            default: false,
        },
        user: {
            type: Object,
            required: true,
        },
        config: {
            type: Array,
            required: true,
        },
    });

    const { isRtl } = useRtl();
    const isSidebarOpen = ref(false);
    const drawerPlacementClass = computed(() => (isRtl.value ? 'ui-mobile-drawer-end' : 'ui-mobile-drawer-start'));
    const drawerClosedClass = computed(() => (isRtl.value ? 'translate-x-full' : '-translate-x-full'));
    const createHref = computed(() => {
        const currentPath = (usePage().url || '/').split('?')[0];
        const normalizedPath = currentPath === '/' ? '' : currentPath.replace(/\/+$/, '');

        if (normalizedPath.endsWith('/create')) {
            return normalizedPath === '' ? '/create' : normalizedPath;
        }

        return normalizedPath === '' ? '/create' : `${normalizedPath}/create`;
    });
</script>
