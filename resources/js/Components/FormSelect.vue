<script setup>
    import { computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import {
      Listbox,
      ListboxButton,
      ListboxOptions,
      ListboxOption,
    } from '@headlessui/vue'
    import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';

    const props = defineProps({
      options: Array,
      modelValue: [String, Number, Array],
      name: String,
      className: String,
      optionClassName: String,
      placeholder: {
        type: String, 
        default: 'Select option'
      },
      multiple: Boolean,
      required: Boolean,
      error: String,
      disabled: Boolean,
      dropdownUp: {
        type: Boolean,
        default: false
      }
    })

    const emit = defineEmits(['update:modelValue'])
    const { t, te } = useI18n();

    const translateLabel = (value) => {
      const raw = String(value ?? '').trim();
      if (!raw) {
        return '';
      }

      for (const candidate of buildI18nLookupCandidates(raw)) {
        if (te(candidate)) {
          const translated = t(candidate);
          if (typeof translated === 'string' && translated.trim() !== '') {
            return translated;
          }
        }
      }

      return raw;
    };

    const resolveOptionLabel = (option) => {
      if (!option || typeof option !== 'object') {
        return '';
      }

      if (typeof option.labelKey === 'string' && option.labelKey !== '') {
        return t(option.labelKey);
      }

      const shouldSkipTranslation = Boolean(option.disableTranslation);

      if (typeof option.label === 'string' && option.label !== '') {
        return shouldSkipTranslation ? option.label : translateLabel(option.label);
      }

      if (typeof option.rawLabel === 'string' && option.rawLabel !== '') {
        return shouldSkipTranslation ? option.rawLabel : translateLabel(option.rawLabel);
      }

      return shouldSkipTranslation ? String(option.value ?? '') : translateLabel(option.value);
    };

    const normalizedOptions = computed(() => {
      if (Array.isArray(props.options)) {
        return props.options;
      }

      if (props.options && typeof props.options === 'object') {
        return Object.values(props.options);
      }

      return [];
    });

    const label = computed(() => {
      return normalizedOptions.value.filter(option => {
        if(Array.isArray(props.modelValue)){
          return props.modelValue.includes(option.value);
        }

        return props.modelValue === option.value;
      }).map(option => resolveOptionLabel(option)).join(', ');
    })
</script>
<template>
    <div :class="className">
        <label for="name" class="ui-form-label">{{ name }}</label>
        <div v-if="disabled == true">
            <input
            class="ui-input placeholder:text-gray-400 sm:text-sm sm:leading-6"
            :class="error ? 'ui-input-error' : ''"
            :type="'text'"
            :value="label"
            :step="'any'"
            :placeholder="$t(placeholder)"
            :disabled="true"
            />
        </div>
        <div v-else>
            <Listbox
                :multiple="props.multiple"
                @update:modelValue="value => emit('update:modelValue', value)"
                :model-value="props.modelValue"
                v-slot="{ open }">
                <div class="ui-select-root relative" :class="{ 'ui-select-root-open': open }">
                    <ListboxButton
                    class="ui-select-btn relative w-full cursor-default py-2 pe-10 ps-5 text-start focus:outline-none sm:text-sm"
                    :class="error ? 'ui-input-error' : ''"
                    >
                    <span class="block truncate" v-if="label">{{ label }}</span>
                    <span v-else class="text-gray-500">{{ $t(props.placeholder) }}</span>
                    <span
                        class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-2"
                    >
                        <ChevronUpDownIcon
                        class="h-5 w-5 text-gray-400"
                        aria-hidden="true"
                        />
                    </span>
                    </ListboxButton>

                    <transition
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                    >
                    <ListboxOptions
                        :class="[
                            'ui-select-options ui-layer-dropdown absolute max-h-60 w-full overflow-auto py-1 text-base focus:outline-none sm:text-sm',
                            props.dropdownUp ? 'bottom-full mb-1' : 'mt-1',
                            optionClassName
                        ]"
                    >
                        <ListboxOption
                        v-slot="{ active, selected }"
                        v-for="option in normalizedOptions"
                        :key="`${option.value}-${resolveOptionLabel(option)}`"
                        :value="option.value"
                        as="template"
                        >
                        <li :class="[active ? 'ui-select-option ui-select-option-active' : 'ui-select-option','relative cursor-default select-none py-2 ps-10 pe-4']">
                            <span :class="[selected ? 'font-medium' : 'font-normal', 'block truncate']">{{ resolveOptionLabel(option) }}</span>
                            <span v-if="selected" class="ui-select-option-check absolute inset-y-0 start-0 flex items-center ps-3">
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                        </ListboxOption>
                    </ListboxOptions>
                    </transition>
                </div>
            </Listbox>
        </div>
        <div v-if="error" class="ui-form-error">{{ error }}</div>
    </div>
</template>

