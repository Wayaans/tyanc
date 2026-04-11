import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type EchoInstance = Echo<'reverb'>;
type EchoConnectionState =
    | 'initialized'
    | 'connecting'
    | 'connected'
    | 'unavailable'
    | 'failed'
    | 'disconnected';
type EchoWithConnection = EchoInstance & {
    connector?: {
        pusher?: {
            connection?: {
                state?: EchoConnectionState;
            };
        };
    };
};

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: EchoInstance;
    }
}

let echo: EchoInstance | null = null;

function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function getEcho(): EchoInstance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (echo) {
        return echo;
    }

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: String(import.meta.env.VITE_REVERB_APP_KEY ?? ''),
        wsHost: String(
            import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        ),
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    window.Echo = echo;

    return echo;
}

export function currentSocketId(): string | null {
    return getEcho()?.socketId() ?? null;
}

export function isEchoConnected(): boolean {
    return (
        (getEcho() as EchoWithConnection | null)?.connector?.pusher?.connection
            ?.state === 'connected'
    );
}

export function conversationChannelName(conversationId: string): string {
    return `tyanc.conversations.${conversationId}`;
}
