<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, fcfa } from '@/lib/format';
import { invoices, orders, receptions, suppliers } from '@/mocks/v2';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

const { can } = usePermissions();
const supplier = computed(
    () => suppliers.find((s) => s.id === props.id) ?? suppliers[0],
);
// CA3 : commandes, factures non payées et note moyenne en un écran.
const supplierOrders = computed(() =>
    orders.filter((o) => o.supplierName === supplier.value.name),
);
const supplierReceptions = computed(() =>
    receptions.filter((r) => r.supplierName === supplier.value.name),
);
const unpaid = computed(() =>
    invoices.filter(
        (i) => i.supplierName === supplier.value.name && i.status !== 'payee',
    ),
);

// RG3.3 : évaluation (délais, qualité, respect des prix), notée après chaque réception.
type Evaluation = {
    reception: string;
    delay: number;
    quality: number;
    price: number;
    comment: string;
};
const evaluations = ref<Evaluation[]>([]);
const evalOpen = ref(false);
const average = computed(() => {
    if (!evaluations.value.length) {
        return supplier.value.rating;
    }

    const notes = evaluations.value.map(
        (e) => (e.delay + e.quality + e.price) / 3,
    );
    const all = supplier.value.rating
        ? [supplier.value.rating, ...notes]
        : notes;

    return all.reduce((s, n) => s + n, 0) / all.length;
});
const ratingText = computed(() =>
    average.value ? average.value.toFixed(1).replace('.', ',') : '—',
);

const marks = [1, 2, 3, 4, 5].map((n) => ({ value: n, label: `${n} / 5` }));
const evalFields = computed<FieldDef[]>(() => [
    {
        name: 'reception',
        label: 'Réception évaluée',
        type: 'select',
        required: true,
        options: supplierReceptions.value.map((r) => ({
            value: r.number,
            label: `${r.number} (${dateFr(r.date)})`,
        })),
        wide: true,
    },
    {
        name: 'delay',
        label: 'Délais de livraison',
        type: 'select',
        required: true,
        options: marks,
    },
    {
        name: 'quality',
        label: 'Qualité',
        type: 'select',
        required: true,
        options: marks,
    },
    {
        name: 'price',
        label: 'Respect des prix',
        type: 'select',
        required: true,
        options: marks,
    },
    { name: 'comment', label: 'Commentaire', type: 'textarea' },
]);

function evaluate(v: FieldValues) {
    evaluations.value.unshift({
        reception: String(v.reception),
        delay: Number(v.delay),
        quality: Number(v.quality),
        price: Number(v.price),
        comment: String(v.comment ?? ''),
    });
    toast.success(
        'Évaluation enregistrée : la note moyenne est recalculée (maquette).',
    );
}
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
            <Button
                v-if="can('fournisseurs.update') && supplierReceptions.length"
                variant="outline"
                @click="evalOpen = true"
                >Évaluer une réception</Button
            >
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <KpiCard
                label="Note moyenne"
                :value="average ? `${ratingText} / 5` : 'Non évalué'"
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

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">Évaluations</h2>
            <ul v-if="evaluations.length" class="divide-y text-sm">
                <li v-for="(e, i) in evaluations" :key="i" class="py-2.5">
                    <span class="font-medium">{{ e.reception }}</span> — délais
                    {{ e.delay }}/5 · qualité {{ e.quality }}/5 · prix
                    {{ e.price }}/5
                    <span v-if="e.comment" class="block text-muted-foreground"
                        >« {{ e.comment }} »</span
                    >
                </li>
            </ul>
            <EmptyState
                v-else
                compact
                title="Aucune évaluation saisie"
                description="Notez le fournisseur après chaque réception."
            />
        </section>

        <FormDialog
            v-model:open="evalOpen"
            title="Évaluer le fournisseur"
            :fields="evalFields"
            @submit="evaluate"
        />
    </div>
</template>
