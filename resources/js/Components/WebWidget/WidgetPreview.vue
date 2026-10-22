<template>
    <div class="wp-stage" :class="[`wp-stage--${form.theme}`, form.position === 'left' ? 'items-start' : 'items-end']" dir="rtl">
        <!-- Mochi: the welcome state of the panel, as the widget shows it -->
        <div v-if="form.theme === 'mochi'" class="wp-glass">
            <div class="wp-glass-close" aria-hidden="true">✕</div>
            <div class="wp-glass-title">{{ $t('Hello and welcome to {name} 👋', { name: business }) }}</div>
            <div class="wp-glass-sub">{{ form.welcome_text || $t('I am {name}, your smart assistant. Ask me anything.', { name: assistant }) }}</div>
            <div class="wp-mochi" aria-hidden="true" v-html="mochiMarkup"></div>
            <div class="wp-talk">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                {{ $t('Press and talk') }}
            </div>
            <div class="wp-chips">
                <span v-for="(item, index) in suggestions" :key="index" class="wp-chip">{{ item }}</span>
            </div>
            <div class="wp-input">
                <span class="wp-mic" :style="{ background: accent }" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0A0E16" stroke-width="2.2" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                </span>
                <span>{{ $t('Type your question...') }}</span>
            </div>
            <div class="wp-foot">
                <span>{{ $t('Reply by typing') }}</span>
                <span v-if="showBranding">Powered by <b :style="{ color: accent }">botzo</b></span>
                <span>{{ $t('End') }}</span>
            </div>
        </div>

        <!-- The older looks: header, bubble and chips -->
        <div v-else class="wp-panel" :class="form.theme === 'botzo' ? 'wp-panel--dark' : ''">
            <div class="wp-head" :style="{ background: form.theme === 'botzo' ? 'linear-gradient(135deg, #22D366, #2F7BFF)' : accent }">
                <span class="wp-avatar">{{ assistant.charAt(0) }}</span>
                <span class="truncate">{{ assistant }}</span>
            </div>
            <div class="wp-body">
                <div class="wp-bubble">{{ form.welcome_text || $t('Hi! How can I help you today?') }}</div>
                <div class="wp-chips wp-chips--start">
                    <span v-for="(item, index) in suggestions" :key="index" class="wp-chip" :style="{ color: chipColor, borderColor: chipColor }">{{ item }}</span>
                </div>
            </div>
        </div>

        <div class="wp-launcher" :class="form.theme === 'mochi' ? 'wp-launcher--mochi' : ''">
            <div v-if="form.theme === 'mochi'" class="wp-mochi-mini" aria-hidden="true" v-html="mochiMiniMarkup"></div>
            <span v-else class="wp-launcher-dot" :style="{ background: form.theme === 'botzo' ? '#22D366' : accent }"></span>
            <span v-if="form.theme === 'mochi'" class="wp-launcher-label">{{ $t('Talk to me') }}</span>
        </div>
    </div>
</template>

<script setup>
    import { computed } from 'vue';
    import { mochiSvg } from './mochi';

    const props = defineProps({
        form: { type: Object, required: true },
        organizationName: { type: String, default: '' },
        showBranding: { type: Boolean, default: true },
    });

    const HEX = /^#[0-9a-fA-F]{6}$/;
    const accent = computed(() => (HEX.test(props.form.accent) ? props.form.accent : '#22D366'));
    const secondary = computed(() => (HEX.test(props.form.accent_secondary ?? '') ? props.form.accent_secondary : '#2F7BFF'));
    const chipColor = computed(() => (props.form.theme === 'botzo' ? '#22D366' : accent.value));
    const assistant = computed(() => (props.form.assistant_name || '').trim() || props.organizationName || 'Mochi');
    const business = computed(() => (props.form.business_label || '').trim() || props.organizationName);
    const suggestions = computed(() => (props.form.suggestions ?? []).map((item) => (item || '').trim()).filter(Boolean));
    const mochiMarkup = computed(() => mochiSvg({ primary: accent.value, secondary: secondary.value, uid: 'wpBig' }));
    const mochiMiniMarkup = computed(() => mochiSvg({ primary: accent.value, secondary: secondary.value, uid: 'wpMini' }));
</script>

<style scoped>
.wp-stage {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-radius: 1rem;
    background: #07090d;
    padding: 1rem;
    font-family: 'IBM Plex Sans Arabic', Tahoma, sans-serif;
}

.wp-glass {
    position: relative;
    width: 100%;
    max-width: 300px;
    border-radius: 22px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.07);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
    padding: 1rem;
    color: #f2f5f9;
    text-align: center;
}

.wp-glass-close {
    position: absolute;
    inset-inline-end: 0.75rem;
    top: 0.75rem;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    font-size: 0.7rem;
    line-height: 1.5rem;
}

.wp-glass-title {
    margin-top: 0.75rem;
    font-size: 1rem;
    font-weight: 700;
}

.wp-glass-sub {
    margin-top: 0.35rem;
    font-size: 0.72rem;
    line-height: 1.6;
    color: #a3adbd;
}

.wp-mochi {
    width: 110px;
    margin: 0.75rem auto 0.25rem;
    filter: drop-shadow(0 0 22px rgba(34, 211, 102, 0.35));
}

.wp-mochi :deep(svg),
.wp-mochi-mini :deep(svg) {
    display: block;
    width: 100%;
    height: auto;
}

.wp-talk {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    color: #a3adbd;
}

.wp-chips {
    margin-top: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.35rem;
}

.wp-chips--start {
    justify-content: flex-start;
}

.wp-chip {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.06);
    padding: 0.2rem 0.6rem;
    font-size: 0.68rem;
}

.wp-input {
    margin-top: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.3rem;
    font-size: 0.7rem;
    color: #7c8799;
}

.wp-mic {
    display: inline-flex;
    width: 1.6rem;
    height: 1.6rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    box-shadow: 0 0 12px rgba(34, 211, 102, 0.6);
}

.wp-foot {
    margin-top: 0.5rem;
    display: flex;
    justify-content: space-between;
    font-size: 0.62rem;
    color: #7c8799;
}

.wp-panel {
    width: 100%;
    max-width: 280px;
    overflow: hidden;
    border-radius: 18px;
    background: #fff;
    color: #1f2933;
}

.wp-panel--dark {
    background: #0f1520;
    color: #f2f5f9;
    border: 1px solid #1e2838;
}

.wp-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 0.9rem;
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
}

.wp-panel--dark .wp-head {
    color: #0a0e16;
}

.wp-avatar {
    display: inline-flex;
    width: 1.9rem;
    height: 1.9rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
}

.wp-body {
    padding: 0.8rem;
}

.wp-bubble {
    border-radius: 14px;
    border: 1px solid #e3e7e5;
    background: #fff;
    padding: 0.5rem 0.75rem;
    font-size: 0.78rem;
    line-height: 1.6;
    white-space: pre-line;
}

.wp-panel--dark .wp-bubble {
    background: #1a2333;
    border-color: #1a2333;
}

.wp-launcher {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
}

.wp-mochi-mini {
    width: 56px;
}

.wp-launcher-label {
    font-size: 0.7rem;
    color: #dce3ec;
}

.wp-launcher-dot {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
}
</style>
