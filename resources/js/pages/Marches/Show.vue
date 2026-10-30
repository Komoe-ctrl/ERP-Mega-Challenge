<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { computeSituation, contractTotals } from '@/lib/billing';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { projects } from '@/mocks/chantiers';
import { contracts, quotes, situations } from '@/mocks/v3';
import type { Contract } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Marchés', href: '/marches' }] },
});

const { can } = usePermissions();
const contract = reactive<Contract>(
    structuredClone(contracts.find((c) => c.id === props.id) ?? contracts[0]),
);
const amendmentOpen = ref(false);
// CA6 : montant initial, avenants, montant actualisé et délai contractuel (recalculés en direct).
const totals = computed(() => contractTotals(contract));
const contractSituations = computed(() =>
    situations.filter((s) => s.contractNumber === contract.number),
);
const project = computed(() =>
    projects.find((p) =>
        contract.projectName.startsWith(p.name.split(' — ')[0]),
    ),
);
const quote = computed(() =>
    quotes.find((q) =>
        contract.projectName.startsWith(q.projectName.split(' — ')[0]),
    ),
);

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
        hint: 'Positif (plus-value) ou négatif (moins-value).',
        validate: (v) =>
            Number(v) === 0
                ? 'Le montant ne peut pas être nul ; saisissez 0 jour si seul le délai change.'
                : null,
    },
    {
        name: 'days',
        label: 'Délai supplémentaire',
        type: 'number',
        required: true,
        step: 1,
        unit: 'jours',
    },
    { name: 'date', label: 'Date', type: 'date', required: true },
];

function addAmendment(v: FieldValues) {
    contract.amendments.push({
        number: `AV-0${contract.amendments.length + 1}`,
        date: String(v.date),
        label: String(v.label),
        amount: Number(v.amount),
        days: Number(v.days),
        validated: false,
    });
    toast.success('Avenant enregistré, en attente de validation (maquette).');
}

function validateAmendment(index: number) {
    contract.amendments[index].validated = true;
    toast.success(
        'Avenant validé : le montant actualisé et le délai sont mis à jour.',
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
            <Button v-if="project" variant="outline" as-child
                ><Link :href="`/projets/${project.id}`"
                    >Voir le projet</Link
                ></Button
            >
            <Button v-if="quote" variant="outline" as-child
                ><Link :href="`/devis/${quote.id}`"
                    >Voir le devis {{ quote.number }}</Link
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
                :value="`${totals.amendmentsAmount >= 0 ? '+' : ''}${fcfa(totals.amendmentsAmount)}`"
                tone="info"
            />
            <KpiCard
                label="Montant actualisé"
                :value="fcfa(totals.updatedAmount)"
                tone="success"
            />
            <KpiCard
                label="Délai contractuel"
                :value="`${totals.contractualDays} jours`"
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
                        v-if="can('marches.create')"
                        size="sm"
                        variant="outline"
                        @click="amendmentOpen = true"
                        >Ajouter</Button
                    >
                </div>
                <ul v-if="contract.amendments.length" class="divide-y text-sm">
                    <li
                        v-for="(a, i) in contract.amendments"
                        :key="a.number"
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
                                v-if="!a.validated && can('marches.validate')"
                                size="sm"
                                @click="validateAmendment(i)"
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
            <ul v-if="contractSituations.length" class="divide-y text-sm">
                <li
                    v-for="s in contractSituations"
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
                            >{{ fcfa(computeSituation(s).netTtc) }} TTC</span
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
            :initial="{ date: TODAY, days: 0 }"
            :fields="fields"
            @submit="addAmendment"
        />
    </div>
</template>
