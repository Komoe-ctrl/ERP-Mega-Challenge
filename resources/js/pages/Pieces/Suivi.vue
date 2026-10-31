<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { fcfa } from '@/lib/format';
import { eventLabel, pieceTone } from '@/lib/pieces';
import type {
    PieceEvent,
    PieceKindInfo,
    PieceStatusValue,
} from '@/types/pieces';

/**
 * Suivi « workflow » : pour chaque pièce, le circuit parcouru — qui a fait quoi, quand,
 * avec quel commentaire — et l'étape atteinte.
 */
const props = defineProps<{
    kind: PieceKindInfo;
    steps: { position: number; label: string }[];
    pieces: {
        id: number;
        number: string;
        title: string;
        status: PieceStatusValue;
        status_label: string;
        current_step: number | null;
        total: number;
        events: PieceEvent[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notes de frais', href: '/pieces/note_frais' }],
    },
});

const selectedId = ref<number | null>(props.pieces[0]?.id ?? null);
const piece = computed(() =>
    props.pieces.find((p) => p.id === selectedId.value),
);

// Étape atteinte : franchie si approuvée, ou antérieure à l'étape courante.
function reached(position: number): boolean {
    const p = piece.value;

    if (!p) {
        return false;
    }

    return p.status === 'approuvee' || (p.current_step ?? 0) > position;
}
</script>

<template>
    <Head title="Suivi « workflow »" />

    <div class="space-y-4 p-4" data-test="pieces-suivi">
        <div>
            <h1
                class="font-display text-xl font-semibold tracking-tight sm:text-2xl"
            >
                Suivi « workflow »
            </h1>
            <p class="mt-0.5 text-sm text-muted-foreground">
                {{ kind.label }} : qui a fait quoi, quand, avec quel
                commentaire.
            </p>
        </div>

        <label class="grid max-w-md gap-1 text-sm">
            <span class="font-medium">Pièce</span>
            <select
                v-model.number="selectedId"
                class="h-9 rounded-md border bg-background px-2"
            >
                <option v-for="p in pieces" :key="p.id" :value="p.id">
                    {{ p.number }} — {{ p.title }}
                </option>
            </select>
        </label>
        <p v-if="!pieces.length" class="text-sm text-muted-foreground">
            Aucune pièce.
        </p>

        <template v-if="piece">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge
                    :label="piece.status_label"
                    :tone="pieceTone[piece.status]"
                />
                <span class="text-sm text-muted-foreground tabular-nums">{{
                    fcfa(piece.total)
                }}</span>
            </div>
            <ol
                v-if="steps.length"
                class="flex flex-wrap gap-2"
                aria-label="Circuit de validation"
            >
                <li
                    class="rounded-lg border border-brand bg-brand/10 px-3 py-2 text-sm font-medium"
                >
                    Saisie
                </li>
                <li
                    v-for="s in steps"
                    :key="s.position"
                    class="rounded-lg border px-3 py-2 text-sm"
                    :class="
                        reached(s.position)
                            ? 'border-brand bg-brand/10 font-medium'
                            : piece.current_step === s.position
                              ? 'border-warning bg-warning/10 font-medium'
                              : 'text-muted-foreground'
                    "
                >
                    {{ s.label }}
                </li>
            </ol>
            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2">Date et heure</th>
                            <th class="px-3 py-2">Par</th>
                            <th class="px-3 py-2">Étape</th>
                            <th class="px-3 py-2">Commentaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(e, i) in piece.events"
                            :key="i"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 tabular-nums">{{ e.at }}</td>
                            <td class="px-3 py-2">{{ e.by }}</td>
                            <td class="px-3 py-2">
                                {{ eventLabel[e.action] ?? e.action
                                }}<span v-if="e.step"> — {{ e.step }}</span>
                            </td>
                            <td class="px-3 py-2">{{ e.comment || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
