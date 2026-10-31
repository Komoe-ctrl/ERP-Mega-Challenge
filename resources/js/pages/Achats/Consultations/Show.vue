<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsultationController from '@/actions/App/Http/Controllers/Achats/ConsultationController';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { fcfa } from '@/lib/format';
import type { Consultation } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    consultation: Consultation;
    suppliers: { value: string; label: string }[];
    canManage: boolean;
    orderId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Cotations', href: '/achats/consultations' },
        ],
    },
});

const quotes = computed(() => props.consultation.quotes);
const sorted = computed(() =>
    [...quotes.value].sort((a, b) => a.unitTotal - b.unitTotal),
);
const best = computed(() => sorted.value[0]);
const fastest = computed(
    () => [...quotes.value].sort((a, b) => a.delayDays - b.delayDays)[0],
);
const missing = computed(() =>
    Math.max(0, props.consultation.minSuppliers - quotes.value.length),
);
const chosen = ref<number | null>(
    props.consultation.quotes.find((q) => q.selected)?.supplierId ?? null,
);
const attributed = computed(() => props.consultation.status === 'attribuee');
const offerOpen = ref(false);

const offerFields = computed<FieldDef[]>(() => [
    {
        name: 'supplier_id',
        label: 'Fournisseur',
        type: 'select',
        required: true,
        options: props.suppliers,
        wide: true,
        hint: 'Seuls les fournisseurs actifs de la bonne catégorie sont proposés (RG3.1).',
    },
    {
        name: 'total_amount',
        label: 'Total HT proposé',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
    },
    {
        name: 'delay_days',
        label: 'Délai de livraison',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'jours',
    },
]);

function addOffer(v: FieldValues) {
    router.post(
        ConsultationController.addQuote.url(props.consultation.id),
        {
            supplier_id: v.supplier_id,
            total_amount: v.total_amount,
            delay_days: v.delay_days,
        },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Offre invalide.'),
        },
    );
}

function choose() {
    router.post(
        ConsultationController.award.url(props.consultation.id),
        { supplier_id: chosen.value },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Attribution refusée.'),
        },
    );
}
</script>

<template>
    <Head :title="consultation.number" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="consultation.number"
            :description="`${consultation.requestNumber} · ${consultation.chantierName}`"
        >
            <StatusBadge kind="consultation" :status="consultation.status" />
            <Button
                v-if="canManage && !attributed"
                variant="outline"
                @click="offerOpen = true"
                ><Plus /> Ajouter une offre</Button
            >
        </PageHeader>

        <p class="text-sm text-muted-foreground">{{ consultation.items }}</p>

        <p
            v-if="missing"
            class="flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            Il manque {{ missing }} offre(s) : au moins
            {{ consultation.minSuppliers }} fournisseurs doivent être consultés
            avant de commander.
        </p>

        <section
            v-if="quotes.length"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Choix</th>
                            <th class="p-3 font-medium">Fournisseur</th>
                            <th class="p-3 text-right font-medium">Total HT</th>
                            <th class="p-3 text-right font-medium">
                                Écart / meilleure offre
                            </th>
                            <th class="p-3 text-right font-medium">Délai</th>
                            <th class="p-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="q in sorted"
                            :key="q.supplierId"
                            class="border-t"
                            :class="
                                chosen === q.supplierId
                                    ? 'bg-success-soft/50'
                                    : ''
                            "
                        >
                            <td class="p-3">
                                <input
                                    v-model="chosen"
                                    type="radio"
                                    name="offer"
                                    :value="q.supplierId"
                                    :disabled="!canManage || attributed"
                                    :aria-label="`Retenir ${q.supplierName}`"
                                    class="size-4"
                                />
                            </td>
                            <td class="p-3 font-medium">
                                <Link
                                    :href="`/fournisseurs/${q.supplierId}`"
                                    class="hover:underline"
                                    >{{ q.supplierName }}</Link
                                >
                                <Trophy
                                    v-if="q.supplierId === best.supplierId"
                                    class="ml-1 inline size-4 text-brand"
                                />
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ fcfa(q.unitTotal) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                <span
                                    v-if="q.unitTotal === best.unitTotal"
                                    class="text-success"
                                    >Meilleure offre</span
                                >
                                <span v-else
                                    >+{{
                                        fcfa(q.unitTotal - best.unitTotal)
                                    }}</span
                                >
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ q.delayDays }} j
                                <span
                                    v-if="q.supplierId === fastest.supplierId"
                                    class="block text-xs text-success"
                                    >Le plus rapide</span
                                >
                            </td>
                            <td class="p-3">
                                <StatusBadge
                                    v-if="q.selected"
                                    label="Retenu"
                                    tone="success"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p
            v-else
            class="rounded-xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground"
        >
            Aucune offre reçue pour l’instant.
        </p>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="canManage"
                :disabled="chosen === null || missing > 0 || attributed"
                @click="choose"
                >Retenir ce fournisseur et créer le bon de commande</Button
            >
            <Button v-if="orderId" variant="secondary" as-child
                ><Link :href="`/achats/commandes/${orderId}`"
                    >Voir le bon de commande</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link href="/achats/consultations">Retour</Link></Button
            >
        </div>

        <FormDialog
            v-model:open="offerOpen"
            title="Ajouter une offre"
            :fields="offerFields"
            @submit="addOffer"
        />
    </div>
</template>
