import { Link } from '@inertiajs/vue3';
import { h } from 'vue';
import type { VNode } from 'vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import type { StatusKind, Tone } from '@/lib/status';

/** Cellule de tableau : lien Inertia (souvent le libellé principal de la ligne). */
export function linkCell(href: string, text: string, sub?: string): VNode {
    return h('div', { class: 'min-w-0' }, [
        h(
            Link,
            { href, class: 'font-medium text-foreground hover:underline' },
            () => text,
        ),
        sub ? h('div', { class: 'text-xs text-muted-foreground' }, sub) : null,
    ]);
}

/** Cellule de tableau : badge de statut. */
export function badgeCell(kind: StatusKind, status: string): VNode {
    return h(StatusBadge, { kind, status });
}

export function toneBadgeCell(label: string, tone: Tone): VNode {
    return h(StatusBadge, { label, tone });
}

/** Cellule alignée à droite pour les montants (chiffres tabulaires). */
export function amountCell(text: string): VNode {
    return h(
        'div',
        { class: 'text-right tabular-nums whitespace-nowrap' },
        text,
    );
}
