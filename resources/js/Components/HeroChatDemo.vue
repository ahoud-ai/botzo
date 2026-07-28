<script setup>
    import { ref, nextTick, onMounted, onUnmounted } from 'vue';
    import { useI18n } from 'vue-i18n';

    const { t } = useI18n();

    const SCRIPT = [
        { role: 'bot', key: 'Hi there! This is Botzo — how can I help you today?' },
        { role: 'user', key: 'I run an online store and I want customer inquiries on WhatsApp answered automatically around the clock — can you do that?' },
        { role: 'bot', key: 'Absolutely! Botzo connects your WhatsApp Business number to a smart assistant that replies to your customers instantly and works around the clock.' },
        { role: 'user', key: 'Great — can it also track orders and send shipping updates to customers automatically?' },
        { role: 'bot', key: 'Yes — it sends order confirmations and shipping updates as they happen, and even reminds customers about items left in their cart.' },
    ];

    const visible = ref([]);
    const showTyping = ref(false);
    const isClearing = ref(false);
    const scrollEl = ref(null);

    let timers = [];
    let uid = 0;

    const after = (delay, fn) => {
        timers.push(setTimeout(fn, delay));
    };

    const clearTimers = () => {
        timers.forEach(clearTimeout);
        timers = [];
    };

    const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

    const scrollToBottom = () => {
        nextTick(() => {
            if (scrollEl.value) {
                scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
            }
        });
    };

    const pushMessage = (role, text) => {
        const msg = { id: ++uid, role, text, read: false };
        visible.value.push(msg);
        scrollToBottom();
        if (role === 'user') {
            after(rand(500, 900), () => {
                msg.read = true;
            });
        }
        return msg;
    };

    const playCycle = () => {
        visible.value = [];
        showTyping.value = false;

        let elapsed = 500;

        SCRIPT.forEach((line, index) => {
            if (line.role === 'bot' && index > 0) {
                after(elapsed, () => {
                    showTyping.value = true;
                    scrollToBottom();
                });
                elapsed += rand(1100, 1800);
                after(elapsed, () => {
                    showTyping.value = false;
                    pushMessage('bot', t(line.key));
                });
                elapsed += rand(1300, 1900);
            } else {
                after(elapsed, () => {
                    pushMessage(line.role, t(line.key));
                });
                elapsed += line.role === 'bot' ? 1100 : rand(1400, 2000);
            }
        });

        const holdUntil = elapsed + 4200;
        after(holdUntil, () => {
            isClearing.value = true;
            after(380, () => {
                isClearing.value = false;
                playCycle();
            });
        });
    };

    onMounted(() => {
        playCycle();
    });

    onUnmounted(() => {
        clearTimers();
    });
</script>

<template>
    <div
        class="relative hidden h-[420px] w-full max-w-[682px] items-start justify-center overflow-hidden rounded-3xl bg-gradient-to-b from-white from-[-40%] to-[#d4ffdf] dark:to-[#25d366] md:flex lg:h-[625px]"
    >
        <img
            src="/images/hero/overview-bg-light.png"
            class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-[55%] dark:hidden"
            alt=""
            aria-hidden="true"
        />
        <img
            src="/images/hero/overview-bg-dark.png"
            class="pointer-events-none absolute inset-0 hidden h-full w-full object-cover opacity-[55%] dark:block"
            alt=""
            aria-hidden="true"
        />

        <div ref="scrollEl" class="chat-scroll relative h-full w-full overflow-y-auto" style="direction: ltr">
            <TransitionGroup
                tag="div"
                name="bubble-in"
                class="mx-auto flex w-full max-w-[616px] flex-col items-center gap-4 px-4 py-8 transition-opacity duration-300"
                :class="isClearing ? 'opacity-0' : 'opacity-100'"
            >
                <div
                    v-for="msg in visible"
                    :key="msg.id"
                    class="flex w-full items-center gap-4"
                    :class="msg.role === 'bot' ? 'justify-end' : 'justify-start'"
                >
                    <template v-if="msg.role === 'bot'">
                        <div class="w-[360px] max-w-[65%] rounded-tl-2xl rounded-tr-2xl rounded-bl-[4px] rounded-br-2xl bg-white p-4 shadow-[0_1px_1px_rgba(0,0,0,0.25)]">
                            <p class="text-right text-sm leading-[21px] tracking-[0.14px] text-black" dir="auto">
                                {{ msg.text }}
                            </p>
                        </div>
                        <div class="flex h-[63px] w-[63px] shrink-0 items-center justify-center rounded-full bg-white dark:bg-[#0a0f17]">
                            <img src="/images/hero/botzo-chat-logo.svg" class="h-[31.39px] w-[32.18px]" alt="Botzo" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="w-full max-w-[360px] rounded-tl-lg rounded-tr-lg rounded-bl-[2px] rounded-br-lg bg-[#25d366] p-4 dark:bg-[#0a0f17]">
                            <p class="text-right text-lg leading-[27px] tracking-[0.18px] text-black dark:text-white" dir="auto">
                                {{ msg.text }}
                            </p>
                            <div class="mt-1 flex justify-end">
                                <svg
                                    viewBox="0 0 16 11"
                                    class="h-[10px] w-[15px] transition-colors duration-300"
                                    :class="msg.read ? 'text-[#34b7f1]' : 'text-black/40 dark:text-white/40'"
                                    fill="none"
                                >
                                    <path d="M1 5.5 4.2 8.7 9.8 1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M5.6 5.5 8.8 8.7 14.4 1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                    </template>
                </div>

                <div v-if="showTyping" key="typing" class="flex w-full items-center justify-end gap-4">
                    <div class="flex items-center gap-1 rounded-tl-2xl rounded-tr-2xl rounded-bl-[4px] rounded-br-2xl bg-white px-4 py-[15px] shadow-[0_1px_1px_rgba(0,0,0,0.25)]">
                        <span class="typing-dot h-[7px] w-[7px] rounded-full bg-[#e6edf3] dark:bg-[#94a3b8]"></span>
                        <span class="typing-dot typing-dot--2 h-[7px] w-[7px] rounded-full bg-[#e6edf3] dark:bg-[#94a3b8]"></span>
                        <span class="typing-dot typing-dot--3 h-[7px] w-[7px] rounded-full bg-[#e6edf3] dark:bg-[#94a3b8]"></span>
                    </div>
                    <div class="flex h-[63px] w-[63px] shrink-0 items-center justify-center rounded-full bg-white dark:bg-[#0a0f17]">
                        <img src="/images/hero/botzo-chat-logo.svg" class="h-[31.39px] w-[32.18px]" alt="Botzo" />
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>

<style scoped>
    .chat-scroll {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .chat-scroll::-webkit-scrollbar {
        display: none;
    }

    .bubble-in-enter-active {
        animation: bubbleIn 0.32s cubic-bezier(0.2, 0.8, 0.3, 1) both;
    }

    @keyframes bubbleIn {
        from {
            opacity: 0;
            transform: scale(0.86) translateY(8px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .typing-dot {
        animation: dotBounce 1.1s ease-in-out infinite;
    }
    .typing-dot--2 {
        animation-delay: 0.15s;
    }
    .typing-dot--3 {
        animation-delay: 0.3s;
    }

    @keyframes dotBounce {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.5;
        }
        30% {
            transform: translateY(-5px);
            opacity: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .bubble-in-enter-active {
            animation: none;
        }
        .typing-dot {
            animation: none;
            opacity: 0.8;
        }
    }
</style>
