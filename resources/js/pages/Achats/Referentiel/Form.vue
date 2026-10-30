<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ArticleController from '@/actions/App/Http/Controllers/Achats/ArticleController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import type { Article } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = withDefaults(
    defineProps<{
        article: Article | null;
        categories: string[];
        units: string[];
        canWrite?: boolean;
    }>(),
    { canWrite: true },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Référentiel des prix', href: '/achats/referentiel' },
        ],
    },
});

// RG8.13 : seul le Service Achats BTP crée, modifie ou désactive un article (droit décidé par le serveur).
const readonly = computed(() => !props.canWrite);
const title = computed(() =>
    props.article ? props.article.label : 'Nouvel article',
);
const disableOpen = ref(false);

const fields: FieldDef[] = [
    {
        name: 'reference',
        label: 'Référence',
        required: true,
        pattern: /^[A-Za-z]{3}-\d{3}$/,
        patternMessage:
            'Format attendu : trois lettres, un tiret, trois chiffres (ex. CIM-003).',
    },
    { name: 'label', label: 'Libellé', required: true, minLength: 3 },
    {
        name: 'category',
        label: 'Catégorie',
        type: 'select',
        required: true,
        options: props.categories.map((c) => ({ value: c, label: c })),
    },
    {
        name: 'unit',
        label: 'Unité',
        type: 'select',
        required: true,
        options: props.units.map((u) => ({ value: u, label: u })),
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
            props.article &&
            Number(values.price) !== props.article.price &&
            !String(value ?? '').trim()
                ? 'Indiquez le motif du changement de prix.'
                : null,
    },
];

const initial: FieldValues = {
    reference: props.article?.reference ?? '',
    label: props.article?.label ?? '',
    category: props.article?.category ?? '',
    unit: props.article?.unit ?? '',
    price: props.article?.price ?? null,
    reason: '',
};

// Le serveur refait toute la validation ; ses messages reviennent en notification.
function submit(values: FieldValues) {
    const options = {
        onError: (errors: Record<string, string>) =>
            toast.error(Object.values(errors)[0]),
    };

    if (props.article) {
        router.put(
            ArticleController.update.url(props.article.id),
            values as never,
            options,
        );
    } else {
        router.post(ArticleController.store.url(), values as never, options);
    }
}

function onDisable(reason: string) {
    if (props.article) {
        router.patch(
            ArticleController.deactivate.url(props.article.id),
            { reason },
            { onError: (e) => toast.error(Object.values(e)[0]) },
        );
    }
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
                @cancel="router.visit(ArticleController.index.url())"
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
                ><Link :href="ArticleController.index.url()"
                    >Retour</Link
                ></Button
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
                            v-for="(h, i) in [
                                ...(article.history ?? []),
                            ].reverse()"
                            :key="i"
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
