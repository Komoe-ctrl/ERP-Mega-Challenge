<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Mail, Phone } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { computeSituation, contractTotals } from '@/lib/billing';
import { dateFr, fcfa } from '@/lib/format';
import { projects } from '@/mocks/chantiers';
import { clientExtras } from '@/mocks/library';
import { clients, clientTypeLabels, contracts, situations } from '@/mocks/v3';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const client = computed(
    () => clients.find((c) => c.id === props.id) ?? clients[0],
);
// CA2 : en un écran, projets en cours et montant des créances. RG2.2 : agrège projets, marchés, situations, paiements, solde.
const clientProjects = computed(() =>
    projects.filter((p) => p.clientName === client.value.name),
);
const clientContracts = computed(() =>
    contracts.filter((c) => c.clientName === client.value.name),
);
const clientSituations = computed(() =>
    situations.filter((s) => s.clientName === client.value.name),
);
const extras = computed(
    () =>
        clientExtras[client.value.id] ?? {
            contacts: [],
            documents: [],
            payments: [],
        },
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
                <h2 class="mb-3 font-display font-semibold">Contacts</h2>
                <ul v-if="extras.contacts.length" class="divide-y text-sm">
                    <li
                        v-for="c in extras.contacts"
                        :key="c.email"
                        class="py-2.5"
                    >
                        <p class="font-medium">
                            {{ c.name }}
                            <span class="font-normal text-muted-foreground"
                                >— {{ c.role }}</span
                            >
                        </p>
                        <p
                            class="flex flex-wrap gap-x-4 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1"
                                ><Phone class="size-3" /> {{ c.phone }}</span
                            >
                            <span class="inline-flex items-center gap-1"
                                ><Mail class="size-3" /> {{ c.email }}</span
                            >
                        </p>
                    </li>
                </ul>
                <p v-else class="text-sm">
                    {{ client.contact }} · {{ client.phone }}
                </p>
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
                            <Link
                                :href="`/situations/${s.id}`"
                                class="hover:underline"
                                >Situation n°{{ s.number }}</Link
                            >
                            ({{ dateFr(s.date) }}) :
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

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Paiements reçus</h2>
                <ul v-if="extras.payments.length" class="divide-y text-sm">
                    <li
                        v-for="p in extras.payments"
                        :key="p.date + p.label"
                        class="flex items-center justify-between gap-2 py-2.5"
                    >
                        <span
                            >{{ p.label
                            }}<span
                                class="block text-xs text-muted-foreground"
                                >{{ dateFr(p.date) }}</span
                            ></span
                        >
                        <span class="tabular-nums">{{ fcfa(p.amount) }}</span>
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun paiement" />
            </section>
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Documents</h2>
                <ul v-if="extras.documents.length" class="divide-y text-sm">
                    <li
                        v-for="d in extras.documents"
                        :key="d.name"
                        class="flex items-center gap-3 py-2.5"
                    >
                        <FileText class="size-4 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate">{{
                            d.name
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            dateFr(d.date)
                        }}</span>
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun document" />
            </section>
        </div>
    </div>
</template>
