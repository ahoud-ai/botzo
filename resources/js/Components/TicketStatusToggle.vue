<script setup>
    import { ref, onMounted, onUnmounted } from 'vue';
    import { Link } from "@inertiajs/vue3";

    const props = defineProps({
        languages: Object,
        currentLanguage: String,
        status: String,
        rowCount: Number,
    })

    const isOpen = ref(false);

    const toggleDropdown = () => {
        isOpen.value = !isOpen.value;
    }

    const handleClickOutside = (event) => {
        if (isOpen.value && !event.target.closest('.status-dd')) {
            isOpen.value = false;
        }
    }

    const capitalizeString = (str) => {
        // Check if the string is empty or null
        if (!str) return '';
        
        // Capitalize the first character and concatenate it with the rest of the string
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    onMounted(() => {
        document.body.addEventListener('click', handleClickOutside);
    });

    onUnmounted(() => {
        document.body.removeEventListener('click', handleClickOutside);
    });
</script>
<template>
    <div class="relative text-sm">
        <div @click="toggleDropdown()" class="status-dd">
            <div class="cursor-pointer flex items-center gap-x-1 bg-slate-50 w-[fit-content] h-[fit-content] rounded-md p-1 px-2 text-sm">
                <span class="capitalize">{{ $t(capitalizeString(props.status)) }}</span>
                <span class="text-slate-500">{{ props.rowCount }}</span>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M16.53 8.97a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0l-4-4a.75.75 0 1 1 1.06-1.06L12 12.44l3.47-3.47a.75.75 0 0 1 1.06 0" clip-rule="evenodd"/></svg>
                </span>
            </div>
        </div>
        <div v-if="isOpen" class="ui-dropdown-menu ui-layer-dropdown ui-dropdown-start absolute mt-2 w-full p-1 rounded-md text-[var(--ui-text)]">
            <div>
                <Link :href="'/chats?status=unassigned'" class="ui-dropdown-item block px-2 py-1 cursor-pointer hover:bg-slate-100 rounded-md">
                    {{ $t('Unassigned')}} 
                </Link>
                <Link :href="'/chats?status=open'" class="ui-dropdown-item block px-2 py-1 cursor-pointer hover:bg-slate-100 rounded-md">
                    {{ $t('Open')}} 
                </Link>
                <Link :href="'/chats?status=closed'" class="ui-dropdown-item block px-2 py-1 cursor-pointer hover:bg-slate-100 rounded-md">
                    {{ $t('Closed')}} 
                </Link>
                <Link :href="'/chats?status=all'" class="ui-dropdown-item block px-2 py-1 cursor-pointer hover:bg-slate-100 rounded-md">
                    {{ $t('All')}} 
                </Link>
            </div>
        </div>
    </div>
</template>
