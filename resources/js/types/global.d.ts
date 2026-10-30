import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { ModuleNavItem } from '@/types/navigation';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            navigation: ModuleNavItem[];
            demo: {
                current: string | null;
                roles: { value: string; label: string }[];
            } | null;
            /** Nombre de notifications non lues de l'utilisateur connecté. */
            unreadNotifications: number;
            recentNotifications: {
                id: string;
                title: string;
                body: string;
                href: string;
            }[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
