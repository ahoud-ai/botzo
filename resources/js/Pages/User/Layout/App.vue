<template>
    <div :class="[rtlClass, 'ui-shell creative-shell', immersive ? 'ui-shell-immersive' : '']">
        <MobileSidebar v-if="!immersive" :user="user" :config="config" :organization="organization" :organizations="organizations" :title="currentPageTitle" :displayCreateBtn="displayCreateBtn" :displayTopBar="viewTopBar"></MobileSidebar>

        <div class="ui-main creative-main flex w-full tracking-[0.3px]" :class="immersive ? 'ui-main-immersive' : ''">
            <Sidebar v-if="!immersive" :user="user" :config="config" :organization="organization" :organizations="organizations" :unreadMessages="unreadMessages"></Sidebar>
            <div class="ui-content creative-content ui-scroll-root flex flex-col w-full min-w-0" :class="immersive ? 'ui-content-immersive' : ''">
                <slot :user="user" :toggleNavBar="toggleTopBar"></slot>
            </div>
        </div>
    </div>
</template>
<script setup>
    import { usePage } from "@inertiajs/vue3";
    import Sidebar from "./Sidebar.vue";
    import { defineProps, ref, computed, watch, onMounted } from 'vue';
    import { toast } from 'vue3-toastify';
    import MobileSidebar from "./MobileSidebar.vue";
    import 'vue3-toastify/dist/index.css';
    import { getEchoInstance } from '../../../echo';
    import { useRtl } from '@/Composables/useRtl';

    defineProps({
        immersive: {
            type: Boolean,
            default: false,
        },
    });

    const { rtlClass, isRtl } = useRtl();
    const viewTopBar = ref(true);
    const user = computed(() => usePage().props.auth.user);
    const config = computed(() => usePage().props.config);
    const organization = computed(() => usePage().props.organization);
    const organizations = computed(() => usePage().props.organizations);
    const currentPageTitle = computed(() => usePage().props.title);
    const displayCreateBtn = computed(() => usePage().props.allowCreate);
    const unreadMessages = ref(usePage().props.unreadMessages);
    watch(() => [usePage().props.flash, { deep: true }], () => {
        if(usePage().props.flash.status != null){
            toast(usePage().props.flash.status.message, {
                autoClose: 3000,
            });
        }
    });

    const toggleTopBar = () => {
        viewTopBar.value = !viewTopBar.value;
    };

    const getValueByKey = (key) => {
        const found = config.value.find(item => item.key === key);
        return found ? found.value : '';
    };

    const refreshLanguageIfNeeded = () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('refresh_lang') !== '1') {
            return false;
        }

        urlParams.delete('refresh_lang');
        const nextUrl = window.location.pathname + (urlParams.toString() ? `?${urlParams.toString()}` : '');
        window.history.replaceState({}, '', nextUrl);
        window.location.reload();

        return true;
    };

    onMounted(() => {
        if (refreshLanguageIfNeeded()) {
            return;
        }
        const echo = getEchoInstance(
            getValueByKey('pusher_app_key'),
            getValueByKey('pusher_app_cluster')
        );

        if (!echo) {
            return;
        }

        echo.private('chats.ch' + organization.value.id).listen('NewChatEvent', (event) => {
            const chat = event.chat;

            if (chat[0].value.deleted_at == null && chat[0].value.type === 'inbound') {
                unreadMessages.value += 1; // Increment unread messages count
            }
        });
    });

</script>
<style scoped>
.ui-shell-immersive {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(191, 219, 254, 0.5), transparent 28%),
        linear-gradient(180deg, #f7fafc 0%, #edf3fb 100%);
}

.ui-main-immersive {
    min-height: 100vh;
}

.ui-content-immersive {
    padding: 0;
}
</style>
