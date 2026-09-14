import { ref } from 'vue';

const THEME_STORAGE_KEY = 'theme';

function getStoredTheme() {
    const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    if (stored === 'dark' || stored === 'light') {
        return stored;
    }
    return 'dark';
}

function applyTheme(value) {
    document.documentElement.classList.toggle('dark', value === 'dark');
}

const theme = ref('dark');

export function initTheme() {
    theme.value = getStoredTheme();
    applyTheme(theme.value);
}

export function useTheme() {
    const setTheme = (value) => {
        theme.value = value;
        window.localStorage.setItem(THEME_STORAGE_KEY, value);
        applyTheme(value);
    };

    const toggleTheme = () => {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    };

    return { theme, setTheme, toggleTheme };
}
