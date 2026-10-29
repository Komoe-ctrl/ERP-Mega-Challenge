<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateFr, fcfa } from '@/lib/format';
import { articles, categories, units } from '@/mocks/achats';

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
const readonly = computed(
    () => !page.props.auth.roles.includes('service_achats_btp'),
);
const article = computed(() => articles.find((a) => a.id === props.id));
const title = computed(() =>
    article.value ? article.value.label : 'Nouvel article',
);

const form = reactive({
    reference: article.value?.reference ?? '',
    label: article.value?.label ?? '',
    category: article.value?.category ?? categories[0],
    unit: article.value?.unit ?? units[0],
    price: article.value?.price ?? 0,
});

const disableOpen = ref(false);

function onDisable() {
    toast.success('Article désactivé (maquette).');
    router.visit('/achats/referentiel');
}

function submit() {
    toast.success(
        article.value && form.price !== article.value.price
            ? 'Article enregistré : le changement de prix est historisé (maquette).'
            : 'Article enregistré (maquette).',
    );
    router.visit('/achats/referentiel');
}

const select =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40 disabled:opacity-60';
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

        <form
            class="space-y-4 rounded-xl border bg-card p-4 shadow-xs sm:p-6"
            @submit.prevent="submit"
        >
            <fieldset :disabled="readonly" class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="reference">Référence</Label>
                    <Input id="reference" v-model="form.reference" required />
                </div>
                <div class="grid gap-2">
                    <Label for="label">Libellé</Label>
                    <Input id="label" v-model="form.label" required />
                </div>
                <div class="grid gap-2">
                    <Label for="category">Catégorie</Label>
                    <select
                        id="category"
                        v-model="form.category"
                        :class="select"
                    >
                        <option v-for="c in categories" :key="c">
                            {{ c }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="unit">Unité</Label>
                    <select id="unit" v-model="form.unit" :class="select">
                        <option v-for="u in units" :key="u">{{ u }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="price">Prix unitaire (FCFA)</Label>
                    <Input
                        id="price"
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="1"
                        inputmode="numeric"
                        required
                    />
                </div>
            </fieldset>

            <p
                v-if="readonly"
                class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
            >
                Consultation seule : seul le Service Achats BTP peut modifier un
                article ou un prix.
            </p>

            <div class="flex flex-wrap gap-2">
                <Button v-if="!readonly" type="submit">Enregistrer</Button>
                <Button
                    v-if="!readonly && article?.active"
                    type="button"
                    variant="outline"
                    class="text-destructive"
                    @click="disableOpen = true"
                >
                    Désactiver
                </Button>
                <Button variant="ghost" as-child
                    ><Link href="/achats/referentiel">Retour</Link></Button
                >
            </div>
            <p v-if="article" class="text-xs text-muted-foreground">
                Un article déjà utilisé ne peut être que désactivé, jamais
                supprimé (RG8.14).
            </p>
        </form>

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
