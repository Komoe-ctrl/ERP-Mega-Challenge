<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { dateFr, fcfa } from '@/lib/format';
import { invoices, orders, suppliers } from '@/mocks/v2';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

const supplier = computed(
    () => suppliers.find((s) => s.id === props.id) ?? suppliers[0],
);
// CA3 : commandes, factures non payées et note moyenne en un écran.
const supplierOrders = computed(() =>
    orders.filter((o) => o.supplierName === supplier.value.name),
);
const unpaid = computed(() =>
    invoices.filter(
        (i) => i.supplierName === supplier.value.name && i.status !== 'payee',
    ),
);
const ratingText = computed(() =>
    supplier.value.rating.toFixed(1).replace('.', ','),
);
</script>

<template>
    <Head :title="supplier.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="supplier.name"
            :description="`${supplier.city} · ${supplier.contact} · ${supplier.phone}`"
        >
            <StatusBadge
                v-for="c in supplier.categories"
                :key="c"
                :label="c"
                tone="neutral"
            />
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <KpiCard
                label="Note moyenne"
                :value="`${ratingText} / 5`"
                hint="Délais, qualité, respect des prix"
                :icon="Star"
                tone="brand"
            />
            <KpiCard
                label="Commandes"
                :value="String(supplierOrders.length)"
                :hint="`${supplier.openOrders} en cours`"
            />
            <KpiCard
                label="Factures non payées"
                :value="fcfa(unpaid.reduce((s, i) => s + i.amount, 0))"
                :tone="unpaid.length ? 'warning' : 'success'"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Commandes</h2>
                <ul v-if="supplierOrders.length" class="divide-y text-sm">
                    <li
                        v-for="o in supplierOrders"
                        :key="o.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <Link
                            :href="`/achats/commandes/${o.id}`"
                            class="hover:underline"
                        >
                            <span class="font-medium">{{ o.number }}</span>
                            <span class="block text-xs text-muted-foreground"
                                >{{ dateFr(o.date) }} ·
                                {{ fcfa(o.amount) }}</span
                            >
                        </Link>
                        <StatusBadge kind="order" :status="o.status" />
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucune commande" />
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Factures non payées
                </h2>
                <ul v-if="unpaid.length" class="divide-y text-sm">
                    <li
                        v-for="i in unpaid"
                        :key="i.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <span>
                            <span class="font-medium">{{ i.number }}</span>
                            <span class="block text-xs text-muted-foreground"
                                >Échéance {{ dateFr(i.dueDate) }} ·
                                {{ fcfa(i.amount) }}</span
                            >
                        </span>
                        <StatusBadge kind="invoice" :status="i.status" />
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucune facture impayée" />
            </section>
        </div>
    </div>
</template>
