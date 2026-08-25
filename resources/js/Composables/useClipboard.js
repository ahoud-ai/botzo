import { ref } from 'vue';

export function useClipboard(resetDelay = 1600) {
    const copiedKey = ref(null);

    const copy = async (text, key = 'default') => {
        if (!text) {
            return;
        }

        if (navigator?.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);
            } catch (error) {
                return;
            }
        } else {
            const tempInput = document.createElement('textarea');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }

        copiedKey.value = key;

        window.setTimeout(() => {
            if (copiedKey.value === key) {
                copiedKey.value = null;
            }
        }, resetDelay);
    };

    const isCopied = (key = 'default') => copiedKey.value === key;

    return { copy, isCopied, copiedKey };
}
