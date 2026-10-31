<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Component } from 'vue';
import LegacyTable from '@/components/erp/LegacyTable.vue';
import PieceEntry from '@/components/erp/PieceEntry.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { tableScreens } from '@/lib/legacyScreens';
import type { PieceKind, PieceStatus } from '@/types/legacy';
import Budget from './screens/Budget.vue';
import Tableaux from './screens/Tableaux.vue';

/**
 * Point d'entrée unique des écrans repris de l'application de bureau actuelle.
 * Chaque `slug` correspond à une entrée de menu (voir lib/legacyMenu.ts).
 */
const props = defineProps<{ slug: string }>();

type Piece = { kind: PieceKind; title: string; onlyStatus?: PieceStatus[] };

const pieceScreens: Record<string, Piece> = {
    recette: { kind: 'recette', title: 'Client facture / recette' },
    'validation-recette': {
        kind: 'recette',
        title: 'Validation des recettes',
        onlyStatus: ['saisie', 'validee'],
    },
    'entree-de-caisse': { kind: 'caisse', title: 'Entrée de caisse' },
};

const special: Record<
    string,
    { component: Component; props: Record<string, unknown> }
> = {
    'exercice-budgetaire': { component: Budget, props: { mode: 'exercice' } },
    'elaboration-budgetaire': {
        component: Budget,
        props: { mode: 'elaboration' },
    },
    'reconduire-budget': { component: Budget, props: { mode: 'reconduction' } },
    'tableau-de-bord-mensuel': {
        component: Tableaux,
        props: { mode: 'mensuel' },
    },
    'situation-des-projets': {
        component: Tableaux,
        props: { mode: 'situation' },
    },
    planning: { component: Tableaux, props: { mode: 'planning' } },
    'tableau-de-bord-des-projets': {
        component: Tableaux,
        props: { mode: 'projets' },
    },
};

const piece = computed(() => pieceScreens[props.slug]);
const screen = computed(() => tableScreens[props.slug]);
const other = computed(() => special[props.slug]);
const pageTitle = computed(
    () => piece.value?.title ?? screen.value?.title ?? 'Écran',
);
</script>

<template>
    <Head :title="pageTitle" />

    <PieceEntry
        v-if="piece"
        :key="slug"
        :kind="piece.kind"
        :title="piece.title"
        :only-status="piece.onlyStatus"
    />
    <component
        :is="other.component"
        v-else-if="other"
        :key="slug"
        v-bind="other.props"
    />
    <LegacyTable v-else-if="screen" :key="slug" :screen="screen" />
    <div v-else class="p-4">
        <PageHeader
            title="Écran introuvable"
            description="Cet écran n’existe pas dans la maquette."
        />
    </div>
</template>
