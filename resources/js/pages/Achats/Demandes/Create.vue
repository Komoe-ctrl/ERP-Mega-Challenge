<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import MissingArticleReportController from '@/actions/App/Http/Controllers/Achats/MissingArticleReportController';
import PurchaseRequestController from '@/actions/App/Http/Controllers/Achats/PurchaseRequestController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { addDays, fcfa, number, TODAY } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import type { PurchaseRequest, Urgency } from '@/types/erp';

type ArticleOption = {
    id: number;
    label: string;
    category: string;
    unit: string;
    price: number;
};
type BudgetInfo = {
    revised: number;
    committed: number;
    actual: number;
    remaining: number;
    consumption: number;
    lines: { id: number; post: string; nature: string; remaining: number }[];
};

/** `request` : correction d'une demande renvoyée (ou reprise d'un brouillon). */
const props = defineProps<{
    request: PurchaseRequest | null;
    presetChantier: number | null;
    chantiers: { id: number; code: string; name: string }[];
    budgets: Record<number, BudgetInfo>;
    articles: ArticleOption[];
    categories: string[];
    threshold: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
});

type FormLine = {
    key: number;
    articleId: number | null;
    quantity: number;
    needDate: string;
    urgency: Urgency;
};

let nextKey = 1;
const newLine = (): FormLine => ({
    key: nextKey++,
    articleId: null,
    quantity: 1,
    needDate: addDays(TODAY, 3),
    urgency: 'normale',
});

const existing = props.request;
const title = existing
    ? `Corriger ${existing.number}`
    : 'Nouvelle demande d’achat';

const chantierId = ref<number | null>(
    existing?.chantierId ??
        props.presetChantier ??
        props.chantiers[0]?.id ??
        null,
);
const budgetLineId = ref<number | null>(existing?.budgetLineId ?? null);
const justification = ref(existing?.justification ?? '');
const lines = reactive<FormLine[]>(
    existing
        ? existing.lines.map((l) => ({
              key: nextKey++,
              articleId: l.articleId,
              quantity: l.quantity,
              needDate: l.needDate < TODAY ? TODAY : l.needDate,
              urgency: l.urgency,
          }))
        : [newLine()],
);
const missingOpen = ref(false);
const attempted = ref(false);

const articleById = (id: number | null) =>
    props.articles.find((a) => a.id === id);

// RG8.2 : le prix vient obligatoirement du référentiel, jamais saisi.
const priceOf = (l: FormLine) => articleById(l.articleId)?.price ?? 0;
const lineTotal = (l: FormLine) => priceOf(l) * (l.quantity || 0);
const total = computed(() => lines.reduce((s, l) => s + lineTotal(l), 0));

// RG8.3 : budget disponible du chantier affiché pendant la saisie, en direct.
const budget = computed<BudgetInfo | null>(
    () => props.budgets[chantierId.value ?? 0] ?? null,
);
const hasBudget = computed(() => (budget.value?.revised ?? 0) > 0);
const remaining = computed(() => budget.value?.remaining ?? 0);
const availableAfter = computed(() => remaining.value - total.value);
const consumptionAfter = computed(() =>
    budget.value && budget.value.revised
        ? ((budget.value.committed + budget.value.actual + total.value) /
              budget.value.revised) *
          100
        : 0,
);
const overBudget = computed(() => hasBudget.value && availableAfter.value < 0);
// RG8.7 : indication seulement, le serveur décide du circuit de validation.
const needsDirection = computed(
    () => overBudget.value || total.value > props.threshold,
);

// Validation en français, affichée sous les champs après une tentative d'envoi.
const lineErrors = (l: FormLine) => ({
    article: l.articleId ? '' : 'Choisissez un article.',
    quantity: l.quantity > 0 ? '' : 'La quantité doit être supérieure à 0.',
    date:
        l.needDate && l.needDate >= TODAY
            ? ''
            : 'La date de besoin ne peut pas être dans le passé.',
});
const justificationError = computed(() =>
    justification.value.trim().length >= 5
        ? ''
        : 'La justification est obligatoire (5 caractères minimum).',
);
const valid = computed(
    () =>
        chantierId.value !== null &&
        lines.length > 0 &&
        lines.every((l) => Object.values(lineErrors(l)).every((e) => !e)),
);

// Le serveur refait toute la validation ; aucun prix n'est envoyé (RG8.2).
function save(submit: boolean) {
    attempted.value = true;

    if (!valid.value || (submit && justificationError.value)) {
        toast.error('Corrigez les champs signalés avant d’enregistrer.');

        return;
    }

    const payload = {
        chantier_id: chantierId.value,
        budget_line_id: budgetLineId.value,
        justification: justification.value,
        submit,
        lines: lines.map((l) => ({
            article_id: l.articleId,
            quantity: l.quantity,
            need_date: l.needDate,
            urgency: l.urgency,
        })),
    };
    const options = {
        onError: (errors: Record<string, string>) =>
            toast.error(Object.values(errors)[0]),
    };

    if (existing) {
        router.put(
            PurchaseRequestController.update.url(existing.id),
            payload as never,
            options,
        );
    } else {
        router.post(
            PurchaseRequestController.store.url(),
            payload as never,
            options,
        );
    }
}

function reportMissing(label: string) {
    router.post(
        MissingArticleReportController.store.url(),
        { label, chantier_id: chantierId.value },
        {
            preserveScroll: true,
            onError: (errors) => toast.error(Object.values(errors)[0]),
        },
    );
}

const field =
    'h-12 w-full rounded-lg border border-input bg-transparent px-3 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl space-y-4 p-4 pb-40">
        <div>
            <h1 class="font-display text-xl font-semibold">{{ title }}</h1>
            <p class="text-sm text-muted-foreground">
                Les prix viennent du référentiel : vous ne saisissez que les
                quantités.
            </p>
        </div>

        <p
            v-if="existing?.status === 'renvoyee'"
            class="rounded-xl bg-brand-soft p-3 text-sm text-brand-strong"
        >
            Renvoyée pour correction : «
            {{ existing.history[existing.history.length - 1]?.reason }} »
        </p>

        <section class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs">
            <div class="grid gap-2">
                <Label for="chantier">Chantier</Label>
                <select id="chantier" v-model="chantierId" :class="field">
                    <option v-for="c in chantiers" :key="c.id" :value="c.id">
                        {{ c.code }} — {{ c.name }}
                    </option>
                </select>
            </div>

            <div v-if="budget && budget.lines.length" class="grid gap-2">
                <Label for="poste">Poste du déboursé sec</Label>
                <select id="poste" v-model="budgetLineId" :class="field">
                    <option :value="null">Automatique (matériaux)</option>
                    <option v-for="l in budget.lines" :key="l.id" :value="l.id">
                        {{ l.post }} — reste {{ fcfa(l.remaining) }}
                    </option>
                </select>
            </div>

            <!-- Budget disponible en direct -->
            <div
                v-if="hasBudget"
                class="rounded-xl p-3"
                :class="overBudget ? 'bg-danger-soft' : 'bg-muted'"
                aria-live="polite"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium">Budget disponible</span>
                    <span
                        class="font-display text-lg font-semibold tabular-nums"
                        :class="overBudget ? 'text-danger' : ''"
                        >{{ fcfa(availableAfter) }}</span
                    >
                </div>
                <p class="mb-2 text-xs text-muted-foreground">
                    Avant cette demande : {{ fcfa(remaining) }}
                </p>
                <ProgressBar
                    :value="consumptionAfter"
                    :tone="consumptionTone(consumptionAfter)"
                    :label="`Consommation après la demande : ${number(consumptionAfter, 1)} %`"
                    :show-value="false"
                />
            </div>
            <p
                v-else
                class="rounded-xl bg-muted p-3 text-sm text-muted-foreground"
            >
                Budget non renseigné pour ce chantier.
            </p>
        </section>

        <section
            v-for="(l, i) in lines"
            :key="l.key"
            class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
        >
            <div class="flex items-center justify-between">
                <h2 class="font-display font-semibold">Ligne {{ i + 1 }}</h2>
                <Button
                    v-if="lines.length > 1"
                    variant="ghost"
                    size="icon"
                    aria-label="Supprimer la ligne"
                    @click="lines.splice(i, 1)"
                    ><Trash2
                /></Button>
            </div>

            <div class="grid gap-2">
                <Label :for="`article-${l.key}`">Article</Label>
                <select
                    :id="`article-${l.key}`"
                    v-model="l.articleId"
                    :class="field"
                    :aria-invalid="attempted && !!lineErrors(l).article"
                >
                    <option :value="null" disabled>Choisir un article…</option>
                    <optgroup v-for="cat in categories" :key="cat" :label="cat">
                        <option
                            v-for="a in articles.filter(
                                (x) => x.category === cat,
                            )"
                            :key="a.id"
                            :value="a.id"
                        >
                            {{ a.label }}
                        </option>
                    </optgroup>
                </select>
                <p
                    v-if="attempted && lineErrors(l).article"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ lineErrors(l).article }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label :for="`qty-${l.key}`"
                        >Quantité{{
                            l.articleId
                                ? ` (${articleById(l.articleId)?.unit})`
                                : ''
                        }}</Label
                    >
                    <Input
                        :id="`qty-${l.key}`"
                        v-model.number="l.quantity"
                        type="number"
                        min="1"
                        step="1"
                        inputmode="decimal"
                        class="h-12 text-base"
                    />
                    <p
                        v-if="attempted && lineErrors(l).quantity"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ lineErrors(l).quantity }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>Prix unitaire (référentiel)</Label>
                    <div
                        class="flex h-12 items-center rounded-lg border bg-muted px-3 text-base text-muted-foreground tabular-nums"
                        aria-readonly="true"
                    >
                        {{ l.articleId ? fcfa(priceOf(l)) : '—' }}
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label :for="`date-${l.key}`">Date de besoin</Label>
                    <Input
                        :id="`date-${l.key}`"
                        v-model="l.needDate"
                        type="date"
                        class="h-12 text-base"
                    />
                    <p
                        v-if="attempted && lineErrors(l).date"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ lineErrors(l).date }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label :for="`urg-${l.key}`">Urgence</Label>
                    <select
                        :id="`urg-${l.key}`"
                        v-model="l.urgency"
                        :class="field"
                    >
                        <option value="normale">Normale</option>
                        <option value="urgente">Urgente</option>
                        <option value="critique">Critique</option>
                    </select>
                </div>
            </div>
            <p class="text-right text-sm font-medium tabular-nums">
                Sous-total : {{ fcfa(lineTotal(l)) }}
            </p>
        </section>

        <div class="flex flex-wrap gap-2">
            <Button variant="outline" @click="lines.push(newLine())"
                ><Plus /> Ajouter une ligne</Button
            >
            <Button variant="ghost" @click="missingOpen = true"
                >Article introuvable ? Le signaler</Button
            >
        </div>

        <section class="space-y-2 rounded-2xl border bg-card p-4 shadow-xs">
            <Label for="justification">Justification</Label>
            <textarea
                id="justification"
                v-model="justification"
                rows="3"
                class="w-full rounded-lg border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40"
                placeholder="À quoi servent ces matériaux ?"
                :aria-invalid="attempted && !!justificationError"
            />
            <p
                v-if="attempted && justificationError"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ justificationError }}
            </p>
        </section>

        <!-- Barre fixe : total, alertes, actions -->
        <div
            class="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 p-3 backdrop-blur"
        >
            <div class="mx-auto max-w-3xl space-y-2">
                <p
                    v-if="needsDirection"
                    class="flex items-start gap-2 rounded-lg bg-warning-soft p-2 text-sm text-warning"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <span v-if="overBudget"
                        >Cette demande dépasse le budget restant : validation de
                        la Direction requise.</span
                    >
                    <span v-else
                        >Montant supérieur à {{ fcfa(threshold) }} : validation
                        de la Direction requise.</span
                    >
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="flex w-full items-baseline justify-between gap-2 sm:w-auto sm:flex-1 sm:flex-col sm:justify-start"
                    >
                        <p class="text-xs text-muted-foreground">
                            Total de la demande
                        </p>
                        <p
                            class="truncate font-display text-lg font-semibold tabular-nums"
                        >
                            {{ fcfa(total) }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="lg"
                        class="flex-1 sm:flex-none"
                        as-child
                        ><Link href="/achats/demandes">Annuler</Link></Button
                    >
                    <Button
                        variant="secondary"
                        size="lg"
                        class="flex-1 sm:flex-none"
                        @click="save(false)"
                        >Brouillon</Button
                    >
                    <Button
                        size="lg"
                        class="flex-1 bg-brand text-brand-foreground hover:bg-brand/90 sm:flex-none"
                        @click="save(true)"
                        >Soumettre</Button
                    >
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="missingOpen"
            title="Signaler un article manquant"
            description="Le Service Achats BTP recevra une demande d’ajout au référentiel."
            reason-label="Article à ajouter (libellé et unité)"
            confirm-label="Envoyer"
            require-reason
            @confirm="reportMissing"
        />
    </div>
</template>
