<script setup>
    import { ref, watch, computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import 'vue-tel-input/vue-tel-input.css';
    import { localizeCountryOptions } from '@/Utils/optionLocalizers';

    const props = defineProps({
        modelValue: [String, Number],
        name: String,
        type: String,
        className: String,
        labelClass: String,
        required: Boolean,
        error: String,
        disabled: Boolean,
        allowedCountries: {
            type: Array,
            default: () => []
        }
    })

    const { t, locale } = useI18n();
    const phone = ref(props.modelValue);
    const localError = ref('');
    const localErrorKey = ref('');

    const emit = defineEmits(['update:modelValue']);

    const updateValue = (event) => {
        const phoneValue = event.target.value;
        phone.value = phoneValue;
        localErrorKey.value = '';
        localError.value = '';
        
        emit('update:modelValue', phoneValue);
    };

    // Watch for external error changes
    watch(() => props.error, (newError) => {
        if (newError) {
            localErrorKey.value = '';
            localError.value = newError;
            return;
        }

        localErrorKey.value = '';
        localError.value = '';
    });

    // Watch for modelValue changes
    watch(() => props.modelValue, (newValue) => {
        phone.value = newValue;
    });

    // Computed error to show either local validation error or prop error
    const displayError = computed(() => {
        if (localErrorKey.value) {
            return t(localErrorKey.value);
        }

        return localError.value || props.error;
    });

    const normalizedAllowedCountries = computed(() => {
        if (!Array.isArray(props.allowedCountries)) {
            return [];
        }

        const countries = props.allowedCountries.length
            ? props.allowedCountries
            : [{ name: 'Saudi Arabia', iso2: 'SA', dialCode: '966' }];

        return countries
            .filter((country) => country && typeof country === 'object')
            .map((country) => ({
                name: String(country.name || ''),
                iso2: String(country.iso2 || '').toUpperCase(),
                dialCode: String(country.dialCode || ''),
            }))
            .filter((country) => country.name && country.iso2 && country.dialCode);
    });

    const allowedCountryCodes = computed(() => normalizedAllowedCountries.value.map((country) => country.iso2));
    const defaultCountryCode = computed(() => allowedCountryCodes.value[0] || undefined);
    const localizedAllowedCountries = computed(() => {
        if (!normalizedAllowedCountries.value.length) {
            return [];
        }

        const localizedOptions = localizeCountryOptions(
            normalizedAllowedCountries.value.map((country) => ({
                value: country.iso2,
                label: country.name,
            })),
            locale.value
        );

        const localizedNameByCode = new Map(
            localizedOptions.map((option) => [String(option.value || '').toUpperCase(), option.label || option.rawLabel || ''])
        );

        return normalizedAllowedCountries.value.map((country) => ({
            ...country,
            name: localizedNameByCode.get(country.iso2) || country.name,
        }));
    });


</script>

<template>
    <div :class="className">
        <label for="name" class="ui-form-label" :class="labelClass">{{ name }}</label>
        <div>
            <div class="ui-phone-wrapper">
                <vue-tel-input 
                    :inputOptions="{
                        autocomplete: 'off',
                        showDialCode: false,
                        type: type || 'tel'
                    }"
                    :dropdownOptions="{
                        showDialCodeInList: true,
                        showDialCodeInSelection: true,
                        showFlags: true,
                        showSearchBox: true
                    }"
                    :allCountries="localizedAllowedCountries.length ? localizedAllowedCountries : undefined"
                    :onlyCountries="allowedCountryCodes.length ? allowedCountryCodes : undefined"
                    :defaultCountry="defaultCountryCode"
                    :autoDefaultCountry="false"
                    v-model="phone"
                    :autoFormat="true"
                    :mode="'international'"
                    :validCharactersOnly="true"
                    @input="updateValue"
                    :disabled="disabled"
                >
                </vue-tel-input>
            </div>
        </div>
        <div v-if="displayError" class="ui-form-error">{{ displayError }}</div>
    </div>
</template>
