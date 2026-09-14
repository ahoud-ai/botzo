<template>
    <AppLayout>
        <UiPageHeader :title="$t('Timezone and currency')" :subtitle="$t('Configure timezone, date and time formats and currency')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormSelect v-model="form.timezone" :name="$t('Timezone')" :type="'text'" :options="localizedTimezones" :error="form.errors.timezone" :class="'sm:col-span-2'"/>
                    <FormSelect v-model="form.date_format" :name="$t('Date format')" :type="'text'" :options="props.date_formats" :error="form.errors.date_format"/>
                    <FormSelect v-model="form.time_format" :name="$t('Time format')" :type="'text'" :options="timeFormatOptions" :error="form.errors.time_format"/>
                    <FormSelect v-model="form.currency" :name="$t('Currency')" :type="'text'" :options="localizedCurrencies" :error="form.errors.currency" :class="'sm:col-span-2'"/>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="tz-btn tz-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import BaseListbox from '@/Components/BaseListbox.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { localizeCurrencyOptions, localizeTimezoneOptions } from '@/Utils/optionLocalizers';

    const props = defineProps({
        config: {
            type: Object,
            required: true
        },
        timezones: {
            type: Array,
            required: true
        },
        date_formats: {
            type: Array,
            required: true
        },
        time_formats: {
            type: Array,
            required: true
        },
        currencies: {
            type: Array,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const { locale } = useI18n();
    const isLoading = ref(false);
    const localizedTimezones = computed(() => localizeTimezoneOptions(props.timezones ?? [], locale.value));
    const localizedCurrencies = computed(() => localizeCurrencyOptions(props.currencies ?? [], locale.value));
    const allowedTimezoneValues = (props.timezones ?? []).map((option) => option.value);
    const savedTimezone = getValueByKey('timezone');
    const fallbackTimezone = allowedTimezoneValues[0] ?? 'Asia/Riyadh';
    const initialTimezone = allowedTimezoneValues.includes(savedTimezone) ? savedTimezone : fallbackTimezone;

    const form = useForm({
        timezone: initialTimezone,
        currency: getValueByKey('currency'),
        date_format: getValueByKey('date_format'),
        time_format: getValueByKey('time_format'),
    })

    // Function to format time based on selected time format
    const formatTime = (date, timeFormat, timezone) => {
        if (!timeFormat) return date.toLocaleTimeString();

        // Map common time format options to JavaScript date formatting
        const formatMap = {
            'H:i:s': 'HH:mm:ss',
            'h:i:s A': 'hh:mm:ss a',
            'H:i': 'HH:mm',
            'h:i A': 'hh:mm a',
            '24': 'HH:mm:ss',
            '12': 'hh:mm:ss a'
        };

        const format = formatMap[timeFormat] || 'HH:mm:ss';

        return new Intl.DateTimeFormat('en-US', {
            timeZone: timezone || 'UTC',
            hour12: format.includes('a'),
            hour: format.includes('HH') ? '2-digit' : 'numeric',
            minute: '2-digit',
            second: format.includes('ss') ? '2-digit' : undefined
        }).format(date);
    };

    // Create dynamic time format options with actual time display
    const timeFormatOptions = computed(() => {
        if (!form.timezone) return props.time_formats;

        const now = new Date();
        return props.time_formats.map(format => {
            const timeInTimezone = formatTime(now, format.value, form.timezone);
            return {
                ...format,
                label: timeInTimezone
            };
        });
    });

    const submitForm = async () => {
        form.put('/admin/settings?type=timezone', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.tz-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.tz-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tz-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.tz-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
