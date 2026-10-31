<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Lock, Printer } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ContractController from '@/actions/App/Http/Controllers/Marches/ContractController';
import SituationController from '@/actions/App/Http/Controllers/Situations/SituationController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computeSituation } from '@/lib/billing';
import { dateFr, fcfa, percent, TODAY } from '@/lib/format';
import type { FieldDef, FieldValues } from '@/types/forms';

type Calc = ReturnType<typeof computeSituation>;

const props = defineProps<{
    situation: {
        id: number;
        number: number;
        contractId: number;
        contractNumber: string;
        clientName: string;
        date: string;
        status: 'brouillon' | 'validee' | 'payee';
        progressCumul: number;
        previousProgress: number;
        marketAmount: number;
        retentionRate: number;
        advanceRefundRate: number;
        vatRate: number;
        calc: Calc;
        paid: number;
        remaining: number;
    };
    works: { id: number; label: string; total: number; progress: number }[];
    payments: { id: number; date: string; amount: number; reference: string }[];
    can: { update: boolean; validate: boolean; pay: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Situations de travaux', href: '/situations' }],
    },
});

const copy = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

// Copie locale modifiable ; elle est resynchronisée à chaque réponse du serveur.
const situation = reactive(copy(props.situation));
const workProgress = reactive<Record<number, number>>({});

function reset() {
    Object.assign(situation, copy(props.situation));

    for (const w of props.works) {
        workProgress[w.id] = w.progress;
    }
}

reset();
watch(() => [props.situation, props.works], reset);

const locked = computed(() => situation.status !== 'brouillon');
const validateOpen = ref(false);
const paymentOpen = ref(false);
const totalWorks = computed(() =>
    props.works.reduce((sum, w) => sum + w.total, 0),
);

// RG11.1 : avancement par ouvrage (devis du marché) ; sans ouvrage, un seul curseur global.
const progress = computed(() => {
    if (!props.works.length || locked.value) {
        return situation.progressCumul;
    }

    if (totalWorks.value === 0) {
        return 0;
    }

    return (
        Math.round(
            (props.works.reduce(
                (sum, w) => sum + w.total * (workProgress[w.id] ?? 0),
                0,
            ) /
                totalWorks.value) *
                10,
        ) / 10
    );
});

// Brouillon : calcul en direct ; situation figée : valeurs du serveur.
const calc = computed<Calc>(() =>
    locked.value
        ? props.situation.calc
        : computeSituation({
              ...situation,
              progressCumul: progress.value,
          }),
);

const progressIssue = computed(() =>
    progress.value < situation.previousProgress
        ? `L’avancement cumulé (${progress.value} %) ne peut pas être inférieur à celui de la situation précédente (${situation.previousProgress} %).`
        : '',
);
const ratesIssue = computed(() =>
    situation.retentionRate < 0 ||
    situation.retentionRate > 100 ||
    situation.advanceRefundRate < 0 ||
    situation.advanceRefundRate > 100 ||
    situation.vatRate < 0 ||
    situation.vatRate > 100
        ? 'Les taux doivent être compris entre 0 et 100 %.'
        : '',
);

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0] ?? 'Saisie invalide.');

/** Enregistre le brouillon puis enchaîne (validation). */
function save(then?: () => void) {
    if (progressIssue.value || ratesIssue.value) {
        toast.error('Corrigez les valeurs signalées avant de continuer.');

        return;
    }

    router.put(
        SituationController.update.url(situation.id),
        {
            situation_date: situation.date,
            ...(props.works.length
                ? { works: { ...workProgress } }
                : { progress: situation.progressCumul }),
            retention_rate: situation.retentionRate,
            advance_refund_rate: situation.advanceRefundRate,
            vat_rate: situation.vatRate,
        },
        { preserveScroll: true, onError, onSuccess: then },
    );
}

function validate() {
    save(() =>
        router.post(
            SituationController.validateSituation.url(situation.id),
            {},
            { preserveScroll: true, onError },
        ),
    );
}

function askValidate() {
    if (progressIssue.value || ratesIssue.value) {
        toast.error('Corrigez les valeurs signalées avant de valider.');

        return;
    }

    validateOpen.value = true;
}

const paymentFields = computed<FieldDef[]>(() => [
    {
        name: 'amount',
        label: 'Montant reçu',
        type: 'number',
        required: true,
        min: 1,
        max: props.situation.remaining,
        step: 1,
        unit: 'FCFA',
        hint: `Solde restant dû : ${fcfa(props.situation.remaining)}.`,
    },
    {
        name: 'paid_at',
        label: 'Date du paiement',
        type: 'date',
        required: true,
    },
    { name: 'reference', label: 'Référence (virement, chèque…)' },
]);

function addPayment(v: FieldValues) {
    router.post(
        SituationController.storePayment.url(situation.id),
        {
            amount: Number(v.amount),
            paid_at: String(v.paid_at),
            reference: v.reference ? String(v.reference) : null,
        },
        { preserveScroll: true, onError },
    );
}

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Situation n°${situation.number}`" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="`Situation n°${situation.number}`"
            :description="`${situation.contractNumber} · ${situation.clientName} · ${dateFr(situation.date)}`"
        >
            <StatusBadge kind="situation" :status="situation.status" />
            <Button variant="outline" as-child
                ><Link :href="ContractController.show.url(situation.contractId)"
                    >Voir le marché</Link
                ></Button
            >
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer</Button
            >
            <Button v-if="can.update" variant="outline" @click="save()"
                >Enregistrer</Button
            >
            <Button
                v-if="can.validate"
                class="bg-success text-white hover:bg-success/90"
                @click="askValidate"
                >Valider et figer</Button
            >
            <Button v-if="can.pay" @click="paymentOpen = true"
                >Enregistrer un paiement</Button
            >
        </PageHeader>

        <p
            v-if="locked"
            class="flex items-center gap-2 rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            <Lock class="size-4" /> Situation figée : une correction passe par
            la situation suivante.
        </p>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="font-display font-semibold">Avancement</h2>

                <div v-if="works.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Ouvrage</th>
                                <th class="py-1 text-right font-medium">
                                    Montant du marché
                                </th>
                                <th class="py-1 text-right font-medium">
                                    Cumul réalisé
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="w in works" :key="w.id" class="border-t">
                                <td class="py-1.5 pr-2">{{ w.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ fcfa(w.total) }}
                                </td>
                                <td class="py-1.5 text-right">
                                    <Input
                                        v-if="!locked"
                                        v-model.number="workProgress[w.id]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="ml-auto w-20 text-right"
                                        :aria-label="`Avancement cumulé : ${w.label}`"
                                    />
                                    <span v-else class="tabular-nums">{{
                                        percent(w.progress)
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Cumul de l’ouvrage = quantités réalisées × prix du
                        marché (RG11.1).
                    </p>
                </div>
                <label v-else class="grid gap-1.5 text-sm">
                    Avancement cumulé (%)
                    <input
                        v-model.number="situation.progressCumul"
                        type="range"
                        min="0"
                        max="100"
                        :disabled="locked"
                        class="h-8 w-full accent-brand"
                    />
                </label>

                <p class="font-display text-2xl font-semibold tabular-nums">
                    {{ percent(progress, 1) }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >d’avancement cumulé</span
                    >
                </p>
                <ProgressBar
                    :value="progress"
                    :marker="situation.previousProgress"
                    label="Cumul (trait = situation précédente)"
                    :show-value="false"
                />
                <p
                    v-if="progressIssue"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ progressIssue }}
                </p>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <label class="grid gap-1"
                        >Retenue (%)<Input
                            v-model.number="situation.retentionRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="locked"
                    /></label>
                    <label class="grid gap-1"
                        >Acompte (%)<Input
                            v-model.number="situation.advanceRefundRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="locked"
                    /></label>
                    <label class="grid gap-1"
                        >TVA (%)<Input
                            v-model.number="situation.vatRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="locked"
                    /></label>
                </div>
                <p
                    v-if="ratesIssue"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ ratesIssue }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Taux repris du marché (conditions de paiement, RG6.3 et
                    RG11.2).
                </p>
            </section>

            <section class="print-area rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Calcul du net à payer
                </h2>
                <dl class="space-y-1.5 text-sm tabular-nums">
                    <div class="flex justify-between">
                        <dt>Montant du marché</dt>
                        <dd>{{ fcfa(situation.marketAmount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Cumul n ({{ percent(progress, 1) }})</dt>
                        <dd>{{ fcfa(calc.cumul) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Cumul n−1 ({{
                                percent(situation.previousProgress)
                            }})
                        </dt>
                        <dd>−{{ fcfa(calc.previousCumul) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-1.5 font-medium"
                    >
                        <dt>Travaux de la période</dt>
                        <dd>{{ fcfa(calc.amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Retenue de garantie ({{ situation.retentionRate }}
                            %)
                        </dt>
                        <dd>−{{ fcfa(calc.retention) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Remboursement d’acompte ({{
                                situation.advanceRefundRate
                            }}
                            %)
                        </dt>
                        <dd>−{{ fcfa(calc.advanceRefund) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-1.5 font-medium"
                    >
                        <dt>Net à payer hors taxes</dt>
                        <dd>{{ fcfa(calc.netHt) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>TVA ({{ situation.vatRate }} %)</dt>
                        <dd>+{{ fcfa(calc.vat) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t-2 border-brand pt-1.5 font-display text-lg font-semibold"
                    >
                        <dt>Net à payer TTC</dt>
                        <dd>{{ fcfa(calc.netTtc) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section v-if="locked" class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display font-semibold">Paiements reçus</h2>
                <p class="text-sm text-muted-foreground tabular-nums">
                    Reçu {{ fcfa(situation.paid) }} · reste
                    {{ fcfa(situation.remaining) }}
                </p>
            </div>
            <ul v-if="payments.length" class="divide-y text-sm">
                <li
                    v-for="p in payments"
                    :key="p.id"
                    class="flex flex-wrap justify-between gap-2 py-2"
                >
                    <span
                        >{{ dateFr(p.date) }}
                        <span class="text-muted-foreground">{{
                            p.reference
                        }}</span></span
                    >
                    <span class="tabular-nums">{{ fcfa(p.amount) }}</span>
                </li>
            </ul>
            <EmptyState v-else compact title="Aucun paiement" />
        </section>

        <Button variant="ghost" as-child
            ><Link :href="SituationController.index.url()"
                >Retour à la liste</Link
            ></Button
        >

        <ConfirmDialog
            v-model:open="validateOpen"
            title="Valider et figer la situation"
            description="Une situation validée est numérotée et figée : elle ne pourra plus être modifiée. Une correction passera par la situation suivante (RG11.3)."
            confirm-label="Valider et figer"
            @confirm="validate"
        />
        <FormDialog
            v-model:open="paymentOpen"
            title="Enregistrer un paiement"
            :initial="{ paid_at: TODAY }"
            :fields="paymentFields"
            submit-label="Enregistrer"
            @submit="addPayment"
        />
    </div>
</template>
