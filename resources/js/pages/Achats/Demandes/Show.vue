<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, CornerUpLeft, Info, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import PurchaseRequestController from '@/actions/App/Http/Controllers/Achats/PurchaseRequestController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, number } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import { invoices, orders, receptions } from '@/mocks/v2';
import type { PurchaseRequest } from '@/types/erp';

const props = defineProps<{
    request: PurchaseRequest;
    budget: {
        revised: number;
        committed: number;
        actual: number;
        remaining: number;
        consumption: number;
    };
    threshold: number;
    isAuthor: boolean;
    canDecide: boolean;
    directionRequired: boolean;
    canValidate: boolean;
    canCancel: boolean;
    canEdit: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
});

// Quantités ajustables par le valideur (RG8.5) ; l'écart est tracé par le serveur.
const adjusted = reactive<Record<number, number>>(
    Object.fromEntries(
        props.request.lines.map((l) => [
            l.id,
            l.adjustedQuantity ?? l.quantity,
        ]),
    ),
);

// CA8.2 : depuis la demande, retrouver ses bons de commande, réceptions, factures et leur paiement.
// Branché avec le processus d'achat complet (V2) ; maquettes en attendant.
const linkedOrders = computed(() =>
    orders.filter((o) => o.requestNumbers.includes(props.request.number)),
);
const linkedReceptions = computed(() =>
    receptions.filter((r) =>
        linkedOrders.value.some((o) => o.number === r.orderNumber),
    ),
);
const linkedInvoices = computed(() =>
    invoices.filter((i) =>
        linkedOrders.value.some((o) => o.number === i.orderNumber),
    ),
);

const decision = ref<'refuser' | 'renvoyer' | 'annuler' | null>(null);
const dialogOpen = computed({
    get: () => decision.value !== null,
    set: (v) => {
        if (!v) {
            decision.value = null;
        }
    },
});

const frozen = computed(() => props.request.status === 'validee');
const unitPrice = (l: { unitPrice?: number }) => l.unitPrice ?? 0;
const total = computed(() =>
    props.request.lines.reduce(
        (s, l) => s + unitPrice(l) * (adjusted[l.id] ?? 0),
        0,
    ),
);
const originalTotal = computed(() =>
    props.request.lines.reduce((s, l) => s + unitPrice(l) * l.quantity, 0),
);

const remainingAfter = computed(
    () => props.budget.remaining - (frozen.value ? 0 : total.value),
);
// Indication à l'écran ; le serveur décide du circuit (RG8.7).
const needsDirection = computed(
    () =>
        props.directionRequired ||
        total.value > props.threshold ||
        originalTotal.value > props.threshold ||
        remainingAfter.value < 0,
);

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0]);

function validate() {
    router.post(
        PurchaseRequestController.validateRequest.url(props.request.id),
        { adjustments: { ...adjusted } },
        { preserveScroll: true, onError },
    );
}

function onDecision(reason: string) {
    const routes = {
        refuser: PurchaseRequestController.refuse,
        renvoyer: PurchaseRequestController.sendBack,
        annuler: PurchaseRequestController.cancel,
    } as const;

    if (decision.value) {
        router.post(
            routes[decision.value].url(props.request.id),
            { reason },
            { preserveScroll: true, onError },
        );
    }
}

const titles = {
    refuser: 'Refuser la demande',
    renvoyer: 'Renvoyer pour correction',
    annuler: 'Annuler la demande',
} as const;
</script>

<template>
    <Head :title="request.number" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="request.number"
            :description="`${request.chantierName} · ${request.requester} · ${dateFr(request.createdAt)}`"
        >
            <StatusBadge kind="request" :status="request.status" />
            <StatusBadge
                v-if="needsDirection && request.status === 'en_attente'"
                label="Validation Direction"
                tone="brand"
            />
            <Button variant="outline" as-child
                ><Link href="/achats/demandes">Retour à la liste</Link></Button
            >
            <Button variant="outline" as-child
                ><Link :href="`/chantiers/${request.chantierId}`"
                    >Voir le chantier</Link
                ></Button
            >
        </PageHeader>

        <p v-if="request.justification" class="text-sm text-muted-foreground">
            « {{ request.justification }} »
        </p>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Article</th>
                            <th class="p-3 text-right font-medium">
                                Prix unitaire
                            </th>
                            <th class="p-3 text-right font-medium">Quantité</th>
                            <th class="p-3 text-right font-medium">Montant</th>
                            <th class="p-3 font-medium">Besoin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in request.lines"
                            :key="l.id"
                            class="border-t"
                        >
                            <td class="p-3">
                                {{ l.articleLabel }}
                                <StatusBadge
                                    v-if="l.urgency !== 'normale'"
                                    kind="urgency"
                                    :status="l.urgency"
                                    class="ml-1"
                                />
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ fcfa(unitPrice(l)) }}
                                <span
                                    v-if="frozen"
                                    class="block text-xs text-muted-foreground"
                                    >figé</span
                                >
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                <template v-if="canDecide">
                                    <input
                                        v-model.number="adjusted[l.id]"
                                        type="number"
                                        min="0"
                                        class="h-9 w-24 rounded-md border border-input bg-transparent px-2 text-right"
                                        :aria-label="`Quantité ajustée — ${l.articleLabel}`"
                                    />
                                    <span
                                        v-if="adjusted[l.id] !== l.quantity"
                                        class="block text-xs text-warning"
                                    >
                                        demandé : {{ l.quantity }}
                                    </span>
                                </template>
                                <template v-else>
                                    {{ adjusted[l.id] }}
                                    {{ l.unit }}
                                    <span
                                        v-if="adjusted[l.id] !== l.quantity"
                                        class="block text-xs text-warning"
                                    >
                                        demandé : {{ l.quantity }}
                                    </span>
                                </template>
                            </td>
                            <td class="p-3 text-right font-medium tabular-nums">
                                {{ fcfa(unitPrice(l) * adjusted[l.id]) }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ dateFr(l.needDate) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30">
                            <td class="p-3 font-semibold" colspan="3">Total</td>
                            <td
                                class="p-3 text-right font-display text-base font-semibold tabular-nums"
                            >
                                {{ fcfa(total) }}
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Impact budgétaire -->
            <section class="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="font-display font-semibold">
                    Impact sur le budget du chantier
                </h2>
                <ProgressBar
                    :value="budget.consumption"
                    :tone="consumptionTone(budget.consumption)"
                    :label="`Consommé aujourd’hui : ${number(budget.consumption, 1)} %`"
                    :show-value="false"
                />
                <p class="text-sm text-muted-foreground">
                    Reste à engager : {{ fcfa(budget.remaining) }}
                    <template v-if="!frozen">
                        → après validation :
                        <strong
                            :class="
                                remainingAfter < 0
                                    ? 'text-danger'
                                    : 'text-foreground'
                            "
                            >{{ fcfa(remainingAfter) }}</strong
                        ></template
                    >
                </p>
                <p
                    v-if="remainingAfter < 0 && !frozen"
                    class="flex gap-2 rounded-lg bg-danger-soft p-2 text-sm text-danger"
                >
                    <Info class="mt-0.5 size-4 shrink-0" /> Dépassement du
                    budget restant : la Direction doit valider (RG8.7).
                </p>
                <p
                    v-if="originalTotal !== total"
                    class="text-xs text-muted-foreground"
                >
                    Écart de quantités : {{ fcfa(total - originalTotal) }} par
                    rapport à la demande initiale.
                </p>
            </section>

            <!-- Historique -->
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Historique</h2>
                <ol class="space-y-3 border-l pl-4">
                    <li
                        v-for="(e, i) in request.history"
                        :key="i"
                        class="relative text-sm"
                    >
                        <span
                            class="absolute top-1.5 -left-[21px] size-2.5 rounded-full bg-brand"
                        />
                        <p class="font-medium">{{ e.action }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ e.actor }} · {{ e.date }}
                        </p>
                        <p v-if="e.reason" class="mt-0.5 text-muted-foreground">
                            « {{ e.reason }} »
                        </p>
                    </li>
                </ol>
            </section>
        </div>

        <section
            v-if="request.status === 'validee'"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <h2 class="mb-3 font-display font-semibold">
                Suite du parcours d’achat
            </h2>
            <div v-if="linkedOrders.length" class="grid gap-4 md:grid-cols-3">
                <div>
                    <h3 class="mb-1 text-xs font-medium text-muted-foreground">
                        Bons de commande
                    </h3>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="o in linkedOrders"
                            :key="o.id"
                            class="flex items-center justify-between gap-2"
                        >
                            <Link
                                :href="`/achats/commandes/${o.id}`"
                                class="hover:underline"
                                >{{ o.number }}</Link
                            >
                            <StatusBadge kind="order" :status="o.status" />
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-1 text-xs font-medium text-muted-foreground">
                        Réceptions
                    </h3>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="r in linkedReceptions"
                            :key="r.id"
                            class="flex items-center justify-between gap-2"
                        >
                            <Link
                                href="/achats/receptions"
                                class="hover:underline"
                                >{{ r.number }}</Link
                            >
                            <StatusBadge kind="reception" :status="r.gap" />
                        </li>
                        <li
                            v-if="!linkedReceptions.length"
                            class="text-muted-foreground"
                        >
                            Aucune réception.
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-1 text-xs font-medium text-muted-foreground">
                        Factures et paiement
                    </h3>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="i in linkedInvoices"
                            :key="i.id"
                            class="flex items-center justify-between gap-2"
                        >
                            <Link
                                href="/achats/factures"
                                class="hover:underline"
                                >{{ i.number }}</Link
                            >
                            <StatusBadge kind="invoice" :status="i.status" />
                        </li>
                        <li
                            v-if="!linkedInvoices.length"
                            class="text-muted-foreground"
                        >
                            Aucune facture.
                        </li>
                    </ul>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Prise en charge par le Service Achats BTP en attente : aucun bon
                de commande pour l’instant.
            </p>
        </section>

        <!-- Décision du valideur : les droits viennent du serveur -->
        <div
            v-if="canDecide"
            class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-2 border-t bg-card/95 p-3 backdrop-blur"
        >
            <Button
                v-if="canValidate"
                class="bg-success text-white hover:bg-success/90"
                @click="validate"
                ><Check /> Valider</Button
            >
            <Button variant="outline" @click="decision = 'renvoyer'"
                ><CornerUpLeft /> Renvoyer</Button
            >
            <Button
                variant="outline"
                class="text-destructive"
                @click="decision = 'refuser'"
                ><X /> Refuser</Button
            >
            <p
                v-if="!canValidate"
                class="basis-full text-sm text-muted-foreground"
            >
                Cette demande dépasse le budget restant ou le seuil de
                {{ fcfa(threshold) }} : seule la Direction peut la valider
                (RG8.7). Vous pouvez la renvoyer ou la refuser.
            </p>
        </div>
        <p
            v-else-if="request.status === 'en_attente' && isAuthor"
            class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            Vous êtes l’auteur de cette demande : vous ne pouvez pas la valider
            vous-même (séparation des tâches).
        </p>
        <div v-if="canEdit">
            <Button as-child
                ><Link :href="PurchaseRequestController.edit.url(request.id)"
                    >Corriger et soumettre à nouveau</Link
                ></Button
            >
        </div>
        <div v-if="canCancel">
            <Button
                variant="ghost"
                class="text-destructive"
                @click="decision = 'annuler'"
                >Annuler cette demande</Button
            >
        </div>

        <ConfirmDialog
            v-model:open="dialogOpen"
            :title="decision ? titles[decision] : ''"
            confirm-label="Confirmer"
            require-reason
            :destructive="decision !== 'renvoyer'"
            @confirm="onDecision"
        />
    </div>
</template>
