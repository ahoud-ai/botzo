<script setup>
// A WhatsApp-Business-style notification toast — pops in, holds, fades out,
// then cycles to the next message in `items` and repeats forever. Positioned
// by whatever class the parent passes onto this component's root element.
import { ref, computed, onMounted, onUnmounted } from "vue";
import { MessageCircle } from "lucide-vue-next";

const props = defineProps({
  items: { type: Array, required: true },
  delay: { type: Number, default: 0 },
  duration: { type: Number, default: 5000 },
});

const index = ref(0);
const current = computed(() => props.items[index.value % props.items.length]);
const initial = computed(() => (current.value.name || "").trim().charAt(0) || "؟");

let intervalId = null;
let timeoutId = null;

onMounted(() => {
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) return;
  timeoutId = setTimeout(() => {
    intervalId = setInterval(() => {
      index.value = (index.value + 1) % props.items.length;
    }, props.duration);
  }, props.delay);
});

onUnmounted(() => {
  clearTimeout(timeoutId);
  clearInterval(intervalId);
});
</script>

<template>
  <div
    class="notification-toast pointer-events-none flex w-[236px] items-center gap-2.5 rounded-2xl bg-white px-3.5 py-3 shadow-[0_10px_30px_-8px_rgba(15,23,42,0.18)] dark:bg-[#111a14] dark:shadow-[0_10px_30px_-8px_rgba(0,0,0,0.5)]"
    :style="{ animationDelay: `${delay}ms`, animationDuration: `${duration}ms` }"
    dir="rtl"
  >
    <div
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
      :style="{ backgroundColor: current.color }"
    >
      {{ initial }}
    </div>
    <div class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
      <div class="flex w-full items-center justify-between gap-2">
        <span class="truncate text-xs font-semibold text-black dark:text-white">{{ current.name }}</span>
        <span class="shrink-0 text-[10px] text-[#8899aa]">{{ current.time }}</span>
      </div>
      <span class="w-full truncate text-xs text-[#5b6b7a] dark:text-[#94a3b8]">{{ current.message }}</span>
    </div>
    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#25d366]">
      <MessageCircle :size="11" class="text-white" stroke-width="2.5" />
    </div>
  </div>
</template>

<style scoped>
.notification-toast {
  animation-name: toast-cycle;
  animation-timing-function: ease-in-out;
  animation-iteration-count: infinite;
  animation-fill-mode: both;
}

@keyframes toast-cycle {
  0% {
    opacity: 0;
    transform: translateY(10px) scale(0.96);
  }
  8% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  70% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  85%,
  100% {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
  }
}

@media (prefers-reduced-motion: reduce) {
  .notification-toast {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
