import { reactive, readonly } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const STORAGE_KEY = 'botzo_onboarding_tour_step';

export const TOUR_STEPS = [
    {
        id: 'welcome',
        page: '/dashboard',
        selector: null,
        titleKey: 'Welcome to Botzo 👋',
        descKey: "Let's take a minute to show you around the dashboard — you can stop the tour anytime.",
        nextLabelKey: 'Start tour',
    },
    {
        id: 'sidebar-dashboard',
        page: '/dashboard',
        selector: 'a[href="/dashboard"]',
        placement: 'sidebar',
        titleKey: 'Dashboard',
        descKey: 'Get a quick overview of your account activity and performance from here.',
    },
    {
        id: 'sidebar-contacts',
        page: '/dashboard',
        selector: 'a[href="/contacts"]',
        placement: 'sidebar',
        titleKey: 'Contacts',
        descKey: 'All your customers live here — add a new contact or import them all at once.',
    },
    {
        id: 'sidebar-campaigns',
        page: '/dashboard',
        selector: 'a[href="/campaigns"]',
        placement: 'sidebar',
        titleKey: 'Campaigns',
        descKey: 'Send bulk messages to your customers about any offer or update.',
    },
    {
        id: 'sidebar-templates',
        page: '/dashboard',
        selector: 'a[href="/templates"]',
        placement: 'sidebar',
        titleKey: 'Message templates',
        descKey: 'Prepare approved WhatsApp message templates before using them in a campaign or automated reply.',
    },
    {
        id: 'sidebar-automation',
        page: '/dashboard',
        selector: 'a[href="/automation/basic"]',
        placement: 'sidebar',
        titleKey: 'Automation',
        descKey: 'Let the bot reply to your customers automatically based on certain keywords or situations.',
    },
    {
        id: 'whatsapp-setup',
        page: '/dashboard',
        selector: '[data-tour="whatsapp-setup"]',
        placement: 'content',
        titleKey: 'Connect your WhatsApp number',
        descKey: 'This is the most important step — connect your number here so you can actually send and receive messages.',
        optional: true,
    },
    {
        id: 'contacts-add',
        page: '/contacts',
        selector: '[data-tour="contacts-add"]',
        placement: 'content',
        titleKey: 'Add Contact',
        descKey: 'Add a new customer manually from here, or import a full list at once.',
    },
    {
        id: 'campaigns-create',
        page: '/campaigns',
        selector: '[data-tour="campaigns-create"]',
        placement: 'content',
        titleKey: 'Ready for your first campaign?',
        descKey: 'Click here to send your first campaign to your customers. That wraps up the tour 🎉',
        nextLabelKey: 'Finish',
        isLast: true,
    },
];

const state = reactive({
    active: false,
    stepIndex: 0,
    navigating: false,
    autoStartChecked: false,
    locked: false,
});

function readStoredIndex() {
    try {
        const raw = window.sessionStorage.getItem(STORAGE_KEY);
        const parsed = raw === null ? null : parseInt(raw, 10);

        return Number.isInteger(parsed) && parsed >= 0 && parsed < TOUR_STEPS.length ? parsed : null;
    } catch (error) {
        return null;
    }
}

function persistIndex(index) {
    try {
        window.sessionStorage.setItem(STORAGE_KEY, String(index));
    } catch (error) {
        // sessionStorage unavailable — mid-tour resume simply won't survive a hard refresh.
    }
}

function clearPersistedIndex() {
    try {
        window.sessionStorage.removeItem(STORAGE_KEY);
    } catch (error) {
        // no-op
    }
}

function finishOnServer(status) {
    axios.post('/onboarding-tour/finish', { status }).catch(() => {
        // Best-effort — the tour already closed locally even if this write fails.
    });
}

export function useProductTour() {
    function maybeAutoStart(onboardingTourStatus, currentPath) {
        if (state.autoStartChecked || state.active) {
            return false;
        }

        state.autoStartChecked = true;

        const resumeIndex = readStoredIndex();
        if (resumeIndex !== null) {
            state.active = true;
            state.stepIndex = resumeIndex;
            state.locked = false;
            return true;
        }

        if (currentPath !== '/dashboard') {
            return false;
        }

        state.active = true;
        state.stepIndex = 0;
        state.locked = false;
        persistIndex(0);
        return true;
    }

    function start() {
        state.active = true;
        state.stepIndex = 0;
        state.locked = false;
        persistIndex(0);
    }

    function currentStep() {
        return TOUR_STEPS[state.stepIndex] ?? null;
    }

    async function goToStep(index) {
        const step = TOUR_STEPS[index];
        if (!step) {
            return;
        }

        state.stepIndex = index;
        persistIndex(index);

        if (step.page && step.page !== window.location.pathname) {
            state.navigating = true;
            await new Promise((resolve) => {
                router.visit(step.page, {
                    preserveScroll: true,
                    onFinish: resolve,
                });
            });
            state.navigating = false;
        }
    }

    async function next() {
        state.locked = true;
        const step = currentStep();
        if (step?.isLast) {
            await finish('completed');
            return;
        }

        await goToStep(state.stepIndex + 1);
    }

    async function prev() {
        if (state.stepIndex === 0) {
            return;
        }

        state.locked = true;
        await goToStep(state.stepIndex - 1);
    }

    function setLocked(value) {
        state.locked = value;
    }

    async function skip() {
        state.active = false;
        clearPersistedIndex();
        finishOnServer('skipped');
    }

    async function finish(status = 'completed') {
        state.active = false;
        clearPersistedIndex();
        finishOnServer(status);
    }

    return {
        state: readonly(state),
        maybeAutoStart,
        start,
        next,
        prev,
        skip,
        finish,
        setLocked,
        currentStep,
        goToStep,
    };
}
