<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ContractController from '@/actions/App/Http/Controllers/Marches/ContractController';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import type { FieldDef, FieldValues } from '@/types/forms';

type Amendment = {
    id: number;
    number: string;
    date: string;
    label: string;
    amount: number;
    days: number;
    validated: boolean;
    canValidate: boolean;
};

const props = defineProps<{
    contract: {
        id: number;
        number: string;
        clientName: string;
        projectName: string;
        signedAt: string;
        initialAmount: number;
        updated: number;
        amendmentsAmount: number;
        days: number;
        advanceRate: number;
        retentionRate: number;
        paymentDays: number;
        deadlineDays: number;
        projectId: number;
        quoteId: number | null;
        quoteNumber: string;
        amendments: Amendment[];
    };
    situations: {
        id: number;
        number: number;
        date: string;
        status: string;
        netTtc: number;
    }[];
    can: { amend: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Marchés', href: '/marches' }] },
});

const amendmentOpen = ref(false);
// CA6 : montant initial, avenants validés, montant actualisé et délai contractuel (calculés par le serveur, RG6.2).
const contract = computed(() => props.contract);

const fields: FieldDef[] = [
    {
        name: 'label',
        label: 'Objet de l’avenant',
        required: true,
        minLength: 5,
        wide: true,
    },
    {
        name: 'amount',
        label: 'Montant',
        type: 'number',
        required: true,
        step: 1,
        unit: 'FCFA',
        hint: 'Positif (plus-value), négatif (moins-value) ou 0 si seul le délai change.',
    },
    {
        name: 'days',
        label: 'Délai supplémentaire',
        type: 'number',
        required: true,
        step: 1,
        unit: 'jours',
    },
    { name: 'amendment_date', label: 'Date', type: 'date', required: true },
];

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0] ?? 'Saisie invalide.');

function addAmendment(v: FieldValues) {
    router.post(
        ContractController.storeAmendment.url(contract.value.id),
        {
            label: String(v.label),
            amount: Number(v.amount),
            days: Number(v.days),
            amendment_date: String(v.amendment_date),
        },
        { preserveScroll: true, onError },
    );
}

function validateAmendment(amendment: Amendment) {
    router.post(
        ContractController.validateAmendment.url({
            contract: contract.value.id,
            amendment: amendment.id,
        }),
        {},
        { preserveScroll: true, onError },
    );
}
</script>

<template>
    <Head :title="contract.number" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="contract.number"
            :description="`${contract.clientName} · ${contract.projectName} · signé le ${dateFr(contract.signedAt)}`"
        >
            <Button variant="outline" as-child
                ><Link :href="`/projets/${contract.projectId}`"
                    >Voir le projet</Link
                ></Button
            >
            <Button v-if="contract.quoteId" variant="outline" as-child
                ><Link :href="`/devis/${contract.quoteId}`"
                    >Voir le devis {{ contract.quoteNumber }}</Link
                ></Button
            >
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                label="Montant initial"
                :value="fcfa(contract.initialAmount)"
            />
            <KpiCard
                label="Avenants validés"
                :value="`${contract.amendmentsAmount >= 0 ? '+' : ''}${fcfa(contract.amendmentsAmount)}`"
                tone="info"
            />
            <KpiCard
                label="Montant actualisé"
                :value="fcfa(contract.updated)"
                tone="success"
            />
            <KpiCard
                label="Délai contractuel"
                :value="`${contract.days} jours`"
                :hint="`dont ${contract.deadlineDays} initiaux`"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Conditions de paiement
                </h2>
                <p class="mb-2 text-xs text-muted-foreground">
                    Elles servent au calcul des situations de travaux (RG6.3).
                </p>
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                    <dt class="text-muted-foreground">Acompte</dt>
                    <dd>{{ contract.advanceRate }} %</dd>
                    <dt class="text-muted-foreground">Retenue de garantie</dt>
                    <dd>{{ contract.retentionRate }} %</dd>
                    <dt class="text-muted-foreground">Délai de paiement</dt>
                    <dd>{{ contract.paymentDays }} jours</dd>
                </dl>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display font-semibold">Avenants</h2>
                    <Button
                        v-if="can.amend"
                        size="sm"
                        variant="outline"
                        @click="amendmentOpen = true"
                        >Ajouter</Button
                    >
                </div>
                <ul v-if="contract.amendments.length" class="divide-y text-sm">
                    <li
                        v-for="a in contract.amendments"
                        :key="a.id"
                        class="flex flex-wrap items-start justify-between gap-2 py-2.5"
                    >
                        <span>
                            <span class="font-medium">{{ a.number }}</span> —
                            {{ a.label }}
                            <span class="block text-xs text-muted-foreground"
                                >{{ dateFr(a.date) }} ·
                                {{ a.amount >= 0 ? '+' : ''
                                }}{{ fcfa(a.amount) }} ·
                                {{ a.days }} jours</span
                            >
                        </span>
                        <span class="flex items-center gap-2">
                            <StatusBadge
                                :label="a.validated ? 'Validé' : 'En attente'"
                                :tone="a.validated ? 'success' : 'warning'"
                            />
                            <Button
                                v-if="a.canValidate"
                                size="sm"
                                @click="validateAmendment(a)"
                                >Valider</Button
                            >
                        </span>
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun avenant" />
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Situations de travaux
            </h2>
            <ul v-if="situations.length" class="divide-y text-sm">
                <li
                    v-for="s in situations"
                    :key="s.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <Link
                        :href="`/situations/${s.id}`"
                        class="font-medium hover:underline"
                        >Situation n°{{ s.number }} — {{ dateFr(s.date) }}</Link
                    >
                    <span class="flex items-center gap-3">
                        <span class="tabular-nums"
                            >{{ fcfa(s.netTtc) }} TTC</span
                        >
                        <StatusBadge kind="situation" :status="s.status" />
                    </span>
                </li>
            </ul>
            <EmptyState v-else compact title="Aucune situation" />
        </section>

        <FormDialog
            v-model:open="amendmentOpen"
            title="Nouvel avenant"
            :initial="{ amendment_date: TODAY, days: 0 }"
            :fields="fields"
            @submit="addAmendment"
        />
    </div>
</template>
