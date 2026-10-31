<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Copy, Handshake, Plus, Printer, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import QuoteController from '@/actions/App/Http/Controllers/Devis/QuoteController';
import ContractController from '@/actions/App/Http/Controllers/Marches/ContractController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { quoteTotals, workTotal } from '@/lib/billing';
import { dateFr, fcfa, number, percent } from '@/lib/format';
import type { QuoteDoc, QuoteStatus } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type QuoteView = QuoteDoc & { refusalReason: string | null };

const props = defineProps<{
    quote: QuoteView;
    contract: { id: number; number: string } | null;
    versions: { id: number; version: number; status: string }[];
    library: {
        id: number;
        label: string;
        unit: string;
        cost: number;
        price: number;
    }[];
    can: {
        update: boolean;
        validate: boolean;
        duplicate: boolean;
        createContract: boolean;
        submit: boolean;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Devis', href: '/devis' }] },
});

const copy = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

// Copie locale modifiable ; elle est resynchronisée à chaque réponse du serveur.
const quote = reactive<QuoteView>(copy(props.quote));

watch(
    () => props.quote,
    (value) => Object.assign(quote, copy(value)),
);

const tab = ref('editeur');
const refuseOpen = ref(false);
const libraryOpen = ref(false);

// L'édition n'est possible qu'en brouillon (droit vérifié côté serveur).
const editable = computed(
    () => quote.status === 'brouillon' && props.can.update,
);
const totals = computed(() => quoteTotals(quote));

// Contrôles de saisie affichés sous les lignes invalides.
const lineIssue = (w: QuoteDoc['works'][number]) =>
    w.label.trim().length < 3
        ? 'Libellé trop court.'
        : w.quantity <= 0
          ? 'La quantité doit être supérieure à 0.'
          : w.unitPrice < 0
            ? 'Le prix ne peut pas être négatif.'
            : '';
const issues = computed(() => quote.works.filter((w) => lineIssue(w)).length);
const discountIssue = computed(() =>
    quote.discountRate < 0 || quote.discountRate > 100
        ? 'La remise doit être comprise entre 0 et 100 %.'
        : '',
);

function addWork() {
    quote.works.push({
        id: -Date.now(),
        label: 'Nouvel ouvrage',
        unit: 'm²',
        quantity: 1,
        unitPrice: 0,
        components: '',
    });
}

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0] ?? 'Saisie invalide.');

/** Enregistre le brouillon puis enchaîne (soumission, ajout d'un ouvrage). */
function save(then?: () => void) {
    if (issues.value || discountIssue.value) {
        toast.error('Corrigez les lignes signalées avant de continuer.');

        return;
    }

    router.put(
        QuoteController.update.url(quote.id),
        {
            discount_rate: quote.discountRate,
            vat_rate: quote.vatRate,
            direct_cost: quote.directCost,
            works: quote.works.map((w) => ({
                label: w.label,
                unit: w.unit,
                quantity: w.quantity,
                unit_price: w.unitPrice,
                components: w.components,
                ouvrage_id: w.ouvrageId ?? null,
            })),
        },
        { preserveScroll: true, onError, onSuccess: then },
    );
}

// RG5.1 : un ouvrage de la bibliothèque a son prix calculé depuis sa composition.
const libraryFields = computed<FieldDef[]>(() => [
    {
        name: 'ouvrage',
        label: 'Ouvrage',
        type: 'select',
        required: true,
        wide: true,
        options: props.library.map((o) => ({
            value: String(o.id),
            label: `${o.label} — ${fcfa(o.price)} / ${o.unit}`,
        })),
    },
    {
        name: 'quantity',
        label: 'Quantité',
        type: 'number',
        required: true,
        min: 0.01,
        step: 0.01,
    },
]);

function addFromLibrary(v: FieldValues) {
    save(() =>
        router.post(
            QuoteController.addOuvrage.url(quote.id),
            { ouvrage_id: Number(v.ouvrage), quantity: Number(v.quantity) },
            { preserveScroll: true, onError },
        ),
    );
}

function submitQuote() {
    save(() =>
        router.post(
            QuoteController.submit.url(quote.id),
            {},
            { preserveScroll: true, onError },
        ),
    );
}

function decide(action: 'validate' | 'version') {
    router.post(
        action === 'validate'
            ? QuoteController.validateQuote.url(quote.id)
            : QuoteController.newVersion.url(quote.id),
        {},
        { onError },
    );
}

function onRefuse(reason: string) {
    router.post(QuoteController.refuse.url(quote.id), { reason }, { onError });
}

function print() {
    window.print();
}

const statusOf = (s: string) => s as QuoteStatus;
</script>

<template>
    <Head :title="quote.number" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="`${quote.number} — version ${quote.version}`"
            :description="`${quote.clientName} · ${quote.projectName}`"
        >
            <StatusBadge kind="quote" :status="statusOf(quote.status)" />
            <Button v-if="editable" variant="outline" @click="save()"
                >Enregistrer</Button
            >
            <Button v-if="can.submit" @click="submitQuote">Soumettre</Button>
            <Button
                v-if="quote.status === 'soumis' && can.validate"
                class="bg-success text-white hover:bg-success/90"
                @click="decide('validate')"
                >Valider</Button
            >
            <Button
                v-if="quote.status === 'soumis' && can.validate"
                variant="outline"
                class="text-destructive"
                @click="refuseOpen = true"
                >Refuser</Button
            >
            <Button
                v-if="can.duplicate"
                variant="outline"
                @click="decide('version')"
                ><Copy /> Dupliquer en nouvelle version</Button
            >
            <Button v-if="can.createContract" as-child
                ><Link :href="ContractController.index.url()"
                    ><Handshake /> Créer le marché</Link
                ></Button
            >
            <Button v-if="contract" variant="outline" as-child
                ><Link :href="ContractController.show.url(contract.id)"
                    ><Handshake /> Voir le marché {{ contract.number }}</Link
                ></Button
            >
        </PageHeader>

        <p
            v-if="quote.status === 'refuse' && quote.refusalReason"
            class="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm"
            role="status"
        >
            Motif du refus : {{ quote.refusalReason }}
        </p>
        <p v-if="versions.length > 1" class="text-sm text-muted-foreground">
            Versions :
            <template v-for="v in versions" :key="v.id">
                <Link
                    :href="QuoteController.show.url(v.id)"
                    class="mr-2 underline"
                    :class="v.id === quote.id ? 'font-semibold' : ''"
                    >v{{ v.version }}</Link
                >
            </template>
        </p>

        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'editeur', label: 'Éditeur d’ouvrages' },
                { key: 'apercu', label: 'Aperçu imprimable' },
            ]"
        />

        <!-- Éditeur -->
        <div v-if="tab === 'editeur'" class="space-y-4">
            <section
                class="overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 font-medium">Ouvrage</th>
                                <th class="p-3 font-medium">Unité</th>
                                <th class="p-3 text-right font-medium">
                                    Quantité
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Prix unitaire
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Total
                                </th>
                                <th class="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(w, i) in quote.works"
                                :key="w.id"
                                class="border-t align-top"
                            >
                                <td class="p-3">
                                    <Input
                                        v-if="editable"
                                        v-model="w.label"
                                        :aria-label="`Libellé ouvrage ${i + 1}`"
                                        :aria-invalid="!!lineIssue(w)"
                                    />
                                    <template v-else>{{ w.label }}</template>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Composition : {{ w.components || '—' }}
                                    </p>
                                    <p
                                        v-if="editable && lineIssue(w)"
                                        class="text-sm text-destructive"
                                        role="alert"
                                    >
                                        {{ lineIssue(w) }}
                                    </p>
                                </td>
                                <td class="p-3">{{ w.unit }}</td>
                                <td class="p-3 text-right">
                                    <Input
                                        v-if="editable"
                                        v-model.number="w.quantity"
                                        type="number"
                                        min="0"
                                        class="w-28 text-right"
                                        :aria-label="`Quantité ouvrage ${i + 1}`"
                                    />
                                    <span v-else class="tabular-nums">{{
                                        number(w.quantity)
                                    }}</span>
                                </td>
                                <td class="p-3 text-right">
                                    <Input
                                        v-if="editable"
                                        v-model.number="w.unitPrice"
                                        type="number"
                                        min="0"
                                        class="w-32 text-right"
                                        :aria-label="`Prix unitaire ouvrage ${i + 1}`"
                                    />
                                    <span v-else class="tabular-nums">{{
                                        fcfa(w.unitPrice)
                                    }}</span>
                                </td>
                                <td
                                    class="p-3 text-right font-medium tabular-nums"
                                >
                                    {{ fcfa(workTotal(w)) }}
                                </td>
                                <td class="p-3">
                                    <Button
                                        v-if="editable"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Supprimer l’ouvrage"
                                        @click="quote.works.splice(i, 1)"
                                        ><Trash2
                                    /></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-if="editable" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="libraryOpen = true"
                    ><BookOpen /> Ajouter depuis la bibliothèque</Button
                >
                <Button variant="outline" @click="addWork"
                    ><Plus /> Ligne libre</Button
                >
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section
                    class="space-y-2 rounded-xl border bg-card p-4 shadow-xs"
                >
                    <h2 class="font-display font-semibold">Remise et taxes</h2>
                    <label
                        class="flex items-center justify-between gap-3 text-sm"
                        >Remise (%)
                        <Input
                            v-model.number="quote.discountRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="!editable"
                            class="w-24 text-right"
                        />
                    </label>
                    <p
                        v-if="discountIssue"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ discountIssue }}
                    </p>
                    <label
                        class="flex items-center justify-between gap-3 text-sm"
                        >TVA (%)
                        <Input
                            v-model.number="quote.vatRate"
                            type="number"
                            min="0"
                            :disabled="!editable"
                            class="w-24 text-right"
                        />
                    </label>
                    <label
                        class="flex items-center justify-between gap-3 text-sm"
                        >Déboursé sec (FCFA)
                        <Input
                            v-model.number="quote.directCost"
                            type="number"
                            min="0"
                            :disabled="!editable"
                            class="w-40 text-right"
                        />
                    </label>
                </section>

                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-2 font-display font-semibold">Totaux</h2>
                    <dl class="space-y-1.5 text-sm tabular-nums">
                        <div class="flex justify-between">
                            <dt>Sous-total</dt>
                            <dd>{{ fcfa(totals.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Remise ({{ quote.discountRate }} %)</dt>
                            <dd>-{{ fcfa(totals.discount) }}</dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>Total HT</dt>
                            <dd>{{ fcfa(totals.ht) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>TVA ({{ quote.vatRate }} %)</dt>
                            <dd>{{ fcfa(totals.vat) }}</dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-1.5 font-display text-base font-semibold"
                        >
                            <dt>Total TTC</dt>
                            <dd>{{ fcfa(totals.ttc) }}</dd>
                        </div>
                        <div
                            class="mt-3 flex justify-between rounded-lg bg-muted p-2"
                            :class="totals.margin < 0 ? 'text-danger' : ''"
                        >
                            <dt>Marge prévue (HT − déboursé sec)</dt>
                            <dd class="font-medium">
                                {{ fcfa(totals.margin) }} ·
                                {{ percent(totals.marginRate, 1) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>

        <!-- Aperçu imprimable (charte Mega Challenge) -->
        <div v-else class="space-y-3">
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer / PDF</Button
            >
            <article
                class="print-area mx-auto w-full max-w-3xl space-y-6 rounded-xl border bg-white p-8 text-ink shadow-xs"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b-2 border-brand pb-4"
                >
                    <img
                        src="/brand/logo-mega-challenge.svg"
                        alt="Mega Challenge"
                        class="h-16 w-auto"
                    />
                    <div class="text-right text-sm">
                        <p class="font-display text-xl font-semibold">DEVIS</p>
                        <p>{{ quote.number }} — v{{ quote.version }}</p>
                        <p>Abidjan, le {{ dateFr(quote.date) }}</p>
                    </div>
                </header>
                <section class="text-sm">
                    <p class="font-semibold">{{ quote.clientName }}</p>
                    <p>Objet : {{ quote.projectName }}</p>
                </section>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-brand text-left text-white">
                            <th class="p-2 font-medium">Désignation</th>
                            <th class="p-2 font-medium">U</th>
                            <th class="p-2 text-right font-medium">Qté</th>
                            <th class="p-2 text-right font-medium">
                                P.U. (FCFA)
                            </th>
                            <th class="p-2 text-right font-medium">
                                Total (FCFA)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="w in quote.works"
                            :key="w.id"
                            class="border-b"
                        >
                            <td class="p-2">{{ w.label }}</td>
                            <td class="p-2">{{ w.unit }}</td>
                            <td class="p-2 text-right tabular-nums">
                                {{ number(w.quantity) }}
                            </td>
                            <td class="p-2 text-right tabular-nums">
                                {{ number(w.unitPrice) }}
                            </td>
                            <td class="p-2 text-right tabular-nums">
                                {{ number(workTotal(w)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <dl class="ml-auto w-72 space-y-1 text-sm tabular-nums">
                    <div class="flex justify-between">
                        <dt>Sous-total</dt>
                        <dd>{{ fcfa(totals.subtotal) }}</dd>
                    </div>
                    <div v-if="totals.discount" class="flex justify-between">
                        <dt>Remise {{ quote.discountRate }} %</dt>
                        <dd>-{{ fcfa(totals.discount) }}</dd>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <dt>Total HT</dt>
                        <dd>{{ fcfa(totals.ht) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>TVA {{ quote.vatRate }} %</dt>
                        <dd>{{ fcfa(totals.vat) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t-2 border-brand pt-1 text-base font-bold"
                    >
                        <dt>Total TTC</dt>
                        <dd>{{ fcfa(totals.ttc) }}</dd>
                    </div>
                </dl>
                <footer class="border-t pt-3 text-xs text-muted-foreground">
                    Devis valable 30 jours. Mega Challenge — Abidjan, Côte
                    d’Ivoire.
                </footer>
            </article>
        </div>

        <Button variant="ghost" as-child
            ><Link href="/devis">Retour à la liste</Link></Button
        >

        <ConfirmDialog
            v-model:open="refuseOpen"
            title="Refuser le devis"
            confirm-label="Refuser"
            require-reason
            destructive
            @confirm="onRefuse"
        />
        <FormDialog
            v-model:open="libraryOpen"
            title="Ajouter un ouvrage de la bibliothèque"
            description="Le prix est calculé à partir de la composition (articles du référentiel et main-d’œuvre)."
            :fields="libraryFields"
            submit-label="Ajouter"
            @submit="addFromLibrary"
        />
    </div>
</template>
