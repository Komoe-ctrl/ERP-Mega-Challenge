<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, number } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { invoices, orders, receptions } from '@/mocks/v2';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de commande', href: '/achats/commandes' },
        ],
    },
});

function print() {
    window.print();
}

const order = computed(
    () => orders.find((o) => o.id === props.id) ?? orders[0],
);
const orderedTotal = computed(() =>
    order.value.lines.reduce((s, l) => s + l.ordered, 0),
);
const receivedTotal = computed(() =>
    order.value.lines.reduce((s, l) => s + l.received, 0),
);
const received = computed(() =>
    orderedTotal.value ? (receivedTotal.value / orderedTotal.value) * 100 : 0,
);
const orderReceptions = computed(() =>
    receptions.filter((r) => r.orderNumber === order.value.number),
);
const orderInvoices = computed(() =>
    invoices.filter((i) => i.orderNumber === order.value.number),
);
</script>

<template>
    <Head :title="order.number" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="order.number"
            :description="`${order.supplierName} · ${order.chantierName} · ${dateFr(order.date)}`"
        >
            <StatusBadge kind="order" :status="order.status" />
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer</Button
            >
        </PageHeader>
        <SubNav :links="achatsNav" />

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Article</th>
                            <th class="p-3 text-right font-medium">
                                Prix unitaire
                            </th>
                            <th class="p-3 text-right font-medium">Commandé</th>
                            <th class="p-3 text-right font-medium">Reçu</th>
                            <th class="p-3 text-right font-medium">Reliquat</th>
                            <th class="p-3 text-right font-medium">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in order.lines"
                            :key="l.label"
                            class="border-t"
                        >
                            <td class="p-3">{{ l.label }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ fcfa(l.unitPrice) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(l.ordered) }} {{ l.unit }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(l.received) }}
                            </td>
                            <td
                                class="p-3 text-right tabular-nums"
                                :class="
                                    l.ordered - l.received > 0
                                        ? 'text-warning'
                                        : ''
                                "
                            >
                                {{ number(l.ordered - l.received) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ fcfa(l.unitPrice * l.ordered) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-semibold">
                            <td class="p-3" colspan="5">Total</td>
                            <td
                                class="p-3 text-right font-display tabular-nums"
                            >
                                {{ fcfa(order.amount) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="font-display font-semibold">Réception</h2>
                <ProgressBar
                    :value="received"
                    :tone="received >= 100 ? 'success' : 'warning'"
                    label="Quantités reçues"
                />
                <ul class="divide-y text-sm">
                    <li v-for="r in orderReceptions" :key="r.id" class="py-2">
                        {{ r.number }} — {{ dateFr(r.date) }}
                    </li>
                </ul>
                <p
                    v-if="!orderReceptions.length"
                    class="text-sm text-muted-foreground"
                >
                    Aucune réception enregistrée.
                </p>
            </section>
            <section class="space-y-2 rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="font-display font-semibold">
                    Demandes et factures liées
                </h2>
                <p class="text-sm">
                    Demandes : {{ order.requestNumbers.join(', ') }}
                </p>
                <ul class="divide-y text-sm">
                    <li
                        v-for="i in orderInvoices"
                        :key="i.id"
                        class="flex items-center justify-between py-2"
                    >
                        <span>{{ i.number }} — {{ fcfa(i.amount) }}</span>
                        <StatusBadge kind="invoice" :status="i.status" />
                    </li>
                </ul>
                <p
                    v-if="!orderInvoices.length"
                    class="text-sm text-muted-foreground"
                >
                    Aucune facture.
                </p>
            </section>
        </div>
        <Button variant="ghost" as-child
            ><Link href="/achats/commandes">Retour à la liste</Link></Button
        >
    </div>
</template>
