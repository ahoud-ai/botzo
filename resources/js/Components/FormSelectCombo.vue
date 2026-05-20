<script setup>
    import {ref, computed, watch, onMounted, onBeforeUnmount, nextTick} from "vue";
    import { useI18n } from "vue-i18n";
    import { buildI18nLookupCandidates } from "@/Utils/i18nLookup";

    const emit = defineEmits(["update:modelValue"]);
    const { t, te } = useI18n();

    const props = defineProps({
        modelValue: Object,
        name: String,
        className: String,
        placeholder: {
            type: String, 
            default: 'Select option'
        },
        options: {
            type: Array,
            default: () => [],
        },
        loadOptions: Function,
        createOption: Function,
        error: String,
    });

    const isOpen = ref(false);
    const query = ref("");
    const inputRef = ref(null);
    const dropdownRef = ref(null);
    const highlightedIndex = ref(-1);
    const options = ref([]);
    const isLoading = ref(false);

    const resolveOptionLabel = (option) => {
        const raw = String(option?.label ?? option?.rawLabel ?? option?.value ?? "").trim();
        if (!raw) {
            return "";
        }

        if (option?.disableTranslation) {
            return raw;
        }

        for (const candidate of buildI18nLookupCandidates(raw)) {
            if (te(candidate)) {
                return t(candidate);
            }
        }

        return raw;
    };

    // Use computed to get options - prefer props.options if loadOptions is not provided
    const availableOptions = computed(() => {
        if (props.loadOptions) {
            return options.value; // Use dynamically loaded options
        } else {
            return props.options || []; // Use static options from props
        }
    });

    // Initialize options from props if loadOptions is not provided
    watch(() => props.options, (newOptions) => {
        if (!props.loadOptions && newOptions && Array.isArray(newOptions)) {
            options.value = newOptions;
        }
    }, { immediate: true, deep: true });

    const queryOption = computed(() => {
        return query.value === ""
            ? null
            : {
                missing: true,
                label: query.value,
            };
    });

    // Watch query for dynamic loading
    watch(query, q => {
        if (props.loadOptions) {
            isLoading.value = true;
            
            props.loadOptions(q, results => {
                options.value = Object.values(results).map(user => {
                    return {
                        value: user.id,
                        label: `${user.first_name} ${user.last_name}`
                    };
                });

                isLoading.value = false;
            });
        }
    },
    {immediate: true}
    );

    let filteredOptions = computed(() => {
        const opts = availableOptions.value;
        return query.value === ""
            ? opts
            : opts.filter(option =>
                `${String(option?.label ?? "")} ${resolveOptionLabel(option)}`
                .toLowerCase()
                .replace(/\s+/g, "")
                .includes(query.value.toLowerCase().replace(/\s+/g, ""))
            );
    });

    const displayValue = computed(() => {
        return resolveOptionLabel(props.modelValue) || "";
    });

    const isSelected = (option) => {
        if (!props.modelValue || !option) return false;
        return props.modelValue.value === option.value;
    };

    function handleSelect(option) {
        emit("update:modelValue", option);
        query.value = "";
        isOpen.value = false;
        highlightedIndex.value = -1;

        if (props.createOption && option?.missing) {
            isLoading.value = true;
            props.createOption(option, newOption => {
                emit("update:modelValue", newOption);
                isLoading.value = false;
                isOpen.value = false;
            });
        }
    }

    function handleInputFocus() {
        isOpen.value = true;
        query.value = displayValue.value;
        highlightedIndex.value = -1;
    }

    function handleInputBlur() {
        // Delay closing to allow click events to fire
        setTimeout(() => {
            if (!dropdownRef.value?.matches(':hover')) {
                isOpen.value = false;
                query.value = "";
                highlightedIndex.value = -1;
            }
        }, 200);
    }

    function handleInputChange(e) {
        query.value = e.target.value;
        isOpen.value = true;
        highlightedIndex.value = -1;
    }

    function handleKeyDown(e) {
        if (!isOpen.value && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            isOpen.value = true;
            return;
        }

        if (!isOpen.value) return;

        const optionsToNavigate = props.createOption && queryOption.value && !filteredOptions.value.length 
            ? [queryOption.value, ...filteredOptions.value]
            : filteredOptions.value;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                highlightedIndex.value = Math.min(highlightedIndex.value + 1, optionsToNavigate.length - 1);
                scrollToHighlighted();
                break;
            case 'ArrowUp':
                e.preventDefault();
                highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
                scrollToHighlighted();
                break;
            case 'Enter':
                e.preventDefault();
                if (highlightedIndex.value >= 0 && optionsToNavigate[highlightedIndex.value]) {
                    handleSelect(optionsToNavigate[highlightedIndex.value]);
                }
                break;
            case 'Escape':
                e.preventDefault();
                isOpen.value = false;
                query.value = "";
                highlightedIndex.value = -1;
                inputRef.value?.blur();
                break;
        }
    }

    function scrollToHighlighted() {
        nextTick(() => {
            const highlightedElement = dropdownRef.value?.querySelector(`[data-index="${highlightedIndex.value}"]`);
            if (highlightedElement) {
                highlightedElement.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        });
    }

    function handleClickOutside(event) {
        if (dropdownRef.value && !dropdownRef.value.contains(event.target) && 
            inputRef.value && !inputRef.value.contains(event.target)) {
            isOpen.value = false;
            query.value = "";
            highlightedIndex.value = -1;
        }
    }

    onMounted(() => {
        document.addEventListener('click', handleClickOutside);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('click', handleClickOutside);
    });

    // Reset query when closing
    watch(isOpen, (open) => {
        if (!open) {
            query.value = "";
            highlightedIndex.value = -1;
        }
    });
</script>

<template>
    <div :class="className">
        <label v-if="name" for="name" class="ui-form-label">{{ name }}</label>
        <div class="relative">
            <div 
                class="ui-select-btn relative w-full cursor-default overflow-hidden rounded-lg text-start focus:outline-none sm:text-sm transition-all"
                :class="error ? 'ui-input-error' : ''"
            >
                <input
                    ref="inputRef"
                    type="text"
                    :value="isOpen ? query : displayValue"
                    :placeholder="$t(placeholder)"
                    @focus="handleInputFocus"
                    @blur="handleInputBlur"
                    @input="handleInputChange"
                    @keydown="handleKeyDown"
                    class="w-full border-none py-2 ps-3 pe-10 text-sm leading-5 text-[var(--ui-text)] focus:outline-none focus:ring-0 bg-transparent"
                />
                <button
                    type="button"
                    @click="isOpen = !isOpen"
                    class="absolute inset-y-0 end-0 flex items-center pe-2 text-gray-400 hover:text-gray-500"
                >
                    <svg 
                        class="h-5 w-5 transition-transform duration-200"
                        :class="{ 'rotate-180': isOpen }"
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                    </svg>
                </button>
            </div>
            
            <!-- Dropdown -->
            <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="isOpen"
                    ref="dropdownRef"
                    class="ui-select-options ui-layer-dropdown absolute mt-1 max-h-60 w-full overflow-auto rounded-md py-1 text-base focus:outline-none sm:text-sm"
                    @mouseleave="highlightedIndex = -1"
                >
                    <!-- Nothing found -->
                    <div
                        v-if="
                            filteredOptions.length === 0 &&
                            !isLoading &&
                            !queryOption &&
                            !props.createOption
                        "
                        class="relative cursor-default select-none py-2 px-4 text-gray-700"
                    >
                        {{ $t('Nothing found') }}
                    </div>

                    <!-- Loading -->
                    <div
                        v-if="isLoading"
                        class="relative cursor-default select-none py-2 px-4 text-gray-700"
                    >
                        {{ $t('Loading...') }}
                    </div>

                    <!-- Create option -->
                    <div
                        v-if="
                            !isLoading &&
                            queryOption && 
                            !filteredOptions.length && 
                            props.createOption
                        "
                        :data-index="0"
                        @mouseenter="highlightedIndex = 0"
                        @click="handleSelect(queryOption)"
                        class="relative cursor-pointer select-none py-2 ps-10 pe-4 transition-colors"
                        :class="{
                            'ui-select-option-active': highlightedIndex === 0,
                            'ui-select-option': highlightedIndex !== 0,
                        }"
                    >
                        {{ $t('Create') }} "{{ queryOption.label }}"
                    </div>

                    <!-- Options list -->
                    <template v-if="!isLoading">
                        <div
                            v-for="(option, index) in filteredOptions"
                            :key="option.value"
                            :data-index="index"
                            @mouseenter="highlightedIndex = index"
                            @click="handleSelect(option)"
                            class="relative cursor-pointer select-none py-2 ps-10 pe-4 transition-colors"
                            :class="{
                                'ui-select-option-active': highlightedIndex === index,
                                'ui-select-option': highlightedIndex !== index,
                            }"
                        >
                            <span
                                class="block truncate"
                                :class="{
                                    'font-medium': isSelected(option), 
                                    'font-normal': !isSelected(option)
                                }"
                            >
                                {{ resolveOptionLabel(option) }}
                            </span>
                            <span
                                v-if="isSelected(option)"
                                class="absolute inset-y-0 start-0 flex items-center ps-3"
                                :class="{
                                    'text-white': highlightedIndex === index,
                                    'ui-select-option-check': highlightedIndex !== index
                                }"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        </div>
                    </template>
                </div>
            </Transition>
        </div>
        <div v-if="error" class="form-error ui-danger-text text-xs mt-1">{{ error }}</div>
    </div>
</template>

