<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { addDays, fcfa, number, TODAY } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import {
    articleById,
    articles,
    budgetOf,
    budgetTotals,
    categories,
    DIRECTION_THRESHOLD,
} from '@/mocks/achats';
import { chantiers } from '@/mocks/chantiers';
import type { Urgency } from '@/types/erp';

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

const chantierId = ref<number | null>(1);
const justification = ref('');
const lines = reactive<FormLine[]>([newLine()]);
const missingOpen = ref(false);

// RG8.2 : le prix vient obligatoirement du référentiel, jamais saisi.
const priceOf = (l: FormLine) =>
    l.articleId ? (articleById(l.articleId)?.price ?? 0) : 0;
const lineTotal = (l: FormLine) => priceOf(l) * (l.quantity || 0);
const total = computed(() => lines.reduce((s, l) => s + lineTotal(l), 0));

// RG8.3 : budget disponible du chantier affiché pendant la saisie, en direct.
const totals = computed(() => budgetTotals(budgetOf(chantierId.value ?? 0)));
const hasBudget = computed(() => totals.value.revised > 0);
const availableAfter = computed(() => totals.value.remaining - total.value);
const consumptionAfter = computed(() =>
    totals.value.revised
        ? ((totals.value.committed + totals.value.actual + total.value) /
              totals.value.revised) *
          100
        : 0,
);
const overBudget = computed(() => hasBudget.value && availableAfter.value < 0);
const needsDirection = computed(
    () => overBudget.value || total.value > DIRECTION_THRESHOLD,
); // RG8.7

const valid = computed(
    () =>
        chantierId.value !== null &&
        lines.length > 0 &&
        lines.every((l) => l.articleId && l.quantity > 0),
);

function save(submit: boolean) {
    toast.success(
        submit
            ? needsDirection.value
                ? 'Demande soumise : validation de la Direction requise (maquette).'
                : 'Demande soumise au valideur (maquette).'
            : 'Brouillon enregistré (maquette).',
    );
    router.visit('/achats/demandes');
}

function reportMissing(label: string) {
    toast.success(
        `Demande d’ajout transmise au Service Achats BTP : « ${label} » (maquette).`,
    );
}

const field =
    'h-12 w-full rounded-lg border border-input bg-transparent px-3 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head title="Nouvelle demande d’achat" />

    <div class="mx-auto w-full max-w-3xl space-y-4 p-4 pb-40">
        <div>
            <h1 class="font-display text-xl font-semibold">
                Nouvelle demande d’achat
            </h1>
            <p class="text-sm text-muted-foreground">
                Les prix viennent du référentiel : vous ne saisissez que les
                quantités.
            </p>
        </div>

        <section class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs">
            <div class="grid gap-2">
                <Label for="chantier">Chantier</Label>
                <select id="chantier" v-model="chantierId" :class="field">
                    <option
                        v-for="c in chantiers.filter(
                            (x) => x.status !== 'cloture',
                        )"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.code }} — {{ c.name }}
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
                    >
                        {{ fcfa(availableAfter) }}
                    </span>
                </div>
                <p class="mb-2 text-xs text-muted-foreground">
                    Avant cette demande : {{ fcfa(totals.remaining) }}
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
                >
                    <Trash2 />
                </Button>
            </div>

            <div class="grid gap-2">
                <Label :for="`article-${l.key}`">Article</Label>
                <select
                    :id="`article-${l.key}`"
                    v-model="l.articleId"
                    :class="field"
                >
                    <option :value="null" disabled>Choisir un article…</option>
                    <optgroup v-for="cat in categories" :key="cat" :label="cat">
                        <option
                            v-for="a in articles.filter(
                                (x) => x.category === cat && x.active,
                            )"
                            :key="a.id"
                            :value="a.id"
                        >
                            {{ a.label }}
                        </option>
                    </optgroup>
                </select>
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
            />
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
                        >Montant supérieur à {{ fcfa(DIRECTION_THRESHOLD) }} :
                        validation de la Direction requise.</span
                    >
                </p>
                <div class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">
                            Total de la demande
                        </p>
                        <p
                            class="truncate font-display text-lg font-semibold tabular-nums"
                        >
                            {{ fcfa(total) }}
                        </p>
                    </div>
                    <Button variant="outline" size="lg" as-child>
                        <Link href="/achats/demandes">Annuler</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        size="lg"
                        :disabled="!valid"
                        @click="save(false)"
                        >Brouillon</Button
                    >
                    <Button
                        size="lg"
                        class="bg-brand text-brand-foreground hover:bg-brand/90"
                        :disabled="!valid"
                        @click="save(true)"
                    >
                        Soumettre
                    </Button>
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
