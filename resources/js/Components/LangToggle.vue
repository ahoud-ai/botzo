<script setup>
    import { ref, computed, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        languages: Object,
        currentLanguage: String,
    })

    const isOpen = ref(false);

    const currentLanguageName = computed(() => {
        const match = Array.isArray(props.languages)
            ? props.languages.find((language) => language.code === props.currentLanguage)
            : null;

        return match?.name || props.currentLanguage;
    });

    const toggleDropdown = () => {
        isOpen.value = !isOpen.value;
    }

    const handleClickOutside = (event) => {
        if (isOpen.value && !event.target.closest('.lang-dd')) {
            isOpen.value = false;
        }
    }

    onMounted(() => {
        document.body.addEventListener('click', handleClickOutside);
    });

    onUnmounted(() => {
        document.body.removeEventListener('click', handleClickOutside);
    });
</script>
<template>
    <div class="relative text-base leading-6">
        <div
            @click="toggleDropdown()"
            class="lang-dd lang-toggle-btn flex items-center justify-center gap-1 w-11 h-11 md:w-auto md:h-auto md:justify-start md:p-2 rounded-lg cursor-pointer transition-colors"
        >
            <span class="hidden md:inline whitespace-nowrap">{{ currentLanguageName }}</span>
            <svg class="w-[18px] h-[18px] md:w-6 md:h-6 shrink-0" viewBox="0 0 20 20" fill="none">
                <path d="M8.25 19.25L13.5 7.5L18.75 19.25M9.75 16.25H17.25M10.08 3.61C8.98 3.54 7.87 3.5 6.75 3.5C4.74 3.5 2.74 3.62 0.75 3.87M6.75 3.5V1.25M10.08 3.61C8.93 8.91 5.44 13.33 0.75 15.75M10.08 3.61C10.98 3.68 11.87 3.76 12.75 3.87M8.16 12.37C6.52 10.7 5.22 8.73 4.33 6.56" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div v-if="isOpen" class="ui-layer-dropdown ui-dropdown-start lang-toggle-menu absolute mt-2 p-1 w-full min-w-[10em] rounded-lg border shadow-lg">
            <div>
                <a v-for="(item, index) in props.languages" :key="item.code ?? index" :href="'/language/' + item.code" class="lang-toggle-item block px-3 py-2 cursor-pointer rounded-md transition-colors">
                    {{ item.name }}
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lang-toggle-btn {
    color: var(--ui-text);
}

.lang-toggle-btn:hover {
    background: var(--ui-surface-soft);
}

.lang-toggle-menu {
    border-color: var(--ui-border);
    background: var(--ui-surface);
}

.lang-toggle-item {
    color: var(--ui-text);
}

.lang-toggle-item:hover {
    background: var(--ui-surface-soft);
}
</style>
