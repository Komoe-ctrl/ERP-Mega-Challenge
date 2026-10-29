<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Copy, Plus, Printer, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { quoteTotals, workTotal } from '@/lib/billing';
import { dateFr, fcfa, number, percent } from '@/lib/format';
import { quotes } from '@/mocks/v3';
import type { QuoteDoc, QuoteStatus } from '@/types/erp';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Devis', href: '/devis' }] },
});

const { can } = usePermissions();
const quote = reactive<QuoteDoc>(
    structuredClone(quotes.find((q) => q.id === props.id) ?? quotes[0]),
);
const tab = ref('editeur');
const refuseOpen = ref(false);

// L'édition n'est possible qu'en brouillon.
const editable = computed(
    () => quote.status === 'brouillon' && can('devis.update'),
);
const totals = computed(() => quoteTotals(quote));

function addWork() {
    quote.works.push({
        id: Date.now(),
        label: 'Nouvel ouvrage',
        unit: 'm²',
        quantity: 1,
        unitPrice: 0,
        components: '',
    });
}

function setStatus(status: QuoteStatus, message: string) {
    quote.status = status;
    toast.success(message);
}

function print() {
    window.print();
}

function onRefuse() {
    setStatus(
        'refuse',
        'Devis refusé. Il peut être dupliqué en nouvelle version.',
    );
}

function duplicate() {
    toast.success(
        `Nouvelle version v${quote.version + 1} créée en brouillon ; la version v${quote.version} est conservée (RG5.3).`,
    );
}
</script>

<template>
    <Head :title="quote.number" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="`${quote.number} — version ${quote.version}`"
            :description="`${quote.clientName} · ${quote.projectName}`"
        >
            <StatusBadge kind="quote" :status="quote.status" />
            <Button
                v-if="editable"
                @click="setStatus('soumis', 'Devis soumis pour validation.')"
                >Soumettre</Button
            >
            <Button
                v-if="quote.status === 'soumis' && can('devis.validate')"
                class="bg-success text-white hover:bg-success/90"
                @click="
                    setStatus(
                        'valide',
                        'Devis validé : il peut alimenter le marché.',
                    )
                "
                >Valider</Button
            >
            <Button
                v-if="quote.status === 'soumis' && can('devis.validate')"
                variant="outline"
                class="text-destructive"
                @click="refuseOpen = true"
                >Refuser</Button
            >
            <Button
                v-if="quote.status === 'refuse' && can('devis.create')"
                variant="outline"
                @click="duplicate"
                ><Copy /> Dupliquer en nouvelle version</Button
            >
        </PageHeader>

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
                                    />
                                    <template v-else>{{ w.label }}</template>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Composition : {{ w.components || '—' }}
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

            <Button v-if="editable" variant="outline" @click="addWork"
                ><Plus /> Ajouter un ouvrage</Button
            >

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
                class="print-area mx-auto w-full max-w-3xl space-y-6 rounded-xl border bg-white p-8 text-[#1b1e21] shadow-xs"
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
                        <tr class="bg-[#003366] text-left text-white">
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
                <footer class="border-t pt-3 text-xs text-[#4a4e52]">
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
    </div>
</template>
