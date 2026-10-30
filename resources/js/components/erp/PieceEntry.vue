<script setup lang="ts">
import { Check, FileDown, Plus, Printer, Search, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useLegacySession } from '@/composables/useLegacySession';
import { dateFr, fcfa, percent } from '@/lib/format';
import {
    defaultWorkflow,
    initialPieces,
    pieceKindLabels,
    pieceTotal,
    projectTrees,
    rubriqueByCode,
    rubriques,
} from '@/mocks/legacy';
import type { Piece, PieceKind, PieceLine, PieceStatus } from '@/types/legacy';

/**
 * Composant de saisie commun aux pièces (D.A., notes de frais, recettes, caisse).
 * Reprend le déroulé de l'application actuelle : période → n° de pièce → lignes,
 * budget de la rubrique en direct, bouton d'approbation du niveau suivant.
 */
const props = defineProps<{
    kind: PieceKind;
    title: string;
    /** Affiche seulement les pièces d'un état (écrans « Validation »). */
    onlyStatus?: PieceStatus[];
}>();

const { session } = useLegacySession();
const label = pieceKindLabels[props.kind];

const pieces = ref<Piece[]>(
    initialPieces
        .filter((p) => p.kind === props.kind)
        .map((p) => structuredClone(p)),
);
const search = ref('');
const selectedId = ref<number | null>(null);
const activeLine = ref(0);

const blank = (): Piece => ({
    id: Date.now(),
    kind: props.kind,
    number: '',
    title: '',
    period: '2026-10',
    date: '2026-10-29',
    requester: session.value.title,
    bcNumber: '',
    observation: '',
    proof: '',
    project: '',
    product: '',
    component: '',
    status: 'saisie',
    lines: [{ designation: '', quantity: 1, unitPrice: 0, rubrique: '' }],
    history: [],
});

const draft = ref<Piece>(blank());
const isNew = computed(() => selectedId.value === null);

const visible = computed(() =>
    pieces.value
        .filter((p) => !props.onlyStatus || props.onlyStatus.includes(p.status))
        .filter((p) =>
            `${p.number} ${p.title} ${p.requester}`
                .toLowerCase()
                .includes(search.value.toLowerCase()),
        ),
);

// Ligne d'état du circuit : combien de pièces attendent chaque niveau.
const waiting = computed(() => {
    const count = (s: PieceStatus) =>
        pieces.value.filter((p) => p.status === s).length;

    return [
        `${count('saisie')} ${label} en attente de validation du chef de service`,
        `${count('validee')} ${label} en attente d’approbation du Directeur Mega Challenge`,
    ];
});

const tree = computed(() =>
    projectTrees.find((t) => t.project === draft.value.project),
);
const components = computed(
    () =>
        tree.value?.products.find((p) => p.name === draft.value.product)
            ?.components ?? [],
);

const total = computed(() => pieceTotal(draft.value.lines));
const current = computed(() => draft.value.lines[activeLine.value]);
const rubrique = computed(() =>
    current.value ? rubriqueByCode(current.value.rubrique) : undefined,
);

// Budget en direct : le montant de la pièce en cours s'ajoute au « en cours ».
const budget = computed(() => {
    const r = rubrique.value;

    if (!r) {
        return null;
    }

    const own = draft.value.lines
        .filter((l) => l.rubrique === r.code)
        .reduce((s, l) => s + l.quantity * l.unitPrice, 0);
    const used = r.consumed + r.committed + (isNew.value ? own : 0);
    const remaining = r.budget - used;

    return {
        budget: r.budget,
        used,
        remaining,
        rate: r.budget ? (used / r.budget) * 100 : 0,
    };
});

const nextStep = computed(() => {
    const status = draft.value.status;

    if (isNew.value || status === 'approuvee' || status === 'rejetee') {
        return null;
    }

    return defaultWorkflow[status === 'saisie' ? 1 : 2] ?? null;
});
const canAdvance = computed(
    () =>
        nextStep.value !== null && nextStep.value.title === session.value.title,
);

const statusTone: Record<
    PieceStatus,
    'neutral' | 'warning' | 'success' | 'danger'
> = {
    saisie: 'neutral',
    validee: 'warning',
    approuvee: 'success',
    rejetee: 'danger',
};
const statusLabel: Record<PieceStatus, string> = {
    saisie: 'Saisie',
    validee: 'Validée par le chef de service',
    approuvee: 'Approuvée',
    rejetee: 'Rejetée',
};

function select(piece: Piece | null) {
    selectedId.value = piece?.id ?? null;
    draft.value = piece ? structuredClone(piece) : blank();
    activeLine.value = 0;
}

function addLine() {
    draft.value.lines.push({
        designation: '',
        quantity: 1,
        unitPrice: 0,
        rubrique: current.value?.rubrique ?? '',
    });
    activeLine.value = draft.value.lines.length - 1;
}

function removeLine(i: number) {
    draft.value.lines.splice(i, 1);
    activeLine.value = Math.max(
        0,
        Math.min(activeLine.value, draft.value.lines.length - 1),
    );
}

const lineAmount = (l: PieceLine): number => l.quantity * l.unitPrice;

function validate() {
    if (!draft.value.number.trim() || !draft.value.title.trim()) {
        toast.error('Saisissez le n° de pièce et l’intitulé avant de valider.');

        return;
    }

    if (draft.value.lines.some((l) => !l.designation.trim() || !l.rubrique)) {
        toast.error(
            'Chaque ligne doit avoir une désignation et une rubrique budgétaire.',
        );

        return;
    }

    if (isNew.value) {
        draft.value.history.push({
            at: '2026-10-29 09:00',
            by: session.value.title,
            action: 'Saisie',
            comment: '',
        });
        pieces.value.unshift(structuredClone(draft.value));
        selectedId.value = draft.value.id;
    } else {
        const i = pieces.value.findIndex((p) => p.id === draft.value.id);
        pieces.value[i] = structuredClone(draft.value);
    }

    toast.success(`Pièce ${draft.value.number} enregistrée (maquette).`);
}

function remove() {
    pieces.value = pieces.value.filter((p) => p.id !== draft.value.id);
    select(null);
    toast.success('Pièce supprimée (maquette).');
}

function advance() {
    if (!nextStep.value) {
        return;
    }

    const toStatus: PieceStatus =
        draft.value.status === 'saisie' ? 'validee' : 'approuvee';
    draft.value.status = toStatus;
    draft.value.history.push({
        at: '2026-10-29 09:00',
        by: session.value.title,
        action: nextStep.value.label,
        comment: '',
    });
    const i = pieces.value.findIndex((p) => p.id === draft.value.id);
    pieces.value[i] = structuredClone(draft.value);
    toast.success(`${nextStep.value.label} enregistrée (maquette).`);
}

function validateAll() {
    toast.success('Saisies validées (maquette).');
}

function extract() {
    toast.success('Extraction des pièces validées lancée (maquette).');
}

select(null);
</script>

<template>
    <div class="space-y-4 p-4" data-test="piece-entry">
        <div>
            <h1
                class="font-display text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ title }}
            </h1>
            <ul class="mt-1 space-y-0.5 text-sm text-muted-foreground">
                <li v-for="w in waiting" :key="w">{{ w }}</li>
            </ul>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" @click="validateAll">
                <Check /> Valider des saisies
            </Button>
            <Button variant="outline" size="sm" @click="extract">
                <FileDown /> Extraction des pièces validées
            </Button>
            <label class="relative">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Recherche"
                    class="h-8 rounded-md border bg-background pr-2 pl-8 text-sm"
                    aria-label="Recherche"
                />
            </label>
            <Button size="sm" @click="select(null)"><Plus /> Nouveau</Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="isNew"
                @click="remove"
            >
                <Trash2 /> Supprimer
            </Button>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-4">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium"
                                >Période d’imputation</span
                            >
                            <input
                                v-model="draft.period"
                                type="month"
                                class="h-9 rounded-md border bg-background px-2"
                            />
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">N° pièce</span>
                            <input
                                v-model="draft.number"
                                class="h-9 rounded-md border bg-background px-2"
                            />
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">Intitulé</span>
                            <input
                                v-model="draft.title"
                                class="h-9 rounded-md border bg-background px-2"
                            />
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">Client / Projet</span>
                            <select
                                v-model="draft.project"
                                class="h-9 rounded-md border bg-background px-2"
                                @change="
                                    draft.product = '';
                                    draft.component = '';
                                "
                            >
                                <option value="">—</option>
                                <option
                                    v-for="t in projectTrees"
                                    :key="t.project"
                                    :value="t.project"
                                >
                                    {{ t.project }}
                                </option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium"
                                >Produit / Lot / Section</span
                            >
                            <select
                                v-model="draft.product"
                                :disabled="!tree"
                                class="h-9 rounded-md border bg-background px-2"
                                @change="draft.component = ''"
                            >
                                <option value="">—</option>
                                <option
                                    v-for="p in tree?.products ?? []"
                                    :key="p.name"
                                    :value="p.name"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">Composante</span>
                            <select
                                v-model="draft.component"
                                :disabled="!components.length"
                                class="h-9 rounded-md border bg-background px-2"
                            >
                                <option value="">—</option>
                                <option
                                    v-for="c in components"
                                    :key="c"
                                    :value="c"
                                >
                                    {{ c }}
                                </option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm sm:col-span-2">
                            <span class="font-medium">Observation</span>
                            <input
                                v-model="draft.observation"
                                class="h-9 rounded-md border bg-background px-2"
                            />
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">Justificatif</span>
                            <input
                                v-model="draft.proof"
                                placeholder="nom-du-fichier.pdf"
                                class="h-9 rounded-md border bg-background px-2"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="overflow-x-auto rounded-xl border bg-card shadow-xs"
                >
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead
                            class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2">Désignation</th>
                                <th class="w-24 px-3 py-2">Quantité</th>
                                <th class="w-32 px-3 py-2">Prix unitaire</th>
                                <th class="w-32 px-3 py-2 text-right">
                                    Montant
                                </th>
                                <th class="w-56 px-3 py-2">
                                    Rubrique budgétaire
                                </th>
                                <th class="w-10" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(l, i) in draft.lines"
                                :key="i"
                                class="border-b last:border-0"
                                :class="i === activeLine ? 'bg-accent/40' : ''"
                                @focusin="activeLine = i"
                            >
                                <td class="px-3 py-1.5">
                                    <input
                                        v-model="l.designation"
                                        class="h-8 w-full rounded-md border bg-background px-2"
                                        :aria-label="`Désignation ligne ${i + 1}`"
                                    />
                                </td>
                                <td class="px-3 py-1.5">
                                    <input
                                        v-model.number="l.quantity"
                                        type="number"
                                        min="0"
                                        class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                        :aria-label="`Quantité ligne ${i + 1}`"
                                    />
                                </td>
                                <td class="px-3 py-1.5">
                                    <input
                                        v-model.number="l.unitPrice"
                                        type="number"
                                        min="0"
                                        class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                        :aria-label="`Prix unitaire ligne ${i + 1}`"
                                    />
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums">
                                    {{ fcfa(lineAmount(l)) }}
                                </td>
                                <td class="px-3 py-1.5">
                                    <select
                                        v-model="l.rubrique"
                                        class="h-8 w-full rounded-md border bg-background px-2"
                                        :aria-label="`Rubrique ligne ${i + 1}`"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="r in rubriques"
                                            :key="r.code"
                                            :value="r.code"
                                        >
                                            {{ r.code }} – {{ r.label }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-1">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-muted-foreground hover:text-destructive"
                                        :aria-label="`Supprimer la ligne ${i + 1}`"
                                        @click="removeLine(i)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-medium">
                                <td colspan="3" class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="text-sm text-brand underline-offset-2 hover:underline"
                                        @click="addLine"
                                    >
                                        + Ajouter une ligne
                                    </button>
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    data-test="piece-total"
                                >
                                    {{ fcfa(total) }}
                                </td>
                                <td
                                    colspan="2"
                                    class="px-3 py-2 text-xs text-muted-foreground"
                                >
                                    Total de la pièce
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <div class="flex flex-wrap items-center gap-2">
                    <Button @click="validate">Valider</Button>
                    <Button variant="outline" :disabled="isNew" @click="remove"
                        >Supprimer</Button
                    >
                    <Button
                        v-if="canAdvance && nextStep"
                        variant="secondary"
                        @click="advance"
                    >
                        {{ nextStep.button }}
                    </Button>
                    <Button
                        variant="outline"
                        @click="toast.info('Impression (maquette).')"
                        ><Printer /> Imprimer</Button
                    >
                    <StatusBadge
                        v-if="!isNew"
                        :label="statusLabel[draft.status]"
                        :tone="statusTone[draft.status]"
                    />
                    <p
                        v-if="nextStep && !canAdvance"
                        class="text-xs text-muted-foreground"
                    >
                        Prochaine étape : {{ nextStep.label }} (réservée au
                        poste « {{ nextStep.title }} »).
                    </p>
                </div>

                <section
                    class="overflow-x-auto rounded-xl border bg-card shadow-xs"
                >
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead
                            class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2">ID pièce</th>
                                <th class="px-3 py-2">Date de saisie</th>
                                <th class="px-3 py-2">Demandeur</th>
                                <th class="px-3 py-2">N° B.C.</th>
                                <th class="px-3 py-2">Observation</th>
                                <th class="px-3 py-2">Justificatif</th>
                                <th class="px-3 py-2">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in visible"
                                :key="p.id"
                                class="cursor-pointer border-b last:border-0 hover:bg-accent/50"
                                :class="p.id === selectedId ? 'bg-accent' : ''"
                                tabindex="0"
                                @click="select(p)"
                                @keydown.enter="select(p)"
                            >
                                <td class="px-3 py-2 font-medium">
                                    {{ p.number }}
                                </td>
                                <td class="px-3 py-2">{{ dateFr(p.date) }}</td>
                                <td class="px-3 py-2">{{ p.requester }}</td>
                                <td class="px-3 py-2">
                                    {{ p.bcNumber || '—' }}
                                </td>
                                <td class="max-w-56 truncate px-3 py-2">
                                    {{ p.observation || '—' }}
                                </td>
                                <td class="px-3 py-2">{{ p.proof || '—' }}</td>
                                <td class="px-3 py-2">
                                    <StatusBadge
                                        :label="statusLabel[p.status]"
                                        :tone="statusTone[p.status]"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!visible.length">
                                <td
                                    colspan="7"
                                    class="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Aucune pièce.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="space-y-4">
                <section
                    class="rounded-xl border bg-card p-4 shadow-xs"
                    aria-live="polite"
                >
                    <h2 class="font-display text-base font-semibold">
                        Budget de la rubrique
                    </h2>
                    <template v-if="rubrique && budget">
                        <p class="text-sm text-muted-foreground">
                            {{ rubrique.code }} – {{ rubrique.label }}
                        </p>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt>Montant budget</dt>
                                <dd class="tabular-nums">
                                    {{ fcfa(budget.budget) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>Consommé + en cours</dt>
                                <dd class="tabular-nums">
                                    {{ fcfa(budget.used) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-2 font-medium"
                                :class="
                                    budget.remaining < 0
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                <dt>Budget restant</dt>
                                <dd class="tabular-nums">
                                    {{ fcfa(budget.remaining) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>Taux de consommation</dt>
                                <dd class="tabular-nums">
                                    {{ percent(budget.rate, 1) }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full"
                                :class="
                                    budget.rate > 100
                                        ? 'bg-destructive'
                                        : budget.rate > 80
                                          ? 'bg-warning'
                                          : 'bg-brand'
                                "
                                :style="{
                                    width: Math.min(100, budget.rate) + '%',
                                }"
                            />
                        </div>
                        <p
                            v-if="budget.remaining < 0"
                            class="mt-3 rounded-md bg-destructive/10 p-2 text-sm font-medium text-destructive"
                            role="alert"
                        >
                            Dépassement de la rubrique de
                            {{ fcfa(-budget.remaining) }}.
                        </p>
                    </template>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Choisissez une rubrique budgétaire sur une ligne.
                    </p>
                </section>

                <section
                    class="rounded-xl border bg-card p-4 text-sm shadow-xs"
                >
                    <h2 class="font-display text-base font-semibold">
                        Pour saisir une pièce
                    </h2>
                    <ol
                        class="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground"
                    >
                        <li>Choisir la période d’imputation.</li>
                        <li>Saisir le n° de pièce et valider.</li>
                        <li>Saisir les lignes de la pièce et valider.</li>
                    </ol>
                </section>
            </aside>
        </div>
    </div>
</template>
