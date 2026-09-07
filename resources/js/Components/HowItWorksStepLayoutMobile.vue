<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";

// Continuous step-cycling animation for "How it works" (mobile layout).
// One active step at a time (bright/scaled/glowing), the other two dimmed.
// Each step plays its own inner sequence while active, then the section
// holds before advancing. Manual dots let a viewer jump straight to a step.
// Adapted from a design handoff (colors/timings/easings below) onto this
// component's REAL markup/copy/values — the phone number, stat labels and
// numeric targets are this component's existing content, only their reveal
// is animated; nothing was renamed or re-valued to match the handoff's own
// example content.

const PHONE_NUMBER = "+966 5X XXX XXXX";
const arabicIndicDigits = ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"];
const toArabicIndic = (n) => String(Math.round(n)).replace(/[0-9]/g, (d) => arabicIndicDigits[d]);

const stepLabels = ["الخطوة ١", "الخطوة ٢", "الخطوة ٣"];
const active = ref(0);
const forceArrowsDrawn = ref(false);

const step1 = reactive({ phoneText: "", showVerify: false, pressed: false });
const step2 = reactive({ revealed: 0 });
const step3 = reactive({ replyRateDisplay: "٠٪", conversionsDisplay: "٠٪" });

const prefersReducedMotionQuery = typeof window !== "undefined" ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
const prefersReducedMotion = () => prefersReducedMotionQuery?.matches ?? false;

const arrow1On = computed(() => active.value >= 1 || forceArrowsDrawn.value);
const arrow2On = computed(() => active.value >= 2 || forceArrowsDrawn.value);

// Traveling dot along each arrow — a persistent RAF loop (independent of the
// step-cycling timers above) so it keeps a steady 2.2s period regardless of
// which step is active; only its opacity is gated on whether that arrow is
// currently "on". Uses getPointAtLength, which needs a real <path> element,
// hence the static "9 9" dash staying on the path itself (never animated —
// the reveal comes from the clip-path wipe on the surrounding <rect> instead,
// which is what lets the dash pattern stay intact while still hiding/showing
// the arrow, unlike animating stroke-dashoffset against a short dasharray).
const arrow1Path = ref(null);
const arrow2Path = ref(null);
const arrow1Dot = ref(null);
const arrow2Dot = ref(null);
let dotAnimationFrame = null;
const DOT_PERIOD = 2200;

function startDotLoop() {
  const len1 = arrow1Path.value?.getTotalLength() ?? 0;
  const len2 = arrow2Path.value?.getTotalLength() ?? 0;

  const tick = (time) => {
    const p = (time % DOT_PERIOD) / DOT_PERIOD;

    if (arrow1On.value && arrow1Path.value && arrow1Dot.value) {
      const point = arrow1Path.value.getPointAtLength(len1 * p);
      arrow1Dot.value.setAttribute("cx", point.x);
      arrow1Dot.value.setAttribute("cy", point.y);
      arrow1Dot.value.style.opacity = String(Math.sin(p * Math.PI));
    } else if (arrow1Dot.value) {
      arrow1Dot.value.style.opacity = "0";
    }

    if (arrow2On.value && arrow2Path.value && arrow2Dot.value) {
      const point = arrow2Path.value.getPointAtLength(len2 * p);
      arrow2Dot.value.setAttribute("cx", point.x);
      arrow2Dot.value.setAttribute("cy", point.y);
      arrow2Dot.value.style.opacity = String(Math.sin(p * Math.PI));
    } else if (arrow2Dot.value) {
      arrow2Dot.value.style.opacity = "0";
    }

    dotAnimationFrame = requestAnimationFrame(tick);
  };
  dotAnimationFrame = requestAnimationFrame(tick);
}

let runToken = 0;
const timeouts = new Set();
const rafs = new Set();

function wait(ms, token) {
  return new Promise((resolve) => {
    const id = setTimeout(() => {
      timeouts.delete(id);
      resolve();
    }, ms);
    timeouts.add(id);
  }).then(() => token === runToken);
}

function countUp(setter, target, duration, token) {
  return new Promise((resolve) => {
    const start = performance.now();
    const tick = (now) => {
      if (token !== runToken) return resolve(false);
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      setter(toArabicIndic(eased * target) + "٪");
      if (progress < 1) {
        const id = requestAnimationFrame(tick);
        rafs.add(id);
      } else {
        resolve(true);
      }
    };
    const id = requestAnimationFrame(tick);
    rafs.add(id);
  });
}

async function playStep1(token) {
  step1.phoneText = "";
  step1.showVerify = false;
  step1.pressed = false;
  if (!(await wait(500, token))) return;

  for (let i = 1; i <= PHONE_NUMBER.length; i++) {
    if (token !== runToken) return;
    step1.phoneText = PHONE_NUMBER.slice(0, i);
    if (!(await wait(55, token))) return;
  }

  if (!(await wait(400, token))) return;
  step1.pressed = true;
  if (!(await wait(280, token))) return;
  step1.pressed = false;

  if (!(await wait(500, token))) return;
  step1.showVerify = true;

  await wait(900, token);
}

async function playStep2(token) {
  step2.revealed = 0;
  if (!(await wait(380, token))) return;
  for (let i = 1; i <= 4; i++) {
    if (token !== runToken) return;
    step2.revealed = i;
    if (!(await wait(380, token))) return;
  }
  await wait(1100, token);
}

async function playStep3(token) {
  step3.replyRateDisplay = "٠٪";
  step3.conversionsDisplay = "٠٪";
  await Promise.all([
    countUp((v) => (step3.replyRateDisplay = v), 94, 1500, token),
    countUp((v) => (step3.conversionsDisplay = v), 31, 1500, token),
  ]);
  await wait(900, token);
}

async function runStep(index) {
  runToken += 1;
  const token = runToken;
  active.value = index;
  forceArrowsDrawn.value = false;

  if (index === 0) await playStep1(token);
  else if (index === 1) await playStep2(token);
  else await playStep3(token);

  if (token !== runToken) return;
  if (!(await wait(1400, token))) return;
  runStep((index + 1) % 3);
}

function goToStep(index) {
  if (prefersReducedMotion()) return;
  runStep(index);
}

function setFinalRestingState() {
  active.value = 0;
  forceArrowsDrawn.value = true;
  step1.phoneText = PHONE_NUMBER;
  step1.showVerify = true;
  step1.pressed = false;
  step2.revealed = 4;
  step3.replyRateDisplay = toArabicIndic(94) + "٪";
  step3.conversionsDisplay = toArabicIndic(31) + "٪";
}

onMounted(() => {
  if (prefersReducedMotion()) {
    setFinalRestingState();
    return;
  }
  runStep(0);
  startDotLoop();
});

onUnmounted(() => {
  runToken += 1;
  timeouts.forEach((id) => clearTimeout(id));
  timeouts.clear();
  rafs.forEach((id) => cancelAnimationFrame(id));
  rafs.clear();
  if (dotAnimationFrame) cancelAnimationFrame(dotAnimationFrame);
});

const nodeState = (index) => (step2.revealed > index ? "hw-node--revealed" : "hw-node--hidden");
const stubState = (index) => (step2.revealed > index ? "hw-stub--drawn" : "");
</script>

<template>
  <div class="flex w-full flex-col items-center gap-4">
    <!-- Step 1: Connect WhatsApp Business -->
    <div class="hw-card" :class="active === 0 ? 'hw-card--active' : 'hw-card--inactive'">
      <div class="hw-card__glow hw-card__glow--start" :class="{ 'hw-card__glow--on': active === 0 }" aria-hidden="true"></div>
      <div class="relative flex w-full flex-col items-end gap-3">
        <div class="flex w-full items-center justify-between">
          <div class="hw-badge" :class="active === 0 ? 'hw-badge--active' : 'hw-badge--inactive'">١</div>
          <span class="hw-eyebrow">{{ active === 0 ? "قيد التشغيل" : stepLabels[0] }}</span>
        </div>
        <div class="w-full text-right">
          <h3 dir="auto" class="text-xl font-semibold leading-[25px] text-white">
            1-{{ $t("Connect WhatsApp Business") }}
          </h3>
          <p class="mt-1.5 text-sm leading-6 text-[#b9cfc2]">
            {{ $t("Connect your number via official WhatsApp in minutes, with zero technical hassle.") }}
          </p>
        </div>
        <div class="hw-float hw-float--1 mx-auto w-full max-w-[319px]" :class="{ 'hw-float--dim': active !== 0 }">
          <div class="flex w-full flex-col items-start gap-3 rounded-2xl bg-black p-4">
            <div class="w-full">
              <p class="text-right text-xs leading-[18px] text-[#8899aa]">{{ $t("Mobile number") }}</p>
              <div class="hw-phone-input mt-2 w-full rounded-xl bg-[#0d1117] px-3.5 py-[11px]" :class="{ 'hw-phone-input--focused': step1.phoneText.length > 0 }">
                <p class="hw-mono flex items-center text-right text-sm leading-[21px] text-white" dir="ltr">
                  {{ step1.phoneText }}<span v-if="active === 0" class="hw-caret"></span>
                </p>
              </div>
            </div>
            <button
              type="button"
              tabindex="-1"
              class="hw-connect-btn w-full rounded-xl bg-[#25d366] px-4 py-3 text-center text-sm font-semibold leading-[21px] text-black"
              :class="{ 'hw-connect-btn--pressed': step1.pressed }"
            >
              <span class="hw-sweep" aria-hidden="true"></span>
              {{ $t("Connect now") }}
            </button>
            <div class="hw-verify-row flex w-full items-center justify-end gap-[10px] rounded-xl border-[1.5px] border-[#1e2a3a] px-[15.5px] py-[13.5px]" :class="{ 'hw-verify-row--shown': step1.showVerify }">
              <div class="flex flex-col items-end">
                <p class="text-sm font-bold leading-[21px] text-[#25d366]">{{ $t("Connected") }}</p>
                <p class="text-[11px] leading-[16.5px] text-[#8899aa]" dir="ltr">Botzo AI مرتبط بواتساب</p>
              </div>
              <div class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-[#25d366]">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M10.8346 3.25L4.8763 9.20833L2.16797 6.5" stroke="#06210f" stroke-width="1.625" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="hw-arrow-band">
      <svg viewBox="0 0 200 74" preserveAspectRatio="none" class="hw-arrow-svg" overflow="visible">
        <defs>
          <clipPath id="hw-clip-1" clipPathUnits="userSpaceOnUse">
            <rect
              x="0" y="-20" width="200" height="114"
              class="hw-clip-rect"
              :class="{ 'hw-clip-rect--on': arrow1On }"
              style="transform-origin: 0px 0px"
            />
          </clipPath>
        </defs>
        <g clip-path="url(#hw-clip-1)">
          <path ref="arrow1Path" class="hw-arrow-path" d="M 48 4 C 88 4 98 66 150 66" />
        </g>
        <circle ref="arrow1Dot" r="3.4" fill="#25d366" style="opacity: 0" />
      </svg>
    </div>

    <!-- Step 2: Design smart replies without code -->
    <div class="hw-card" :class="active === 1 ? 'hw-card--active' : 'hw-card--inactive'">
      <div class="hw-card__glow hw-card__glow--end" :class="{ 'hw-card__glow--on': active === 1 }" aria-hidden="true"></div>
      <div class="relative flex w-full flex-col items-end gap-3">
        <div class="flex w-full items-center justify-between">
          <div class="hw-badge" :class="active === 1 ? 'hw-badge--active' : 'hw-badge--inactive'">٢</div>
          <span class="hw-eyebrow">{{ active === 1 ? "قيد التشغيل" : stepLabels[1] }}</span>
        </div>
        <div class="w-full text-right">
          <h3 dir="auto" class="text-xl font-semibold leading-[25px] text-white">
            2-{{ $t("Design smart replies without code") }}
          </h3>
          <p class="mt-1.5 text-sm leading-6 text-[#b9cfc2]">
            {{ $t("Build flows with drag-and-drop, without writing a single line of code.") }}
          </p>
        </div>
        <div class="hw-float hw-float--2 w-full" :class="{ 'hw-float--dim': active !== 1 }">
          <div class="flex w-full flex-col items-center gap-0 rounded-2xl bg-black px-6 py-8">
            <div class="hw-node" :class="nodeState(0)">
              <span>👋</span>
              <span class="whitespace-nowrap">{{ $t("Welcome message") }}</span>
            </div>
            <div class="hw-stub" :class="stubState(1)"></div>
            <div class="hw-node" :class="nodeState(1)">
              <span>❓</span>
              <span class="whitespace-nowrap">{{ $t("Customer question?") }}</span>
            </div>
            <div class="hw-stub" :class="stubState(2)"></div>
            <div class="flex w-full items-center justify-center gap-3">
              <div class="hw-node" :class="nodeState(2)">
                <span>✓</span>
                <span class="whitespace-nowrap">{{ $t("Auto-reply") }}</span>
              </div>
              <div class="hw-node" :class="nodeState(3)">
                <span>👤</span>
                <span class="whitespace-nowrap">{{ $t("Transfer to agent") }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="hw-arrow-band">
      <svg viewBox="0 0 200 74" preserveAspectRatio="none" class="hw-arrow-svg" overflow="visible">
        <defs>
          <clipPath id="hw-clip-2" clipPathUnits="userSpaceOnUse">
            <rect
              x="0" y="-20" width="200" height="114"
              class="hw-clip-rect"
              :class="{ 'hw-clip-rect--on': arrow2On }"
              style="transform-origin: 200px 0px"
            />
          </clipPath>
        </defs>
        <g clip-path="url(#hw-clip-2)">
          <path ref="arrow2Path" class="hw-arrow-path" d="M 150 4 C 110 4 100 66 48 66" />
        </g>
        <circle ref="arrow2Dot" r="3.4" fill="#25d366" style="opacity: 0" />
      </svg>
    </div>

    <!-- Step 3: Monitor and improve -->
    <div class="hw-card" :class="active === 2 ? 'hw-card--active' : 'hw-card--inactive'">
      <div class="hw-card__glow hw-card__glow--start" :class="{ 'hw-card__glow--on': active === 2 }" aria-hidden="true"></div>
      <div class="relative flex w-full flex-col items-end gap-3">
        <div class="flex w-full items-center justify-between">
          <div class="hw-badge" :class="active === 2 ? 'hw-badge--active' : 'hw-badge--inactive'">٣</div>
          <span class="hw-eyebrow">{{ active === 2 ? "قيد التشغيل" : stepLabels[2] }}</span>
        </div>
        <div class="w-full text-right">
          <h3 dir="auto" class="text-xl font-semibold leading-[25px] text-white">
            3-{{ $t("Monitor and improve") }}
          </h3>
          <p class="mt-1.5 text-sm leading-6 text-[#b9cfc2]">
            {{ $t("A real-time analytics dashboard reveals what's working and what needs improvement.") }}
          </p>
        </div>
        <div class="hw-float hw-float--3 w-full" :class="{ 'hw-float--dim': active !== 2 }">
          <div class="flex w-full flex-col items-end rounded-2xl bg-black p-[22px]">
            <p class="w-full text-right text-sm leading-[21px] text-white">{{ $t("Overview") }}</p>
            <div class="mt-4 flex w-full items-start gap-[10px]" dir="ltr">
              <div class="flex flex-1 flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                <p class="w-full text-right text-[11px] leading-[16.5px] text-[#06210f]">{{ $t("Reply rate") }}</p>
                <p class="hw-mono w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-[#06210f]">{{ step3.replyRateDisplay }}</p>
              </div>
              <div class="flex flex-1 flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                <p class="w-full text-right text-[11px] leading-[16.5px] text-[#06210f]">{{ $t("Conversions") }}</p>
                <p class="hw-mono w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-[#06210f]">↑{{ step3.conversionsDisplay }}</p>
              </div>
            </div>
            <div class="mt-[18px] w-full">
              <p class="w-full text-right text-[11px] leading-[16.5px] text-white">{{ $t("Daily conversations") }}</p>
              <div class="mt-2.5 flex w-full items-end gap-1.5" style="height: 82px">
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 44.633px; --d: 0s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 54px; --d: 0.08s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 41.758px; --d: 0.16s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 63.359px; --d: 0.24s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 59.039px; --d: 0.32s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 67.68px; --d: 0.4s"></div>
                <div class="hw-bar min-w-px flex-1 rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 56.875px; --d: 0.48s"></div>
              </div>
              <div class="mt-1.5 flex w-full gap-1.5">
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Sun") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Mon") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Tue") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Wed") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Thu") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] font-bold leading-[13.5px] text-[#25d366]">{{ $t("Fri") }}</span>
                <span class="min-w-px flex-1 text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t("Sat") }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual step control -->
    <div class="mt-2 flex items-center gap-2" role="tablist" aria-label="How it works steps">
      <button
        v-for="i in 3"
        :key="i"
        type="button"
        role="tab"
        :aria-selected="active === i - 1"
        class="hw-dot"
        :class="{ 'hw-dot--active': active === i - 1 }"
        @click="goToStep(i - 1)"
      ></button>
    </div>
  </div>
</template>

<style scoped>
.hw-card {
  position: relative;
  overflow: hidden;
  border-radius: 24px;
  padding: 24px;
  width: 100%;
  transition: background 0.6s, border-color 0.6s, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.6s;
}

.hw-card--active {
  background: #0d2114;
  border: 1px solid rgba(37, 211, 102, 0.34);
  transform: scale(1);
  box-shadow: 0 30px 60px -40px rgba(37, 211, 102, 0.55);
}

.hw-card--inactive {
  background: #0b1a11;
  border: 1px solid rgba(255, 255, 255, 0.07);
  transform: scale(0.985);
  box-shadow: none;
}

.hw-card__glow {
  position: absolute;
  inset: 0;
  border-radius: 24px;
  pointer-events: none;
  overflow: hidden;
  opacity: 0;
  transition: opacity 0.7s;
}

.hw-card__glow--on {
  opacity: 1;
}

.hw-card__glow::before {
  content: "";
  position: absolute;
  top: -70%;
  width: 320px;
  height: 320px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(37, 211, 102, 0.16) 0%, rgba(37, 211, 102, 0) 70%);
}

.hw-card__glow--start::before {
  left: -10%;
}

.hw-card__glow--end::before {
  right: -10%;
}

.hw-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  font-family: "IBM Plex Mono", monospace;
  font-size: 12.5px;
  font-weight: 600;
  transition: background 0.5s, color 0.5s;
}

.hw-badge--active {
  background: #25d366;
  color: #06210f;
}

.hw-badge--inactive {
  background: rgba(255, 255, 255, 0.06);
  color: #7a8f82;
}

.hw-eyebrow {
  font-size: 11.5px;
  color: #a8bdb0;
}

.hw-float {
  animation: hw-float ease-in-out infinite;
  opacity: 1;
  transition: opacity 0.6s;
}

.hw-float--dim {
  opacity: 0.62;
}

.hw-float--1 {
  animation-duration: 6s;
  animation-delay: 0s;
}

.hw-float--2 {
  animation-duration: 7s;
  animation-delay: 0.6s;
}

.hw-float--3 {
  animation-duration: 6.5s;
  animation-delay: 1.2s;
}

@keyframes hw-float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}

.hw-mono {
  font-family: "IBM Plex Mono", monospace;
  letter-spacing: 0.04em;
  font-variant-numeric: tabular-nums;
}

.hw-phone-input {
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: border-color 0.4s;
}

.hw-phone-input--focused {
  border-color: rgba(37, 211, 102, 0.45);
}

.hw-caret {
  display: inline-block;
  width: 1.5px;
  height: 16px;
  margin-inline-start: 2px;
  background: #25d366;
  animation: hw-caret 1s step-end infinite;
}

@keyframes hw-caret {
  0%,
  49% {
    opacity: 1;
  }
  50%,
  100% {
    opacity: 0;
  }
}

.hw-connect-btn {
  position: relative;
  overflow: hidden;
  transform: scale(1);
  transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.hw-connect-btn--pressed {
  transform: scale(0.955);
}

.hw-sweep {
  position: absolute;
  inset: 0;
  width: 55px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.45), transparent);
  animation: hw-sweep 2.8s ease-in-out infinite;
}

@keyframes hw-sweep {
  0% {
    transform: translateX(-120%);
  }
  100% {
    transform: translateX(220%);
  }
}

.hw-verify-row {
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 0.5s, transform 0.5s, border-color 0.5s;
}

.hw-verify-row--shown {
  opacity: 1;
  transform: translateY(0);
  border-color: rgba(37, 211, 102, 0.4);
}

.hw-node {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 600;
  padding: 8px 14px;
  border-radius: 9px;
  margin: 3px 0;
  border: 1px solid rgba(255, 255, 255, 0.07);
  transition: all 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.hw-node--hidden {
  background: rgba(255, 255, 255, 0.04);
  color: #4d5c53;
  opacity: 0.5;
  transform: translateY(6px) scale(0.96);
}

.hw-node--revealed {
  background: rgba(37, 211, 102, 0.14);
  color: #8ef0b4;
  border-color: rgba(37, 211, 102, 0.4);
  opacity: 1;
  transform: translateY(0) scale(1);
}

.hw-stub {
  width: 1.5px;
  height: 15px;
  background: rgba(37, 211, 102, 0.4);
  transform-origin: top;
  transform: scaleY(0);
  transition: transform 0.5s ease-out;
}

.hw-stub--drawn {
  transform: scaleY(1);
}

.hw-arrow-band {
  height: 44px;
  width: 100%;
  max-width: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hw-arrow-svg {
  width: 100%;
  height: 74px;
  overflow: visible;
}

.hw-arrow-path {
  fill: none;
  stroke: rgba(37, 211, 102, 0.85);
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-dasharray: 9 9;
}

/* The reveal is a clip-path wipe, not stroke-dashoffset: with a short "9 9"
   dash repeating along the whole path, animating dashoffset only shifts the
   dash phase — it never actually hides any of the path, so the arrow would
   stay visible the entire time. Scaling this clip rect from 0 keeps the dash
   pattern intact while genuinely hiding/revealing the arrow. */
.hw-clip-rect {
  transform: scaleX(0);
  transition: transform 1.1s cubic-bezier(0.22, 1, 0.36, 1);
}

.hw-clip-rect--on {
  transform: scaleX(1);
}

.hw-bar {
  transform-origin: bottom;
  animation: hw-bar 7s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) infinite;
}

@keyframes hw-bar {
  0%,
  8% {
    transform: scaleY(0.12);
  }
  34%,
  88% {
    transform: scaleY(1);
  }
  100% {
    transform: scaleY(0.12);
  }
}

.hw-dot {
  height: 8px;
  width: 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  border: none;
  padding: 0;
  cursor: pointer;
  transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1), background 0.45s;
}

.hw-dot--active {
  width: 26px;
  background: #25d366;
}

@media (prefers-reduced-motion: reduce) {
  .hw-card,
  .hw-card__glow,
  .hw-badge,
  .hw-float,
  .hw-phone-input,
  .hw-caret,
  .hw-connect-btn,
  .hw-sweep,
  .hw-verify-row,
  .hw-node,
  .hw-stub,
  .hw-clip-rect,
  .hw-bar,
  .hw-dot {
    animation: none !important;
    transition: none !important;
  }
}
</style>
