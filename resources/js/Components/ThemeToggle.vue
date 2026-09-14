<script setup>
    import { useTheme } from '@/Composables/useTheme';

    const { theme, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        class="theme-toggle-btn inline-flex items-center justify-center w-11 h-11 rounded-lg border transition-colors shrink-0"
        :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
    >
        <svg v-if="theme === 'dark'" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path fill="currentColor" d="M12 18a6 6 0 1 1 0-12a6 6 0 0 1 0 12M12 1a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V2a1 1 0 0 1 1-1m0 19a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1M4.222 4.222a1 1 0 0 1 1.415 0l.707.707a1 1 0 1 1-1.414 1.415l-.708-.707a1 1 0 0 1 0-1.415m14.142 14.142a1 1 0 0 1 1.414 0l.708.707a1 1 0 1 1-1.415 1.415l-.707-.708a1 1 0 0 1 0-1.414M1 12a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2H2a1 1 0 0 1-1-1m19 0a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1M4.222 19.778a1 1 0 0 1 0-1.414l.707-.708a1 1 0 1 1 1.415 1.415l-.708.707a1 1 0 0 1-1.414 0M18.364 5.636a1 1 0 0 1 0-1.414l.707-.708a1 1 0 0 1 1.415 1.415l-.708.707a1 1 0 0 1-1.414 0"/>
        </svg>
        <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path fill="currentColor" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75c0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998"/>
        </svg>
    </button>
</template>

<style scoped>
.theme-toggle-btn {
    color: var(--ui-text);
    border-color: var(--ui-border);
    background: transparent;
}

.theme-toggle-btn:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}
</style>
