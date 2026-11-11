import axios from 'axios';
import { ref } from 'vue';
import { getEchoInstance } from '@/echo';

// One shared count: the sidebar item and the mobile bell show the same number.
const available = ref(false);
const count = ref(0);

// Live updates need Pusher, which an installation may not have, so the count also checks on its own.
const REFRESH_EVERY_MS = 60000;

let started = false;
let latestRequest = 0;
let listeningAsUser = null;
let lastEventId = 0;
let announcedEventId = 0;
const changeListeners = new Set();

function apply(data) {
    available.value = Boolean(data?.available);
    count.value = Number(data?.count ?? 0);
}

async function refresh() {
    const ticket = ++latestRequest;
    try {
        const response = await axios.get('/human-requests');
        if (ticket === latestRequest) {
            apply(response.data);
        }
    } catch (_error) {
        // Keep what is showing: a passing network error should not hide the item.
    }
}

async function closeRequest(uuid) {
    // A count requested before this close must not bring the closed request back.
    const ticket = ++latestRequest;
    const response = await axios.post(`/human-requests/${uuid}/close`);
    if (ticket === latestRequest) {
        apply(response.data);
    }
}

function listen(userId, pusherKey, pusherCluster) {
    if (!userId || listeningAsUser === userId) {
        return;
    }

    const echo = getEchoInstance(pusherKey, pusherCluster);
    if (!echo) {
        return;
    }

    listeningAsUser = userId;
    echo.private(`chats.user.${userId}`).listen('HumanRequestsChanged', async (event) => {
        await refresh();
        const eventId = ++lastEventId;
        changeListeners.forEach((listener) => listener(Boolean(event?.opened), eventId));
    });
}

// Several menus are on the page at once (sidebar, mobile drawer); the first to ask says it.
function claimAnnouncement(eventId) {
    if (eventId === announcedEventId) {
        return false;
    }

    announcedEventId = eventId;
    return true;
}

function start() {
    if (started) {
        return;
    }

    started = true;
    refresh();
    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    }, REFRESH_EVERY_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    });
}

export function useHumanRequests() {
    return { available, count, refresh, closeRequest, listen, start, changeListeners, claimAnnouncement };
}
