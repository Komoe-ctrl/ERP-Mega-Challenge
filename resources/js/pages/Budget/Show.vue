<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import BudgetController from '@/actions/App/Http/Controllers/Budget/BudgetController';
import BarChart from '@/components/charts/BarChart.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateFr, fcfa, number } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import type { BudgetLine } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Totals = {
    initial: number;
    revised: number;
    committed: number;
    actual: number;
    remaining: number;
    consumption: number;
    level: 'ok' | 'warning' | 'critical';
};
type Revision = {
    id: number;
    date: string;
    post: string;
    amount: number;
    reason: string;
    author: string;
    status: 'en_attente' | 'validee' | 'rejetee';
    decidedBy: string | null;
    decisionReason: string | null;
    canDecide: boolean;
};
type Expense = {
    id: number;
    date: string;
    label: string;
    post: string;
    amount: number;
    receipt: string;
    receiptUrl: string;
    author: string;
};

const props = defineProps<{
    chantier: { id: number; code: string; name: string };
    lines: BudgetLine[];
    totals: Totals;
    revisions: Revision[];
    expenses: Expense[];
    canManage: boolean;
    canExpense: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Budget', href: '/budget' }] },
});

const lineConsumption = (l: BudgetLine) =>
    l.revised ? ((l.committed + l.actual) / l.revised) * 100 : 0;
const natureLabel = {
    materiaux: 'Matériaux',
    main_oeuvre: 'Main-d’œuvre',
    materiel: 'Matériel',
} as const;
const revisionLabel = {
    en_attente: 'En attente de validation',
    validee: 'Validée',
    rejetee: 'Rejetée',
} as const;

const postOptions = computed(() =>
    props.lines.map((l) => ({ value: String(l.id), label: l.post })),
);
const lineOpen = ref(false);
const revisionOpen = ref(false);
const expenseOpen = ref(false);
const rejecting = ref<number | null>(null);
const rejectOpen = computed({
    get: () => rejecting.value !== null,
    set: (v) => {
        if (!v) rejecting.value = null;
    },
});

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0]);

const lineFields: FieldDef[] = [
    { name: 'post', label: 'Poste', required: true, minLength: 3, wide: true },
    {
        name: 'nature',
        label: 'Nature',
        type: 'select',
        required: true,
        options: Object.entries(natureLabel).map(([value, label]) => ({
            value,
            label,
        })),
    },
    {
        name: 'amount',
        label: 'Budget initial',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        hint: 'Déboursé sec du poste. Il ne change ensuite que par révision validée.',
    },
];
const revisionFields = computed<FieldDef[]>(() => [
    {
        name: 'budget_line_id',
        label: 'Poste',
        type: 'select',
        required: true,
        options: postOptions.value,
    },
    {
        name: 'amount',
        label: 'Montant de la révision',
        type: 'number',
        required: true,
        step: 1,
        unit: 'FCFA',
        hint: 'Positif pour augmenter le budget, négatif pour le diminuer.',
        validate: (value) =>
            Number(value) === 0 ? 'Le montant ne peut pas être nul.' : null,
    },
    {
        name: 'reason',
        label: 'Motif',
        type: 'textarea',
        required: true,
        minLength: 10,
        hint: 'Toute révision est historisée avec son motif, son auteur et sa validation (RG10.2).',
    },
]);

function addLine(v: FieldValues) {
    router.post(BudgetController.storeLine.url(props.chantier.id), v as never, {
        preserveScroll: true,
        onError,
    });
}
function requestRevision(v: FieldValues) {
    router.post(
        BudgetController.requestRevision.url(props.chantier.id),
        v as never,
        { preserveScroll: true, onError },
    );
}
function validateRevision(id: number) {
    router.post(
        BudgetController.validateRevision.url(id),
        {},
        { preserveScroll: true, onError },
    );
}
function rejectRevision(reason: string) {
    if (rejecting.value !== null) {
        router.post(
            BudgetController.rejectRevision.url(rejecting.value),
            { reason },
            { preserveScroll: true, onError },
        );
    }
}

// RG10.4 : dépense diverse avec justificatif obligatoire (envoi de fichier).
const expense = useForm({
    label: '',
    budget_line_id: '',
    amount: '' as string | number,
    receipt: null as File | null,
});

function submitExpense() {
    expense.post(BudgetController.storeExpense.url(props.chantier.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            expenseOpen.value = false;
            expense.reset();
        },
        onError,
    });
}

const chartLabels = computed(() => props.lines.map((l) => l.post));
const chartSets = computed(() => [
    {
        label: 'Révisé (M FCFA)',
        data: props.lines.map((l) => Math.round(l.revised / 1_000_000)),
    },
    {
        label: 'Engagé + réel (M FCFA)',
        data: props.lines.map((l) =>
            Math.round((l.committed + l.actual) / 1_000_000),
        ),
    },
]);

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head :title="`Budget — ${chantier.name}`" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="`Budget — ${chantier.name}`"
            :description="chantier.code"
        >
            <StatusBadge kind="budget" :status="totals.level" />
            <Button variant="outline" as-child
                ><Link :href="BudgetController.index.url()"
                    >Tous les chantiers</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="`/chantiers/${chantier.id}`"
                    >Fiche chantier</Link
                ></Button
            >
            <Button v-if="canManage" variant="outline" @click="lineOpen = true"
                >Ajouter un poste</Button
            >
            <Button
                v-if="canManage && lines.length"
                variant="outline"
                @click="revisionOpen = true"
                >Réviser le budget</Button
            >
            <Button
                v-if="canExpense && lines.length"
                @click="expenseOpen = true"
                >Saisir une dépense</Button
            >
        </PageHeader>

        <p
            v-if="totals.level !== 'ok'"
            class="rounded-lg p-3 text-sm"
            :class="
                totals.level === 'critical'
                    ? 'bg-danger-soft text-danger'
                    : 'bg-warning-soft text-warning'
            "
        >
            {{
                totals.level === 'critical'
                    ? 'Alerte critique : le budget révisé est entièrement consommé.'
                    : 'Avertissement : le seuil d’avertissement du budget révisé est atteint.'
            }}
            Le conducteur de travaux et la Direction technique sont notifiés.
        </p>

        <div v-if="lines.length" class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <KpiCard
                    label="Budget initial (DS)"
                    :value="fcfa(totals.initial)"
                />
                <KpiCard label="Budget révisé" :value="fcfa(totals.revised)" />
                <KpiCard
                    label="Engagé"
                    :value="fcfa(totals.committed)"
                    hint="Demandes validées, non reçues"
                />
                <KpiCard label="Coût réel" :value="fcfa(totals.actual)" />
                <KpiCard
                    label="Reste à engager"
                    :value="fcfa(totals.remaining)"
                    :tone="totals.remaining < 0 ? 'danger' : 'success'"
                />
            </div>

            <section class="space-y-2 rounded-xl border bg-card p-4 shadow-xs">
                <ProgressBar
                    :value="totals.consumption"
                    :tone="consumptionTone(totals.consumption)"
                    :label="`Consommation du budget révisé : ${number(totals.consumption, 1)} %`"
                    :show-value="false"
                />
                <p class="text-xs text-muted-foreground">
                    Consommé = engagé + coût réel. Seuils paramétrables dans
                    Administration.
                </p>
            </section>

            <section
                class="overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 font-medium">Poste</th>
                                <th class="p-3 font-medium">Nature</th>
                                <th class="p-3 text-right font-medium">
                                    Initial
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Révisé
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Engagé
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Coût réel
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Reste
                                </th>
                                <th class="p-3 font-medium">Consommation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in lines" :key="l.id" class="border-t">
                                <td class="p-3 font-medium">{{ l.post }}</td>
                                <td class="p-3">{{ natureLabel[l.nature] }}</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.initial) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.revised) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.committed) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.actual) }}
                                </td>
                                <td
                                    class="p-3 text-right tabular-nums"
                                    :class="
                                        l.revised - l.committed - l.actual < 0
                                            ? 'text-danger'
                                            : ''
                                    "
                                >
                                    {{
                                        fcfa(l.revised - l.committed - l.actual)
                                    }}
                                </td>
                                <td class="min-w-36 p-3">
                                    <ProgressBar
                                        :value="lineConsumption(l)"
                                        :tone="
                                            consumptionTone(lineConsumption(l))
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td class="p-3" colspan="2">Total</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.initial) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.revised) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.committed) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.actual) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.remaining) }}
                                </td>
                                <td class="p-3 tabular-nums">
                                    {{ number(totals.consumption, 1) }} %
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Révisé et consommé par poste
                    </h2>
                    <BarChart :labels="chartLabels" :datasets="chartSets" />
                </section>
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Révisions du budget
                    </h2>
                    <ul v-if="revisions.length" class="divide-y text-sm">
                        <li v-for="r in revisions" :key="r.id" class="py-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium">
                                    {{ r.post }} : {{ r.amount > 0 ? '+' : ''
                                    }}{{ fcfa(r.amount) }}
                                </p>
                                <StatusBadge
                                    :label="revisionLabel[r.status]"
                                    :tone="
                                        r.status === 'validee'
                                            ? 'success'
                                            : r.status === 'rejetee'
                                              ? 'danger'
                                              : 'warning'
                                    "
                                />
                            </div>
                            <p class="text-muted-foreground">
                                « {{ r.reason }} »
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ dateFr(r.date) }} · {{ r.author }}
                                <template v-if="r.decidedBy">
                                    ·
                                    {{ revisionLabel[r.status].toLowerCase() }}
                                    par {{ r.decidedBy }}</template
                                >
                            </p>
                            <p
                                v-if="r.decisionReason"
                                class="text-xs text-muted-foreground"
                            >
                                Motif du rejet : « {{ r.decisionReason }} »
                            </p>
                            <div v-if="r.canDecide" class="mt-2 flex gap-2">
                                <Button
                                    size="sm"
                                    class="bg-success text-white hover:bg-success/90"
                                    @click="validateRevision(r.id)"
                                    >Valider</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-destructive"
                                    @click="rejecting = r.id"
                                    >Rejeter</Button
                                >
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else compact title="Aucune révision" />
                </section>
            </div>
        </div>
        <EmptyState
            v-else
            title="Budget non renseigné pour ce chantier"
            description="Ajoutez les postes du déboursé sec : ils conditionnent le passage du projet à « En cours » et l’engagement des demandes d’achat."
        />

        <section
            v-if="lines.length"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <h2 class="mb-3 font-display font-semibold">Dépenses diverses</h2>
            <ul v-if="expenses.length" class="divide-y text-sm">
                <li
                    v-for="e in expenses"
                    :key="e.id"
                    class="flex flex-wrap items-start justify-between gap-2 py-2.5"
                >
                    <span>
                        <span class="font-medium">{{ e.label }}</span>
                        <span class="block text-xs text-muted-foreground"
                            >{{ dateFr(e.date) }} · {{ e.post }} ·
                            {{ e.author }} · justificatif :
                            <a
                                v-if="e.receiptUrl"
                                :href="e.receiptUrl"
                                target="_blank"
                                rel="noopener"
                                class="underline"
                                >{{ e.receipt }}</a
                            ></span
                        >
                    </span>
                    <span class="tabular-nums">{{ fcfa(e.amount) }}</span>
                </li>
            </ul>
            <EmptyState v-else compact title="Aucune dépense diverse" />
        </section>

        <FormDialog
            v-model:open="lineOpen"
            title="Ajouter un poste du déboursé sec"
            :fields="lineFields"
            @submit="addLine"
        />
        <FormDialog
            v-model:open="revisionOpen"
            title="Réviser le budget"
            description="La révision doit être validée par la Direction avant de modifier le budget révisé."
            :fields="revisionFields"
            submit-label="Soumettre"
            @submit="requestRevision"
        />
        <ConfirmDialog
            v-model:open="rejectOpen"
            title="Rejeter la révision"
            confirm-label="Rejeter"
            require-reason
            destructive
            @confirm="rejectRevision"
        />

        <Dialog v-model:open="expenseOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Saisir une dépense diverse</DialogTitle>
                    <DialogDescription
                        >Petits achats, transport… imputés à un poste du budget.
                        Le justificatif est obligatoire
                        (RG10.4).</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-3" @submit.prevent="submitExpense">
                    <div class="grid gap-1.5">
                        <Label for="exp-label">Libellé</Label>
                        <Input id="exp-label" v-model="expense.label" />
                        <p
                            v-if="expense.errors.label"
                            class="text-sm text-destructive"
                        >
                            {{ expense.errors.label }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="exp-post">Poste imputé</Label>
                        <select
                            id="exp-post"
                            v-model="expense.budget_line_id"
                            :class="fieldClass"
                        >
                            <option value="" disabled>Choisir un poste…</option>
                            <option
                                v-for="p in postOptions"
                                :key="p.value"
                                :value="p.value"
                            >
                                {{ p.label }}
                            </option>
                        </select>
                        <p
                            v-if="expense.errors.budget_line_id"
                            class="text-sm text-destructive"
                        >
                            {{ expense.errors.budget_line_id }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="exp-amount">Montant (FCFA)</Label>
                        <Input
                            id="exp-amount"
                            v-model.number="expense.amount"
                            type="number"
                            min="1"
                            step="1"
                        />
                        <p
                            v-if="expense.errors.amount"
                            class="text-sm text-destructive"
                        >
                            {{ expense.errors.amount }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="exp-receipt">Justificatif</Label>
                        <input
                            id="exp-receipt"
                            type="file"
                            accept=".pdf,image/*"
                            :class="fieldClass"
                            @change="
                                expense.receipt =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                        <p
                            v-if="expense.errors.receipt"
                            class="text-sm text-destructive"
                        >
                            {{ expense.errors.receipt }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="expenseOpen = false"
                            >Annuler</Button
                        >
                        <Button type="submit" :disabled="expense.processing"
                            >Enregistrer</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
