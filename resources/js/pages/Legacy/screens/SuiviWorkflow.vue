<script setup lang="ts">
import { computed, ref } from 'vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { defaultWorkflow, initialPieces, pieceTotal } from '@/mocks/legacy';
import { fcfa } from '@/lib/format';
import type { PieceKind } from '@/types/legacy';

const props = defineProps<{ kind: PieceKind }>();

const pieces = computed(() =>
    initialPieces.filter((p) => p.kind === props.kind),
);
const selectedId = ref<number>(pieces.value[0]?.id ?? 0);
const piece = computed(() =>
    pieces.value.find((p) => p.id === selectedId.value),
);

// Étape atteinte = nombre d'événements d'historique (saisie, validation, approbation).
const reached = computed(() =>
    piece.value?.status === 'approuvee'
        ? 3
        : piece.value?.status === 'validee'
          ? 2
          : 1,
);
</script>

<template>
    <div class="space-y-4 p-4">
        <PageHeader
            title="Suivi « workflow »"
            description="Qui a fait quoi, quand, avec quel commentaire, pour chaque pièce."
        />
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

        <template v-if="piece">
            <ol class="flex flex-wrap gap-2" aria-label="Circuit de validation">
                <li
                    v-for="s in defaultWorkflow"
                    :key="s.id"
                    class="rounded-lg border px-3 py-2 text-sm"
                    :class="
                        s.id <= reached
                            ? 'border-brand bg-brand/10 font-medium'
                            : 'text-muted-foreground'
                    "
                >
                    {{ s.id }}. {{ s.label }}
                </li>
            </ol>
            <p class="text-sm text-muted-foreground">
                Montant de la pièce :
                <span class="font-medium text-foreground tabular-nums">{{
                    fcfa(pieceTotal(piece.lines))
                }}</span>
            </p>
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
                            v-for="(e, i) in piece.history"
                            :key="i"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 tabular-nums">{{ e.at }}</td>
                            <td class="px-3 py-2">{{ e.by }}</td>
                            <td class="px-3 py-2">{{ e.action }}</td>
                            <td class="px-3 py-2">{{ e.comment || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
