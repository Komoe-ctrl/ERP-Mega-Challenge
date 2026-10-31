<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { fcfa, percent } from '@/lib/format';
import type {
    BudgetPanelData,
    PieceDetail,
    PieceKindInfo,
    ProjectTree,
    Rubrique,
} from '@/types/pieces';

/**
 * Saisie commune d'une pièce : période d'imputation, n° de pièce, intitulé, imputation
 * analytique (projet → produit / lot / section → composante), lignes avec rubrique budgétaire,
 * panneau budget de la rubrique (calculé par le serveur) et justificatif.
 */
const props = defineProps<{
    kind: PieceKindInfo;
    projects: ProjectTree[];
    rubriques: Rubrique[];
    piece?: PieceDetail | null;
    readonly?: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

type Line = {
    designation: string;
    quantity: number;
    unit_price: number;
    rubrique_id: number | null;
};

const blankLine = (rubrique: number | null = null): Line => ({
    designation: '',
    quantity: 1,
    unit_price: 0,
    rubrique_id: rubrique,
});

const form = useForm({
    period: props.piece?.period ?? new Date().toISOString().slice(0, 7),
    number: props.piece?.number ?? '',
    title: props.piece?.title ?? '',
    project_id: props.piece?.project_id ?? (null as number | null),
    project_lot_id: props.piece?.project_lot_id ?? (null as number | null),
    lot_component_id: props.piece?.lot_component_id ?? (null as number | null),
    bc_number: props.piece?.bc_number ?? '',
    observation: props.piece?.observation ?? '',
    proof: null as File | null,
    lines: (props.piece?.lines.length
        ? props.piece.lines.map((l) => ({
              designation: l.designation,
              quantity: Number(l.quantity),
              unit_price: l.unit_price,
              rubrique_id: l.rubrique_id,
          }))
        : [blankLine()]) as Line[],
});

const lots = computed(
    () => props.projects.find((p) => p.id === form.project_id)?.lots ?? [],
);
const components = computed(
    () =>
        lots.value.find((l) => l.id === form.project_lot_id)?.components ?? [],
);

const amount = (l: Line): number =>
    Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0));
const total = computed(() => form.lines.reduce((s, l) => s + amount(l), 0));

// --- Panneau budget de la rubrique de la ligne active -------------------------
const activeLine = ref(0);
const panel = ref<BudgetPanelData | null>(null);
const panelError = ref<string | null>(null);

async function loadPanel() {
    const line = form.lines[activeLine.value];

    if (!line?.rubrique_id || !form.period) {
        panel.value = null;

        return;
    }

    const query = new URLSearchParams({
        rubrique_id: String(line.rubrique_id),
        period: form.period,
    });

    if (props.piece) {
        query.set('piece', String(props.piece.id));
    }

    try {
        const response = await fetch(
            `/pieces/${props.kind.value}/budget?${query}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            panel.value = null;
            panelError.value =
                response.status === 422
                    ? 'Aucun exercice ne couvre cette période.'
                    : 'Budget indisponible.';

            return;
        }

        panel.value = (await response.json()) as BudgetPanelData;
        panelError.value = null;
    } catch {
        panelError.value = 'Budget indisponible.';
    }
}

watch(
    () => [
        form.period,
        form.lines[activeLine.value]?.rubrique_id,
        activeLine.value,
    ],
    loadPanel,
    { immediate: true },
);

// Le serveur donne le budget et l'engagé hors de cette saisie ; on y ajoute les lignes en cours de saisie.
const draftOnRubrique = computed(() => {
    const id = form.lines[activeLine.value]?.rubrique_id;

    return form.lines
        .filter((l) => l.rubrique_id === id)
        .reduce((s, l) => s + amount(l), 0);
});
const view = computed(() => {
    const p = panel.value;

    if (!p) {
        return null;
    }

    const used = p.used + draftOnRubrique.value;

    return {
        ...p,
        used,
        remaining: p.budget - used,
        rate: p.budget > 0 ? (used / p.budget) * 100 : 0,
        overrun: used > p.budget,
    };
});

function addLine() {
    form.lines.push(
        blankLine(form.lines[activeLine.value]?.rubrique_id ?? null),
    );
    activeLine.value = form.lines.length - 1;
}

function removeLine(i: number) {
    form.lines.splice(i, 1);

    if (!form.lines.length) {
        form.lines.push(blankLine());
    }

    activeLine.value = Math.min(activeLine.value, form.lines.length - 1);
}

function submit() {
    const url = props.piece
        ? `/pieces/${props.kind.value}/${props.piece.id}`
        : `/pieces/${props.kind.value}`;

    form.transform((data) => ({
        ...data,
        ...(props.piece ? { _method: 'put' } : {}),
        project_id: data.project_id ?? '',
        project_lot_id: data.project_lot_id ?? '',
        lot_component_id: data.lot_component_id ?? '',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}

function onProof(event: Event) {
    form.proof = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>

<template>
    <form
        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]"
        data-test="piece-form"
        @submit.prevent="submit"
    >
        <div class="space-y-4">
            <fieldset
                :disabled="readonly"
                class="grid gap-3 rounded-xl border bg-card p-4 shadow-xs sm:grid-cols-3"
            >
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Période d’imputation</span>
                    <input
                        v-model="form.period"
                        type="month"
                        class="h-9 rounded-md border bg-background px-2"
                    />
                    <InputError :message="form.errors.period" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">N° pièce</span>
                    <input
                        v-model="form.number"
                        placeholder="automatique si vide"
                        class="h-9 rounded-md border bg-background px-2"
                    />
                    <InputError :message="form.errors.number" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Intitulé</span>
                    <input
                        v-model="form.title"
                        class="h-9 rounded-md border bg-background px-2"
                    />
                    <InputError :message="form.errors.title" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Client / Projet</span>
                    <select
                        v-model="form.project_id"
                        class="h-9 rounded-md border bg-background px-2"
                        @change="
                            form.project_lot_id = null;
                            form.lot_component_id = null;
                        "
                    >
                        <option :value="null">—</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Produit / Lot / Section</span>
                    <select
                        v-model="form.project_lot_id"
                        :disabled="!lots.length"
                        class="h-9 rounded-md border bg-background px-2"
                        @change="form.lot_component_id = null"
                    >
                        <option :value="null">—</option>
                        <option v-for="l in lots" :key="l.id" :value="l.id">
                            {{ l.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.project_lot_id" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Composante</span>
                    <select
                        v-model="form.lot_component_id"
                        :disabled="!components.length"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="c in components"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.lot_component_id" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">N° B.C.</span>
                    <input
                        v-model="form.bc_number"
                        class="h-9 rounded-md border bg-background px-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Observation</span>
                    <input
                        v-model="form.observation"
                        class="h-9 rounded-md border bg-background px-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Justificatif</span>
                    <input type="file" class="text-sm" @change="onProof" />
                    <span
                        v-if="piece?.proof"
                        class="text-xs text-muted-foreground"
                        >Actuel : {{ piece.proof }}</span
                    >
                    <InputError :message="form.errors.proof" />
                </label>
            </fieldset>

            <section
                class="overflow-x-auto rounded-xl border bg-card shadow-xs"
            >
                <table class="w-full min-w-[44rem] text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2">Désignation</th>
                            <th class="w-24 px-3 py-2">Quantité</th>
                            <th class="w-32 px-3 py-2">Prix unitaire</th>
                            <th class="w-32 px-3 py-2 text-right">Montant</th>
                            <th class="w-56 px-3 py-2">Rubrique budgétaire</th>
                            <th class="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(l, i) in form.lines"
                            :key="i"
                            class="border-b align-top last:border-0"
                            :class="i === activeLine ? 'bg-accent/40' : ''"
                            @focusin="activeLine = i"
                        >
                            <td class="px-3 py-1.5">
                                <input
                                    v-model="l.designation"
                                    :disabled="readonly"
                                    class="h-8 w-full rounded-md border bg-background px-2"
                                    :aria-label="`Désignation ligne ${i + 1}`"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `lines.${i}.designation` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </td>
                            <td class="px-3 py-1.5">
                                <input
                                    v-model.number="l.quantity"
                                    :disabled="readonly"
                                    type="number"
                                    min="0"
                                    step="any"
                                    class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                    :aria-label="`Quantité ligne ${i + 1}`"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `lines.${i}.quantity` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </td>
                            <td class="px-3 py-1.5">
                                <input
                                    v-model.number="l.unit_price"
                                    :disabled="readonly"
                                    type="number"
                                    min="0"
                                    class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                    :aria-label="`Prix unitaire ligne ${i + 1}`"
                                />
                            </td>
                            <td
                                class="px-3 py-1.5 text-right tabular-nums"
                                data-test="line-amount"
                            >
                                {{ fcfa(amount(l)) }}
                            </td>
                            <td class="px-3 py-1.5">
                                <select
                                    v-model="l.rubrique_id"
                                    :disabled="readonly"
                                    class="h-8 w-full rounded-md border bg-background px-2"
                                    :aria-label="`Rubrique ligne ${i + 1}`"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="r in rubriques"
                                        :key="r.id"
                                        :value="r.id"
                                    >
                                        {{ r.code }} – {{ r.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        form.errors[
                                            `lines.${i}.rubrique_id` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </td>
                            <td class="px-1">
                                <button
                                    v-if="!readonly"
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
                                    v-if="!readonly"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-brand underline-offset-2 hover:underline"
                                    @click="addLine"
                                >
                                    <Plus class="size-3.5" /> Ajouter une ligne
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
            <InputError :message="form.errors.lines" />

            <div v-if="!readonly" class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    piece ? 'Enregistrer' : 'Valider la saisie'
                }}</Button>
            </div>
        </div>

        <aside class="space-y-4">
            <section
                class="rounded-xl border bg-card p-4 shadow-xs"
                aria-live="polite"
                data-test="budget-panel"
            >
                <h2 class="font-display text-base font-semibold">
                    Budget de la rubrique
                </h2>
                <template v-if="view">
                    <p class="text-sm text-muted-foreground">
                        {{ view.rubrique.code }} – {{ view.rubrique.label }}
                    </p>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt>Montant budget</dt>
                            <dd class="tabular-nums">
                                {{ fcfa(view.budget) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt>Consommé + en cours</dt>
                            <dd class="tabular-nums">{{ fcfa(view.used) }}</dd>
                        </div>
                        <div
                            class="flex justify-between gap-2 font-medium"
                            :class="
                                view.remaining < 0 ? 'text-destructive' : ''
                            "
                        >
                            <dt>Budget restant</dt>
                            <dd class="tabular-nums">
                                {{ fcfa(view.remaining) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt>Taux de consommation</dt>
                            <dd class="tabular-nums">
                                {{ percent(view.rate, 1) }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full"
                            :class="
                                view.rate > 100
                                    ? 'bg-destructive'
                                    : view.rate > 80
                                      ? 'bg-warning'
                                      : 'bg-brand'
                            "
                            :style="{ width: Math.min(100, view.rate) + '%' }"
                        />
                    </div>
                    <p
                        v-if="view.overrun"
                        class="mt-3 rounded-md bg-destructive/10 p-2 text-sm font-medium text-destructive"
                        role="alert"
                    >
                        Dépassement de la rubrique de
                        {{ fcfa(-view.remaining) }}.
                    </p>
                </template>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    {{
                        panelError ??
                        'Choisissez une rubrique budgétaire sur une ligne.'
                    }}
                </p>
            </section>

            <section class="rounded-xl border bg-card p-4 text-sm shadow-xs">
                <h2 class="font-display text-base font-semibold">
                    Pour saisir une pièce
                </h2>
                <ol
                    class="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground"
                >
                    <li>Choisir la période d’imputation.</li>
                    <li>Saisir le n° de pièce et l’intitulé.</li>
                    <li>Saisir les lignes de la pièce et valider.</li>
                </ol>
            </section>
        </aside>
    </form>
</template>
