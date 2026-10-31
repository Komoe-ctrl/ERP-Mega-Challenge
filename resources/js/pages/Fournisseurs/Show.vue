<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierController from '@/actions/App/Http/Controllers/Fournisseurs/SupplierController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import type { PurchaseOrder, Supplier } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Tariff = {
    id: number;
    articleId: number;
    label: string;
    unit: string;
    referencePrice: number;
    price: number;
};

const props = defineProps<{
    supplier: Supplier;
    tariffs: Tariff[];
    articles: { value: string; label: string }[];
    categories: string[];
    canUpdate: boolean;
    orders: PurchaseOrder[];
    unpaidInvoices: {
        id: number;
        number: string;
        dueDate: string;
        amount: number;
        status: string;
    }[];
    evaluations: {
        reception: string;
        delay: number;
        quality: number;
        price: number;
        comment: string;
    }[];
    evaluable: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

// CA3 : commandes, factures non payées et note moyenne en un écran.
const supplierOrders = computed(() => props.orders);
const unpaid = computed(() => props.unpaidInvoices);
const average = computed(() => {
    if (!props.evaluations.length) {
        return null;
    }

    const total = props.evaluations.reduce(
        (s, e) => s + e.delay + e.quality + e.price,
        0,
    );

    return total / (props.evaluations.length * 3);
});

const evaluateOpen = ref(false);
const evaluateFields = computed<FieldDef[]>(() => [
    {
        name: 'reception_id',
        label: 'Réception évaluée',
        type: 'select',
        required: true,
        options: props.evaluable,
        wide: true,
        hint: props.evaluable.length
            ? 'Une évaluation par réception.'
            : 'Aucune réception à évaluer.',
    },
    {
        name: 'delay',
        label: 'Respect des délais (1 à 5)',
        type: 'number',
        required: true,
        min: 1,
        max: 5,
        step: 1,
    },
    {
        name: 'quality',
        label: 'Qualité (1 à 5)',
        type: 'number',
        required: true,
        min: 1,
        max: 5,
        step: 1,
    },
    {
        name: 'price',
        label: 'Respect des prix (1 à 5)',
        type: 'number',
        required: true,
        min: 1,
        max: 5,
        step: 1,
    },
    { name: 'comment', label: 'Commentaire', type: 'textarea', wide: true },
]);

function evaluate(v: FieldValues) {
    router.post(
        SupplierController.evaluate.url(props.supplier.id),
        v as Record<string, string | number>,
        { preserveScroll: true, onError },
    );
}

const editOpen = ref(false);
const tariffOpen = ref(false);
const disableOpen = ref(false);

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0]);

const categoryOptions = props.categories.map((c) => ({ value: c, label: c }));
const editFields: FieldDef[] = [
    {
        name: 'name',
        label: 'Raison sociale',
        required: true,
        minLength: 3,
        wide: true,
    },
    {
        name: 'category',
        label: 'Catégorie principale',
        type: 'select',
        required: true,
        options: categoryOptions,
    },
    {
        name: 'category2',
        label: 'Seconde catégorie (facultatif)',
        type: 'select',
        options: categoryOptions,
    },
    { name: 'contact', label: 'Contact', required: true },
    {
        name: 'phone',
        label: 'Téléphone',
        type: 'tel',
        required: true,
        pattern: /^\+?[\d\s]{8,18}$/,
        patternMessage: 'Numéro invalide (ex. +225 07 00 00 00 00).',
    },
    { name: 'email', label: 'E-mail (facultatif)', type: 'email' },
    { name: 'city', label: 'Ville', required: true },
];
const editInitial: FieldValues = {
    name: props.supplier.name,
    category: props.supplier.categories[0] ?? '',
    category2: props.supplier.categories[1] ?? '',
    contact: props.supplier.contact,
    phone: props.supplier.phone,
    email: props.supplier.email ?? '',
    city: props.supplier.city,
};
const tariffFields: FieldDef[] = [
    {
        name: 'article_id',
        label: 'Article',
        type: 'select',
        required: true,
        options: props.articles,
        wide: true,
    },
    {
        name: 'price',
        label: 'Tarif du fournisseur',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        hint: 'Alimente le comparatif des offres ; le référentiel reste le prix de référence interne (RG3.2).',
    },
];

function save(v: FieldValues) {
    const categories = [
        String(v.category),
        ...(v.category2 && v.category2 !== v.category
            ? [String(v.category2)]
            : []),
    ];

    router.put(
        SupplierController.update.url(props.supplier.id),
        {
            name: v.name,
            contact: v.contact,
            phone: v.phone,
            email: v.email || null,
            city: v.city,
            categories,
        },
        { preserveScroll: true, onError },
    );
}
function saveTariff(v: FieldValues) {
    router.post(
        SupplierController.setTariff.url(props.supplier.id),
        v as never,
        { preserveScroll: true, onError },
    );
}
function deactivate(reason: string) {
    router.patch(
        SupplierController.deactivate.url(props.supplier.id),
        { reason },
        { preserveScroll: true, onError },
    );
}
function reactivate() {
    router.patch(
        SupplierController.reactivate.url(props.supplier.id),
        {},
        { preserveScroll: true, onError },
    );
}
</script>

<template>
    <Head :title="supplier.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="supplier.name"
            :description="`${supplier.number} · ${supplier.city} · ${supplier.contact} · ${supplier.phone}`"
        >
            <StatusBadge
                v-for="c in supplier.categories"
                :key="c"
                :label="c"
                tone="neutral"
            />
            <StatusBadge
                v-if="supplier.active === false"
                label="Désactivé"
                tone="warning"
            />
            <Button v-if="canUpdate" variant="outline" @click="editOpen = true"
                >Modifier</Button
            >
            <Button
                v-if="canUpdate && supplier.active !== false"
                variant="outline"
                class="text-destructive"
                @click="disableOpen = true"
                >Désactiver</Button
            >
            <Button v-else-if="canUpdate" variant="outline" @click="reactivate"
                >Réactiver</Button
            >
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <KpiCard
                label="Note moyenne"
                :value="
                    average === null
                        ? 'Non évalué'
                        : `${average.toFixed(1).replace('.', ',')} / 5`
                "
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

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display font-semibold">Tarifs</h2>
                <Button
                    v-if="canUpdate"
                    size="sm"
                    variant="outline"
                    @click="tariffOpen = true"
                    >Ajouter / modifier un tarif</Button
                >
            </div>
            <div v-if="tariffs.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Article</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Tarif fournisseur
                            </th>
                            <th class="py-2 text-right font-medium">
                                Prix de référence
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tariffs" :key="t.id" class="border-t">
                            <td class="py-2 pr-4">
                                {{ t.label }}
                                <span class="text-xs text-muted-foreground"
                                    >({{ t.unit }})</span
                                >
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ fcfa(t.price) }}
                            </td>
                            <td
                                class="py-2 text-right tabular-nums"
                                :class="
                                    t.price > t.referencePrice
                                        ? 'text-warning'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ fcfa(t.referencePrice) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                compact
                title="Aucun tarif enregistré"
                description="Les tarifs alimentent le comparatif des offres (RG3.2)."
            />
        </section>

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
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display font-semibold">Évaluations</h2>
                <Button
                    v-if="canUpdate && evaluable.length"
                    size="sm"
                    variant="outline"
                    @click="evaluateOpen = true"
                    >Évaluer une réception</Button
                >
            </div>
            <ul v-if="evaluations.length" class="divide-y text-sm">
                <li v-for="e in evaluations" :key="e.reception" class="py-2.5">
                    <span class="font-medium">{{ e.reception }}</span>
                    <span class="ml-2 text-muted-foreground"
                        >délais {{ e.delay }} · qualité {{ e.quality }} · prix
                        {{ e.price }}</span
                    >
                    <p v-if="e.comment" class="text-muted-foreground">
                        {{ e.comment }}
                    </p>
                </li>
            </ul>
            <EmptyState
                v-else
                compact
                title="Aucune évaluation"
                description="Le fournisseur est noté après chaque réception de commande (délais, qualité, respect des prix)."
            />
        </section>

        <FormDialog
            v-model:open="evaluateOpen"
            title="Évaluer une réception"
            :fields="evaluateFields"
            @submit="evaluate"
        />

        <FormDialog
            v-model:open="editOpen"
            title="Modifier le fournisseur"
            :fields="editFields"
            :initial="editInitial"
            @submit="save"
        />
        <FormDialog
            v-model:open="tariffOpen"
            title="Tarif du fournisseur"
            :fields="tariffFields"
            @submit="saveTariff"
        />
        <ConfirmDialog
            v-model:open="disableOpen"
            title="Désactiver le fournisseur"
            description="Il ne sera plus proposé en consultation ; son historique est conservé."
            confirm-label="Désactiver"
            require-reason
            destructive
            @confirm="deactivate"
        />
    </div>
</template>
