<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { computeSituation, contractTotals } from '@/lib/billing';
import { dateFr, fcfa } from '@/lib/format';
import { projects } from '@/mocks/chantiers';
import { clients, clientTypeLabels, contracts, situations } from '@/mocks/v3';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const client = computed(
    () => clients.find((c) => c.id === props.id) ?? clients[0],
);
// CA2 : en un écran, projets en cours et montant des créances.
const clientProjects = computed(() =>
    projects.filter((p) => p.clientName === client.value.name),
);
const clientContracts = computed(() =>
    contracts.filter((c) => c.clientName === client.value.name),
);
const clientSituations = computed(() =>
    situations.filter((s) => s.clientName === client.value.name),
);
const due = computed(() => client.value.invoiced - client.value.paid);
</script>

<template>
    <Head :title="client.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="client.name"
            :description="`${client.number} · ${clientTypeLabels[client.type]} · ${client.city}`"
        >
            <StatusBadge
                :label="clientTypeLabels[client.type]"
                tone="neutral"
            />
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                label="Projets en cours"
                :value="String(client.projectsInProgress)"
                tone="info"
            />
            <KpiCard label="Facturé" :value="fcfa(client.invoiced)" />
            <KpiCard
                label="Encaissé"
                :value="fcfa(client.paid)"
                tone="success"
            />
            <KpiCard
                label="Créances (solde dû)"
                :value="fcfa(due)"
                :tone="due > 0 ? 'warning' : 'success'"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Contact</h2>
                <p class="text-sm">{{ client.contact }}</p>
                <p class="text-sm text-muted-foreground">{{ client.phone }}</p>
            </section>
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Projets associés
                </h2>
                <ul v-if="clientProjects.length" class="divide-y text-sm">
                    <li
                        v-for="p in clientProjects"
                        :key="p.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <Link
                            :href="`/projets/${p.id}`"
                            class="font-medium hover:underline"
                            >{{ p.name }}</Link
                        >
                        <StatusBadge kind="project" :status="p.status" />
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun projet" />
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Marchés et situations facturées
            </h2>
            <ul v-if="clientContracts.length" class="divide-y text-sm">
                <li v-for="c in clientContracts" :key="c.id" class="py-2.5">
                    <Link
                        :href="`/marches/${c.id}`"
                        class="font-medium hover:underline"
                        >{{ c.number }}</Link
                    >
                    — {{ fcfa(contractTotals(c).updatedAmount) }}
                    <ul class="mt-1 space-y-0.5 text-xs text-muted-foreground">
                        <li
                            v-for="s in clientSituations.filter(
                                (x) => x.contractNumber === c.number,
                            )"
                            :key="s.id"
                        >
                            Situation n°{{ s.number }} ({{ dateFr(s.date) }}) :
                            {{ fcfa(computeSituation(s).netTtc) }} TTC
                            <StatusBadge
                                kind="situation"
                                :status="s.status"
                                class="ml-1"
                            />
                        </li>
                    </ul>
                </li>
            </ul>
            <EmptyState v-else compact title="Aucun marché" />
        </section>
    </div>
</template>
