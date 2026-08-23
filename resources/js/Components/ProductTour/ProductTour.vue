<template></template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { TOUR_STEPS, useProductTour } from '@/Composables/useProductTour';
import { useRtl } from '@/Composables/useRtl';

const page = usePage();
const { t } = useI18n();
const { isRtl } = useRtl();
const tour = useProductTour();

let driverObj = null;
let renderToken = 0;

function resolveVisibleElement(selector) {
    const candidates = document.querySelectorAll(selector);
    for (const el of candidates) {
        if (el.offsetParent !== null) {
            return el;
        }
    }
    return candidates[0] ?? null;
}

async function waitForElement(selector, attempts = 20, delayMs = 50) {
    for (let i = 0; i < attempts; i += 1) {
        const el = resolveVisibleElement(selector);
        if (el) {
            return el;
        }
        await new Promise((resolve) => setTimeout(resolve, delayMs));
    }
    return null;
}

function overlayColor() {
    return document.documentElement.classList.contains('dark')
        ? 'rgba(0, 0, 0, 0.78)'
        : 'rgba(15, 23, 42, 0.5)';
}

function ensureDriver() {
    if (driverObj) {
        driverObj.setConfig({ ...driverObj.getConfig(), overlayColor: overlayColor() });
        return driverObj;
    }

    driverObj = driver({
        allowClose: false,
        animate: true,
        overlayColor: overlayColor(),
        overlayOpacity: 1,
        stagePadding: 6,
        stageRadius: 14,
        popoverOffset: 12,
        popoverClass: 'botzo-tour-popover',
    });

    return driverObj;
}

function buildLogoMark() {
    const isDark = document.documentElement.classList.contains('dark');
    const iconSrc = isDark ? '/images/nav/nav-icon-dark.svg' : '/images/nav/nav-icon-light.svg';
    const wordmarkSrc = isDark ? '/images/nav/nav-wordmark-dark.svg' : '/images/nav/nav-wordmark-light.svg';

    const wrap = document.createElement('div');
    wrap.className = 'botzo-tour-logo';
    wrap.innerHTML = `<img src="${iconSrc}" alt="" class="botzo-tour-logo-icon" />`
        + `<img src="${wordmarkSrc}" alt="Botzo" class="botzo-tour-logo-wordmark" />`;

    return wrap;
}

function buildPopover(step) {
    const index = TOUR_STEPS.indexOf(step);
    const isFirst = index === 0;
    const isSidebar = step.placement === 'sidebar';
    const progressTemplate = isRtl.value ? 'الخطوة {{current}} من {{total}}' : 'Step {{current}} of {{total}}';

    return {
        title: t(step.titleKey),
        description: t(step.descKey),
        side: isSidebar ? (isRtl.value ? 'left' : 'right') : 'bottom',
        align: isSidebar ? 'start' : 'center',
        popoverClass: isFirst ? 'botzo-tour-popover botzo-tour-popover--welcome' : 'botzo-tour-popover',
        showButtons: isFirst ? ['next', 'close'] : ['previous', 'next', 'close'],
        showProgress: true,
        progressText: progressTemplate
            .replace('{{current}}', String(index + 1))
            .replace('{{total}}', String(TOUR_STEPS.length)),
        nextBtnText: step.nextLabelKey ? t(step.nextLabelKey) : t('Next'),
        prevBtnText: t('Previous'),
        onNextClick: () => {
            if (!tour.state.locked) {
                tour.next();
            }
        },
        onPrevClick: () => {
            if (!tour.state.locked) {
                tour.prev();
            }
        },
        onCloseClick: () => tour.skip(),
        onPopoverRender: (popoverDom) => {
            popoverDom.closeButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
            popoverDom.closeButton.setAttribute('title', t('Skip'));
            popoverDom.closeButton.setAttribute('aria-label', t('Skip'));
            popoverDom.closeButton.classList.add('botzo-tour-skip-btn');

            if (isFirst) {
                popoverDom.wrapper.insertBefore(buildLogoMark(), popoverDom.title);
            }
        },
    };
}

async function renderCurrentStep() {
    const step = tour.currentStep();
    if (!step || !tour.state.active) {
        tour.setLocked(false);
        return;
    }

    const token = ++renderToken;
    await nextTick();

    let element;
    if (step.selector) {
        element = await waitForElement(step.selector);
        if (token !== renderToken || !tour.state.active) {
            return;
        }
        if (!element) {
            await tour.next();
            return;
        }
    }

    ensureDriver().highlight({
        element,
        popover: buildPopover(step),
    });
    tour.setLocked(false);
}

watch(
    () => [tour.state.active, tour.state.stepIndex, tour.state.navigating],
    ([active, , navigating]) => {
        if (!active) {
            driverObj?.destroy();
            return;
        }
        if (navigating) {
            return;
        }
        renderCurrentStep();
    },
    { immediate: true }
);

function checkAutoStart(pageProps) {
    tour.maybeAutoStart(pageProps?.onboardingTourStatus, window.location.pathname);
}

let stopNavigateListener = null;

onMounted(() => {
    checkAutoStart(page.props);
    stopNavigateListener = router.on('navigate', (event) => {
        checkAutoStart(event?.detail?.page?.props);
    });
});

onBeforeUnmount(() => {
    driverObj?.destroy();
    stopNavigateListener?.();
});
</script>
