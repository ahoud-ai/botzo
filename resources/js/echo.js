import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getEchoInstance(pusherKey, pusherCluster) {
    const key = typeof pusherKey === 'string' ? pusherKey.trim() : pusherKey;
    const cluster = typeof pusherCluster === 'string' ? pusherCluster.trim() : pusherCluster;

    if (!key) {
        return null;
    }

    if (!echoInstance) {
        window.Pusher = Pusher;
        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: cluster || 'mt1',
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                withCredentials: true,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            }
        });
    }
    return echoInstance;
}
