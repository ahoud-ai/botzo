<template>
    <AppLayout :immersive="immersiveWorkspace">
        <div
            class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)]"
            :class="[
                studioMode ? 'md:px-0' : 'md:ps-8 md:pe-0',
                boardOnly ? '!p-0 min-h-screen' : (studioMode ? 'min-h-screen px-0' : ''),
            ]"
        >
            <div v-if="!studioMode" class="flex justify-between">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Automation') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Respond automatically to messages based on your own criteria') }}</span>
                    </p>
                </div>
            </div>

            <div class="min-h-0" :class="studioMode ? '' : 'mt-4 flex gap-4'">
                <div v-if="!studioMode" class="hidden md:block md:w-[240px] md:shrink-0">
                    <div class="rounded-2xl border border-slate-200 bg-white p-2">
                        <ul class="space-y-1 text-sm">
                            <li class="truncate rounded-xl px-1 transition hover:bg-slate-50 hover:text-black" :class="$page.url === '/automation/basic' ? 'bg-slate-50 text-black' : ''">
                                <Link rel="noopener noreferrer" href="/automation/basic" class="flex items-center gap-x-3 rounded-xl px-3 py-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M12 15a3 3 0 1 0 0-6a3 3 0 0 0 0 6"/><path d="m19.622 10.395l-1.097-2.65L20 6l-2-2l-1.735 1.483l-2.707-1.113L12.935 2h-1.954l-.632 2.401l-2.645 1.115L6 4L4 6l1.453 1.789l-1.08 2.657L2 11v2l2.401.656L5.516 16.3L4 18l2 2l1.791-1.46l2.606 1.072L11 22h2l.604-2.387l2.651-1.098C16.697 18.832 18 20 18 20l2-2l-1.484-1.75l1.098-2.652l2.386-.62V11z"/></g></svg>
                                <span>{{ $t('Basic Replies') }}</span>
                            </Link>
                        </li>
                            <li v-if="$page.props.features?.flowBuilderV2" class="truncate rounded-xl px-1 transition hover:bg-slate-50 hover:text-black" :class="$page.url.startsWith('/automation/flows') ? 'bg-slate-50 text-black' : ''">
                                <Link rel="noopener noreferrer" href="/automation/flows" class="flex items-center gap-x-3 rounded-xl px-3 py-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.5 6h7"/><path d="M6 8.5v7"/><path d="m8 16l8-8"/></g></svg>
                                <span>{{ $t('Automations') }}</span>
                            </Link>
                        </li>
                            <li v-if="aimodule" class="truncate rounded-xl px-1 transition hover:bg-slate-50 hover:text-black" :class="$page.url.startsWith('/automation/ai') ? 'bg-slate-50 text-black' : ''">
                                <Link rel="noopener noreferrer" href="/automation/ai" class="flex items-center gap-x-3 rounded-xl px-3 py-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 14 14"><g fill="none" stroke="black" stroke-linecap="round" stroke-linejoin="round"><path d="M6.022 4.347a18.452 18.452 0 0 0-1.93 1.686C1.248 8.877-.192 12.046.874 13.113c1.066 1.066 4.236-.375 7.079-3.218a18.452 18.452 0 0 0 1.686-1.931"/><path d="M9.639 7.964c1.677 2.226 2.36 4.32 1.532 5.148c-1.067 1.067-4.236-.374-7.08-3.217C1.249 7.05-.191 3.882.875 2.815c.828-.827 2.922-.144 5.148 1.532"/><path d="M5.522 7.964a.5.5 0 1 0 1 0a.5.5 0 0 0-1 0m2.51-4.354c-.315-.055-.315-.506 0-.56a2.843 2.843 0 0 0 2.29-2.193L10.34.77c.068-.31.51-.312.58-.003l.024.101a2.858 2.858 0 0 0 2.296 2.18c.316.055.316.509 0 .563a2.858 2.858 0 0 0-2.296 2.18l-.024.101c-.07.31-.512.308-.58-.002l-.02-.087A2.843 2.843 0 0 0 8.03 3.61Z"/></g></svg>
                                <div class="overflow-hidden text-ellipsis">{{ $t('AI Reply Assistant') }}</div>
                            </Link>
                        </li>
                        </ul>
                    </div>
                </div>

                <div class="min-w-0 text-[var(--ui-text)]" :class="studioMode ? '' : 'md:flex-1 md:px-6'">
                    <slot />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import AppLayout from './../Layout/App.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    aimodule: {
        type: Boolean,
        default: false,
    },
    studioMode: {
        type: Boolean,
        default: false,
    },
    boardOnly: {
        type: Boolean,
        default: false,
    },
});

const immersiveWorkspace = computed(() => props.studioMode || props.boardOnly);
</script>
