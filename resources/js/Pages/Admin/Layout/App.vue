<template>
    <div :class="[rtlClass, 'ui-shell creative-shell']">
        <MobileSidebar :user="user" :config="config" :title="currentPageTitle" :displayCreateBtn="displayCreateBtn"></MobileSidebar>

        <div class="ui-main creative-main flex w-full tracking-[0.3px]">
            <Sidebar :user="user" :config="config"></Sidebar>
            <div class="ui-content creative-content ui-scroll-root flex flex-col w-full min-w-0">
                <slot :user="user"></slot>
            </div>
        </div>
    </div>
</template>
<script setup>
    import { usePage } from "@inertiajs/vue3";
    import Sidebar from "./Sidebar.vue";
    import MobileSidebar from "./MobileSidebar.vue";
    import { computed, onMounted, watch } from 'vue';
    import { toast } from 'vue3-toastify';
    import 'vue3-toastify/dist/index.css';
    import { useRtl } from '@/Composables/useRtl';

    const { rtlClass, isRtl } = useRtl();

    const user = computed(() => usePage().props.auth.user);
    const config = computed(() => usePage().props.config);
    const currentPageTitle = computed(() => usePage().props.title);
    const displayCreateBtn = computed(() => usePage().props.allowCreate);
    watch(() => [usePage().props.flash, { deep: true }], () => {
        if(usePage().props.flash.status != null){
            toast(usePage().props.flash.status.message, {
                autoClose: 3000,
            });
        }
    });

    const refreshLanguageIfNeeded = () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('refresh_lang') !== '1') {
            return;
        }

        urlParams.delete('refresh_lang');
        const nextUrl = window.location.pathname + (urlParams.toString() ? `?${urlParams.toString()}` : '');
        window.history.replaceState({}, '', nextUrl);
        window.location.reload();
    };

    onMounted(() => {
        refreshLanguageIfNeeded();
    });
</script>
