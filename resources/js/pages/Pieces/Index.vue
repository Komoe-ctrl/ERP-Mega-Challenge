<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import PieceForm from '@/components/erp/PieceForm.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import { pieceTone } from '@/lib/pieces';
import type {
    PieceKindInfo,
    PieceRow,
    ProjectTree,
    Rubrique,
} from '@/types/pieces';

/**
 * Liste des pièces d'un type : saisie (avec formulaire), validation (pièces qui attendent mon étape)
 * ou remboursements (notes de frais approuvées). Le formulaire et le détail sont ceux de PieceForm.
 */
const props = defineProps<{
    mode: 'saisie' | 'validation' | 'remboursements';
    kind: PieceKindInfo;
    pieces: PieceRow[];
    waiting: { step: string; count: number }[];
    steps: { position: number; label: string; button: string }[];
    rubriques: Rubrique[];
    projects: ProjectTree[];
    can: { create: boolean };
}>();

const plurals: Record<string, string> = {
    note_frais: 'notes de frais',
    recette: 'recettes',
    caisse: 'pièces de caisse',
};

const titles = {
    saisie: props.kind.label,
    validation: `Validation des ${plurals[props.kind.value] ?? props.kind.label.toLowerCase()}`,
    remboursements: 'Facture (remboursement des notes de frais)',
} as const;

const base = computed(() => `/pieces/${props.kind.value}`);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notes de frais', href: '/pieces/note_frais' }],
    },
});

const search = ref('');
const showForm = ref(false);
const shown = computed(() =>
    props.pieces.filter((p) =>
        `${p.number} ${p.title} ${p.requester}`
            .toLowerCase()
            .includes(search.value.toLowerCase()),
    ),
);

function reimburse(p: PieceRow) {
    router.post(
        `${base.value}/${p.id}/rembourser`,
        {},
        { preserveScroll: true },
    );
}

function advance(p: PieceRow) {
    router.post(`${base.value}/${p.id}/valider`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="titles[mode]" />

    <div class="space-y-4 p-4" data-test="pieces-index">
        <div>
            <h1
                class="font-display text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ titles[mode] }}
            </h1>
            <ul class="mt-1 space-y-0.5 text-sm text-muted-foreground">
                <li v-for="w in waiting" :key="w.step">
                    {{ w.count }} {{ kind.label.toLowerCase() }} en attente :
                    {{ w.step.toLowerCase() }}
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <label class="relative">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Recherche"
                    aria-label="Recherche"
                    class="h-8 rounded-md border bg-background pr-2 pl-8 text-sm"
                />
            </label>
            <Button v-if="can.create" size="sm" @click="showForm = !showForm"
                ><Plus /> Nouveau</Button
            >
        </div>

        <section
            v-if="showForm && can.create"
            class="rounded-xl border bg-muted/20 p-3"
        >
            <PieceForm
                :kind="kind"
                :projects="projects"
                :rubriques="rubriques"
                @saved="showForm = false"
            />
        </section>

        <section class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[44rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">ID pièce</th>
                        <th class="px-3 py-2">Période</th>
                        <th class="px-3 py-2">Demandeur</th>
                        <th class="px-3 py-2">N° B.C.</th>
                        <th class="px-3 py-2">Observation</th>
                        <th class="px-3 py-2">Justificatif</th>
                        <th class="px-3 py-2 text-right">Montant</th>
                        <th class="px-3 py-2">État</th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in shown"
                        :key="p.id"
                        class="border-b last:border-0 hover:bg-accent/50"
                    >
                        <td class="px-3 py-2 font-medium">
                            <Link
                                :href="`${base}/${p.id}`"
                                class="hover:underline"
                                >{{ p.number }}</Link
                            >
                            <p
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ p.title }}
                            </p>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ dateFr(`${p.period}-01`).slice(3) }}
                        </td>
                        <td class="px-3 py-2">{{ p.requester }}</td>
                        <td class="px-3 py-2">{{ p.bc_number || '—' }}</td>
                        <td class="max-w-56 truncate px-3 py-2">
                            {{ p.observation || '—' }}
                        </td>
                        <td class="px-3 py-2">{{ p.proof || '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ fcfa(p.total) }}
                        </td>
                        <td class="px-3 py-2">
                            <StatusBadge
                                :label="p.status_label"
                                :tone="pieceTone[p.status]"
                            />
                            <p
                                v-if="p.waiting"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ p.waiting }}
                            </p>
                            <p
                                v-if="p.invoice_number"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ p.invoice_number }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <Button
                                v-if="mode === 'validation' && p.can.decide"
                                size="sm"
                                @click="advance(p)"
                                >{{ p.can.button ?? 'Valider' }}</Button
                            >
                            <Button
                                v-if="
                                    mode === 'remboursements' && p.can.reimburse
                                "
                                size="sm"
                                @click="reimburse(p)"
                                >Rembourser</Button
                            >
                        </td>
                    </tr>
                    <tr v-if="!shown.length">
                        <td
                            colspan="9"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Aucune pièce.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
