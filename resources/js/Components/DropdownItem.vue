<script setup>
    import { MenuItem } from '@headlessui/vue';
    import { Link } from "@inertiajs/vue3";
    
    const props = defineProps({
        as: {
            type: String,
            default: "a"
        }
    })
</script>
<template>
    <MenuItem v-slot="{ active }">
        <component v-if="props.as != 'a'"
            :is="props.as"
            :class="[
            active ? 'ui-dropdown-item ui-dropdown-item-active' : 'ui-dropdown-item',
            'group flex w-full rounded-md px-2 py-2 text-sm text-start',
            ]"
        >
            <slot />
        </component>
        <Link v-else
            :is="props.as"
            :class="[
            active ? 'ui-dropdown-item ui-dropdown-item-active' : 'ui-dropdown-item',
            'group flex w-full rounded-md px-2 py-2 text-sm',
            ]"
        >
            <slot />
        </Link>
    </MenuItem>
</template>
