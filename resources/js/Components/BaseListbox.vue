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
      placeholder: {
        type: String, 
        default: 'Select option'
      },
      multiple: Boolean
    })

    const emit = defineEmits(['update:modelValue'])
    const { t, te } = useI18n();

    const resolveOptionLabel = (option) => {
      const raw = String(option?.label ?? option?.rawLabel ?? option?.value ?? '').trim();
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
    <Listbox 
      :multiple="props.multiple"
      @update:modelValue="value => emit('update:modelValue', value)"
      :model-value="props.modelValue">
      <div class="relative mt-1">
        <ListboxButton
          class="relative w-full cursor-default rounded-lg bg-white py-2 px-5 pe-10 shadow-sm text-start ring-1 ring-inset ring-gray-300 focus:outline-none focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75 focus-visible:ring-offset-2 focus-visible:ring-offset-orange-300 sm:text-sm"
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
            class="ui-select-options ui-layer-dropdown absolute mt-1 max-h-60 w-full overflow-auto rounded-md py-1 text-base focus:outline-none sm:text-sm"
          >
            <ListboxOption
              v-slot="{ active, selected }"
              v-for="option in normalizedOptions"
              :key="option.label"
              :value="option.value"
              as="template"
            >
              <li
                :class="[
                  active ? 'bg-amber-100 text-amber-900' : 'text-gray-900',
                  'relative cursor-default select-none py-2 ps-10 pe-4',
                ]"
              >
                <span
                  :class="[
                    selected ? 'font-medium' : 'font-normal',
                    'block truncate',
                  ]"
                  >{{ resolveOptionLabel(option) }}</span
                >
                <span
                  v-if="selected"
                  class="absolute inset-y-0 start-0 flex items-center ps-3 text-amber-600"
                >
                  <CheckIcon class="h-5 w-5" aria-hidden="true" />
                </span>
              </li>
            </ListboxOption>
          </ListboxOptions>
        </transition>
      </div>
    </Listbox>
</template>
