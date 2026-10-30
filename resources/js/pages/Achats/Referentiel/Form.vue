<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { articles, categories, units } from '@/mocks/achats';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id?: number }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Référentiel des prix', href: '/achats/referentiel' },
        ],
    },
});

const page = usePage();
// RG8.13 : seul le Service Achats BTP crée, modifie ou désactive un article.
const readonly = computed(
    () => !page.props.auth.roles.includes('service_achats_btp'),
);
const article = computed(() => articles.find((a) => a.id === props.id));
const title = computed(() =>
    article.value ? article.value.label : 'Nouvel article',
);
const disableOpen = ref(false);

const fields: FieldDef[] = [
    {
        name: 'reference',
        label: 'Référence',
        required: true,
        pattern: /^[A-Z]{3}-\d{3}$/,
        patternMessage:
            'Format attendu : trois lettres, un tiret, trois chiffres (ex. CIM-003).',
        validate: (value) =>
            articles.some(
                (a) => a.reference === value && a.id !== article.value?.id,
            )
                ? 'Cette référence existe déjà.'
                : null,
    },
    { name: 'label', label: 'Libellé', required: true, minLength: 3 },
    {
        name: 'category',
        label: 'Catégorie',
        type: 'select',
        required: true,
        options: categories.map((c) => ({ value: c, label: c })),
    },
    {
        name: 'unit',
        label: 'Unité',
        type: 'select',
        required: true,
        options: units.map((u) => ({ value: u, label: u })),
    },
    {
        name: 'price',
        label: 'Prix unitaire',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        hint: 'Tout changement de prix est historisé avec son auteur.',
    },
    {
        name: 'reason',
        label: 'Motif du changement de prix',
        type: 'textarea',
        validate: (value, values) =>
            article.value &&
            Number(values.price) !== article.value.price &&
            !String(value ?? '').trim()
                ? 'Indiquez le motif du changement de prix.'
                : null,
    },
];

const initial: FieldValues = {
    reference: article.value?.reference ?? '',
    label: article.value?.label ?? '',
    category: article.value?.category ?? '',
    unit: article.value?.unit ?? '',
    price: article.value?.price ?? null,
    reason: '',
};

function submit(values: FieldValues) {
    toast.success(
        article.value && Number(values.price) !== article.value.price
            ? 'Article enregistré : le changement de prix est historisé (maquette).'
            : 'Article enregistré (maquette).',
    );
    router.visit('/achats/referentiel');
}

function onDisable() {
    toast.success('Article désactivé (maquette).');
    router.visit('/achats/referentiel');
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4">
        <PageHeader
            :title="title"
            :description="
                article
                    ? article.reference
                    : 'Un article porte une référence, une unité et un prix unitaire en FCFA.'
            "
        />
        <SubNav :links="achatsNav" />

        <div class="space-y-4 rounded-xl border bg-card p-4 shadow-xs sm:p-6">
            <p
                v-if="readonly"
                class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
            >
                Consultation seule : seul le Service Achats BTP peut modifier un
                article ou un prix.
            </p>
            <SchemaForm
                v-if="!readonly"
                :fields="fields"
                :initial="initial"
                @submit="submit"
                @cancel="router.visit('/achats/referentiel')"
            >
                <template #actions>
                    <Button
                        v-if="article?.active"
                        type="button"
                        variant="outline"
                        class="text-destructive"
                        @click="disableOpen = true"
                        >Désactiver</Button
                    >
                </template>
            </SchemaForm>
            <Button v-else variant="ghost" as-child
                ><Link href="/achats/referentiel">Retour</Link></Button
            >
            <p v-if="article" class="text-xs text-muted-foreground">
                Un article déjà utilisé ne peut être que désactivé, jamais
                supprimé (RG8.14).
            </p>
        </div>

        <section v-if="article" class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">Historique des prix</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Date</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Prix
                            </th>
                            <th class="py-2 pr-4 font-medium">Par</th>
                            <th class="py-2 font-medium">Motif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="h in [...article.history].reverse()"
                            :key="h.date"
                            class="border-t"
                        >
                            <td class="py-2 pr-4 whitespace-nowrap">
                                {{ dateFr(h.date) }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ fcfa(h.price) }}
                            </td>
                            <td class="py-2 pr-4">{{ h.by }}</td>
                            <td class="py-2">{{ h.reason }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <ConfirmDialog
            v-model:open="disableOpen"
            title="Désactiver l’article"
            description="Il ne sera plus proposé dans les nouvelles demandes d’achat."
            confirm-label="Désactiver"
            require-reason
            destructive
            @confirm="onDisable"
        />
    </div>
</template>
