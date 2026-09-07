<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";

// Continuous step-cycling animation for "How it works" (desktop layout).
// Same state machine as the mobile component — see that file for the full
// rationale on the clip-path arrow reveal and the card/mock opacity split.
// Desktop keeps its own absolute-positioned card/connector geometry exactly
// as-is; only the connectors switch from static <img> to inline animated
// SVG (same path data, same rotated bounding-box wrapper) since a static
// image cannot self-draw.

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

const bubbleState = (index) => (step2.revealed > index ? "hw-bubble--revealed" : "hw-bubble--hidden");
</script>

<template>
    <div class="relative w-[1080px]" style="height: 1649.59px">
        <!-- Connector: Step1 -> Step2 (180deg rotation keeps the same bounding box, no dimension swap) -->
        <div class="absolute flex items-center justify-center" style="left: 392px; top: 445px; width: 294.058px; height: 136.218px">
            <div class="shrink-0 rotate-180" style="width: 298.736px; height: 141.032px">
                <svg viewBox="0 0 298.736 141.032" preserveAspectRatio="none" overflow="visible" class="block h-full w-full">
                    <defs>
                        <linearGradient id="hw-d-grad-1" x1="54.9772" y1="-87.5413" x2="325.941" y2="60.262" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#25D366" />
                            <stop offset="1" stop-color="#1DB954" />
                        </linearGradient>
                        <clipPath id="hw-d-clip-1" clipPathUnits="userSpaceOnUse">
                            <rect x="-20" y="-20" width="338.736" height="181.032" class="hw-clip-rect" :class="{ 'hw-clip-rect--on': arrow1On }" style="transform-origin: 0px 0px" />
                        </clipPath>
                    </defs>
                    <g clip-path="url(#hw-d-clip-1)">
                        <path
                            ref="arrow1Path"
                            d="M2.50058 138.531C16.7804 108.572 37.3464 84.7879 71.2547 77.4166C100.714 71.0124 130.226 73.6525 159.744 68.8223C193.096 63.3647 226.588 49.7144 254.493 30.8378C264.005 24.403 273.386 13.5924 283.247 8.66245"
                            stroke="url(#hw-d-grad-1)" stroke-width="5" stroke-linecap="round" stroke-dasharray="10 10" fill="none"
                        />
                        <path
                            d="M262.238 4.84273C272.333 4.84273 283.83 3.46025 292.796 2.93285C297.457 2.65866 297.222 1.47466 295.13 3.35727C288.345 9.46424 280.392 21.0473 277.517 29.6706"
                            stroke="url(#hw-d-grad-1)" stroke-width="5" stroke-linecap="round" stroke-dasharray="10 10" fill="none"
                        />
                    </g>
                    <circle ref="arrow1Dot" r="4" fill="#25d366" style="opacity: 0" />
                </svg>
            </div>
        </div>

        <!-- Connector: Step2 -> Step3 (90deg rotation swaps width/height, so the SVG must keep
             its own natural landscape viewBox and only the wrapper gets rotated) -->
        <div class="absolute flex items-center justify-center" style="left: 493px; top: 837.02px; width: 136.219px; height: 294.057px">
            <div class="shrink-0 rotate-90" style="width: 297.333px; height: 139.279px">
                <svg viewBox="0 0 297.333 139.279" preserveAspectRatio="none" overflow="visible" class="block h-full w-full">
                    <defs>
                        <linearGradient id="hw-d-grad-2" x1="54.7333" y1="-87.8699" x2="325.697" y2="59.9334" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#25D366" />
                            <stop offset="1" stop-color="#1DB954" />
                        </linearGradient>
                        <clipPath id="hw-d-clip-2" clipPathUnits="userSpaceOnUse">
                            <rect x="-20" y="-20" width="337.333" height="179.279" class="hw-clip-rect" :class="{ 'hw-clip-rect--on': arrow2On }" style="transform-origin: 0px 0px" />
                        </clipPath>
                    </defs>
                    <g clip-path="url(#hw-d-clip-2)">
                        <path
                            ref="arrow2Path"
                            d="M2.25676 138.203C16.5366 108.243 37.1026 84.4594 71.0109 77.0881C100.47 70.6839 129.982 73.324 159.5 68.4938C192.853 63.0362 226.345 49.3859 254.249 30.5093C263.762 24.0745 273.142 13.2639 283.003 8.33394"
                            stroke="url(#hw-d-grad-2)" stroke-width="5" stroke-dasharray="10 10" fill="none"
                        />
                        <path
                            d="M261.995 4.51422C272.09 4.51422 283.586 3.13174 292.552 2.60434C297.213 2.33015 296.978 1.14615 294.886 3.02875C288.101 9.13573 280.148 20.7188 277.273 29.3421"
                            stroke="url(#hw-d-grad-2)" stroke-width="5" stroke-dasharray="10 10" fill="none"
                        />
                    </g>
                    <circle ref="arrow2Dot" r="4" fill="#25d366" style="opacity: 0" />
                </svg>
            </div>
        </div>

        <!-- Step 1: Connect WhatsApp Business -->
        <div class="hw-card absolute w-[1080px] overflow-hidden rounded-3xl" :class="active === 0 ? 'hw-card--active' : 'hw-card--inactive'" style="left: 0; top: 0">
            <div class="hw-card__glow hw-card__glow--start" :class="{ 'hw-card__glow--on': active === 0 }" aria-hidden="true"></div>
            <div class="relative flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="hw-float hw-float--1 flex w-[319px] shrink-0 flex-col items-start gap-[18px] rounded-[20px] bg-black p-4" :class="{ 'hw-float--dim': active !== 0 }">
                    <div class="w-full">
                        <p class="text-right text-xs leading-[18px] text-[#8899aa]">{{ $t('Mobile number') }}</p>
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
                        {{ $t('Connect now') }}
                    </button>
                    <div class="hw-verify-row flex w-full items-center justify-end gap-[10px] rounded-xl border-[1.5px] border-[#1e2a3a] px-[15.5px] py-[13.5px]" :class="{ 'hw-verify-row--shown': step1.showVerify }">
                        <div class="flex flex-col items-end">
                            <p class="text-sm font-bold leading-[21px] text-[#25d366]">{{ $t('Connected') }}</p>
                            <p class="text-[11px] leading-[16.5px] text-[#8899aa]" dir="ltr">Botzo AI مرتبط بواتساب</p>
                        </div>
                        <div class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-[#25d366]">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10.8346 3.25L4.8763 9.20833L2.16797 6.5" stroke="#06210f" stroke-width="1.625" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <div class="flex w-full items-center justify-between">
                        <span class="hw-eyebrow">{{ active === 0 ? "قيد التشغيل" : stepLabels[0] }}</span>
                        <div class="hw-badge" :class="active === 0 ? 'hw-badge--active' : 'hw-badge--inactive'">١</div>
                    </div>
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-white">
                        1-{{ $t('Connect WhatsApp Business') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#b9cfc2]">
                        {{ $t('Connect your number via official WhatsApp in minutes, with zero technical hassle.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 2: Design smart replies without code -->
        <div class="hw-card absolute w-[1080px] overflow-hidden rounded-3xl" :class="active === 1 ? 'hw-card--active' : 'hw-card--inactive'" style="left: 0; top: 603px">
            <div class="hw-card__glow hw-card__glow--end" :class="{ 'hw-card__glow--on': active === 1 }" aria-hidden="true"></div>
            <div class="relative flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="hw-float hw-float--2 relative h-[307px] w-[427px] shrink-0 overflow-hidden rounded-[20px] bg-[#0a0f17]" :class="{ 'hw-float--dim': active !== 1 }">
                    <img
                        src="/images/how-it-works/flowchart-lines.svg"
                        class="pointer-events-none absolute inset-0 h-[290px] w-[428px]"
                        alt=""
                        aria-hidden="true"
                    />

                    <div
                        class="hw-bubble absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                        :class="bubbleState(0)"
                        style="left: 190px; top: 41.31px"
                    >
                        <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">👋</span>
                        <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Welcome message') }}</span>
                    </div>

                    <div
                        class="hw-bubble absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                        :class="bubbleState(1)"
                        style="left: 190px; top: 127.72px"
                    >
                        <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">❓</span>
                        <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Customer question?') }}</span>
                    </div>

                    <div
                        class="hw-bubble absolute flex items-center gap-[7px] rounded-xl border-2 border-[#25d366] bg-[#131a28] px-4 py-[10px] drop-shadow-[0_0_0_rgba(37,211,102,0.12),0_4px_8px_rgba(37,211,102,0.22)]"
                        :class="bubbleState(2)"
                        style="left: 95.5px; top: 219.88px"
                    >
                        <span class="text-[13px] font-bold leading-[19.5px] text-[#25d366]">✓</span>
                        <span class="whitespace-nowrap text-xs font-bold leading-[18px] text-[#25d366]">{{ $t('Auto-reply') }}</span>
                        <span class="h-[7px] w-[7px] shrink-0 rounded-full bg-[#25d366]"></span>
                    </div>

                    <div
                        class="hw-bubble absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                        :class="bubbleState(3)"
                        style="left: 284.5px; top: 219.88px"
                    >
                        <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">👤</span>
                        <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Transfer to agent') }}</span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <div class="flex w-full items-center justify-between">
                        <span class="hw-eyebrow">{{ active === 1 ? "قيد التشغيل" : stepLabels[1] }}</span>
                        <div class="hw-badge" :class="active === 1 ? 'hw-badge--active' : 'hw-badge--inactive'">٢</div>
                    </div>
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-white">
                        2-{{ $t('Design smart replies without code') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#b9cfc2]">
                        {{ $t('Build flows with drag-and-drop, without writing a single line of code.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 3: Monitor and improve -->
        <div class="hw-card absolute w-[1080px] overflow-hidden rounded-3xl" :class="active === 2 ? 'hw-card--active' : 'hw-card--inactive'" style="left: 0; top: 1171px">
            <div class="hw-card__glow hw-card__glow--start" :class="{ 'hw-card__glow--on': active === 2 }" aria-hidden="true"></div>
            <div class="relative flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="hw-float hw-float--3 flex w-[360px] max-w-[360px] shrink-0 flex-col items-end rounded-[20px] bg-black p-[22px]" :class="{ 'hw-float--dim': active !== 2 }">
                    <p class="w-full text-right text-sm leading-[21px] text-white">{{ $t('Overview') }}</p>
                    <div class="mt-4 flex w-full items-start gap-[10px]" dir="ltr">
                        <div class="flex w-[152px] flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                            <p class="w-full text-right text-[11px] leading-[16.5px] text-[#06210f]">{{ $t('Reply rate') }}</p>
                            <p class="hw-mono w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-[#06210f]">{{ step3.replyRateDisplay }}</p>
                        </div>
                        <div class="flex w-[152px] flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                            <p class="w-full text-right text-[11px] leading-[16.5px] text-[#06210f]">{{ $t('Conversions') }}</p>
                            <p class="hw-mono w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-[#06210f]">↑{{ step3.conversionsDisplay }}</p>
                        </div>
                    </div>
                    <div class="mt-[18px] w-full">
                        <p class="w-full text-right text-[11px] leading-[16.5px] text-white">{{ $t('Daily conversations') }}</p>
                        <div class="mt-2.5 flex w-full items-end gap-1.5" style="height: 82px">
                            <div class="hw-bar min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 44.633px; --d: 0s"></div>
                            <div class="hw-bar min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 54px; --d: 0.08s"></div>
                            <div class="hw-bar min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 41.758px; --d: 0.16s"></div>
                            <div class="hw-bar min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 63.359px; --d: 0.24s"></div>
                            <div class="hw-bar min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 59.039px; --d: 0.32s"></div>
                            <div class="hw-bar min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 67.68px; --d: 0.4s"></div>
                            <div class="hw-bar min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 56.875px; --d: 0.48s"></div>
                        </div>
                        <div class="mt-1.5 flex w-full gap-1.5">
                            <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Sun') }}</span>
                            <span class="min-w-px flex-[39.719] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Mon') }}</span>
                            <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Tue') }}</span>
                            <span class="min-w-px flex-[39.719] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Wed') }}</span>
                            <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Thu') }}</span>
                            <span class="min-w-px flex-[39.719] text-center text-[9px] font-bold leading-[13.5px] text-[#25d366]">{{ $t('Fri') }}</span>
                            <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#94a3b8]">{{ $t('Sat') }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <div class="flex w-full items-center justify-between">
                        <span class="hw-eyebrow">{{ active === 2 ? "قيد التشغيل" : stepLabels[2] }}</span>
                        <div class="hw-badge" :class="active === 2 ? 'hw-badge--active' : 'hw-badge--inactive'">٣</div>
                    </div>
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-white">
                        3-{{ $t('Monitor and improve') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#b9cfc2]">
                        {{ $t("A real-time analytics dashboard reveals what's working and what needs improvement.") }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Manual step control -->
        <div class="absolute flex items-center gap-2" style="left: 508px; top: 1609.59px" role="tablist" aria-label="How it works steps">
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
  transition: background 0.6s, border-color 0.6s, box-shadow 0.6s;
}

.hw-card--active {
  background: #0d2114;
  border: 1px solid rgba(37, 211, 102, 0.34);
  box-shadow: 0 30px 60px -40px rgba(37, 211, 102, 0.55);
}

.hw-card--inactive {
  background: #0b1a11;
  border: 1px solid rgba(255, 255, 255, 0.07);
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
  width: 420px;
  height: 420px;
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

.hw-bubble {
  transition: opacity 0.45s cubic-bezier(0.22, 1, 0.36, 1), transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.hw-bubble--hidden {
  opacity: 0.4;
  transform: translateY(6px) scale(0.96);
}

.hw-bubble--revealed {
  opacity: 1;
  transform: translateY(0) scale(1);
}

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
  .hw-bubble,
  .hw-clip-rect,
  .hw-bar,
  .hw-dot {
    animation: none !important;
    transition: none !important;
  }
}
</style>
