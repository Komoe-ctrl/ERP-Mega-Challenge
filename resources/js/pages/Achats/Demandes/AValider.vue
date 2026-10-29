<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/erp/PageHeader.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import {
    budgetOf,
    budgetTotals,
    DIRECTION_THRESHOLD,
    requestTotal,
    requests,
} from '@/mocks/achats';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
});

const pending = requests
    .filter((r) => r.status === 'en_attente')
    .map((r) => {
        const total = requestTotal(r);
        const remaining = budgetTotals(budgetOf(r.chantierId)).remaining;

        return {
            r,
            total,
            remaining,
            direction:
                r.needsDirection ||
                total > DIRECTION_THRESHOLD ||
                total > remaining,
        };
    });
</script>

<template>
    <Head title="Demandes à valider" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Demandes à valider"
            :description="`${pending.length} demande(s) en attente de décision.`"
        />

        <div class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="{ r, total, remaining, direction } in pending"
                :key="r.id"
                class="space-y-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display font-semibold">{{ r.number }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ r.chantierName }}
                        </p>
                    </div>
                    <StatusBadge
                        v-if="direction"
                        label="Validation Direction"
                        tone="brand"
                    />
                </div>
                <p class="text-sm">« {{ r.justification }} »</p>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Demandeur</dt>
                        <dd>{{ r.requester }} · {{ dateFr(r.createdAt) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Montant</dt>
                        <dd class="font-medium tabular-nums">
                            {{ fcfa(total) }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Reste à engager du chantier
                        </dt>
                        <dd
                            class="tabular-nums"
                            :class="total > remaining ? 'text-danger' : ''"
                        >
                            {{ fcfa(remaining) }}
                        </dd>
                    </div>
                </dl>
                <Button class="w-full" as-child>
                    <Link :href="`/achats/demandes/${r.id}`"
                        >Examiner et décider</Link
                    >
                </Button>
            </article>
        </div>

        <EmptyState
            v-if="!pending.length"
            title="Aucune demande en attente"
            description="Toutes les demandes ont reçu une décision."
        />
    </div>
</template>
