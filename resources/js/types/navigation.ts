import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: number;
};

/** Entrée de menu d'un module, calculée côté serveur selon les droits. */
export type ModuleNavItem = {
    key: string;
    title: string;
    icon: string;
    href: string;
};
