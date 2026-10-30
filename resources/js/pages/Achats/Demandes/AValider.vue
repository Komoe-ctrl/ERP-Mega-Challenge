<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PurchaseRequestController from '@/actions/App/Http/Controllers/Achats/PurchaseRequestController';
import EmptyState from '@/components/erp/EmptyState.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import type { PurchaseRequest } from '@/types/erp';

type Pending = Omit<PurchaseRequest, 'lines' | 'history'> & {
    remaining: number;
    direction: boolean;
};

defineProps<{ pending: Pending[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
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
                v-for="r in pending"
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
                        v-if="r.direction"
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
                            {{ fcfa(r.total ?? 0) }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Reste à engager du chantier
                        </dt>
                        <dd
                            class="tabular-nums"
                            :class="
                                (r.total ?? 0) > r.remaining
                                    ? 'text-danger'
                                    : ''
                            "
                        >
                            {{ fcfa(r.remaining) }}
                        </dd>
                    </div>
                </dl>
                <Button class="w-full" as-child>
                    <Link :href="PurchaseRequestController.show.url(r.id)"
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
