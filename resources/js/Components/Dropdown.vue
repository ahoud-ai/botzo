<script setup>
    import { computed } from 'vue';
    import { Menu, MenuButton, MenuItems } from '@headlessui/vue';
    
    const props = defineProps({
        align: {
            type: String,
            default: "end"
        }
    });

    const alignClasses = computed(() => {
        if (props.align === 'top-left' || props.align === 'top-start') {
            return 'ui-dropdown-top-start ui-dropdown-start';
        }

        if (props.align === 'top-right' || props.align === 'top-end') {
            return 'ui-dropdown-top-end ui-dropdown-end';
        }

        if (props.align === 'left' || props.align === 'start') {
            return 'ui-dropdown-start';
        }

        return 'ui-dropdown-end';
    });
</script>
<template>
    <Menu as="div" class="relative inline-block text-start">
        <div>
            <MenuButton as="template">
                <slot />
            </MenuButton>
        </div>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <MenuItems
                :class="alignClasses"
                class="ui-dropdown-menu ui-dropdown-layer absolute mt-2 w-40 divide-y focus:outline-none">
                
                <slot name="items" />
            </MenuItems>
        </transition>
    </Menu>
</template>
