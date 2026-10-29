<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Lock, Printer } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { computeSituation, workTotal } from '@/lib/billing';
import { dateFr, fcfa, percent } from '@/lib/format';
import { contracts, quotes, situations } from '@/mocks/v3';
import { ref } from 'vue';
import type { Situation } from '@/types/erp';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Situations de travaux', href: '/situations' }],
    },
});

const { can } = usePermissions();
const situation = reactive<Situation>(
    structuredClone(situations.find((s) => s.id === props.id) ?? situations[0]),
);
const locked = computed(() => situation.status !== 'brouillon');
const validateOpen = ref(false);

const contract = computed(() =>
    contracts.find((c) => c.number === situation.contractNumber),
);
// RG11.1 : avancement par ouvrage (devis du marché) ; sans devis, un seul curseur global.
const works = computed(
    () =>
        quotes.find(
            (q) =>
                contract.value &&
                contract.value.projectName.startsWith(
                    q.projectName.split(' — ')[0],
                ),
        )?.works ?? [],
);
const workProgress = reactive<Record<number, number>>(
    Object.fromEntries(works.value.map((w) => [w.id, situation.progressCumul])),
);
const totalWorks = computed(() =>
    works.value.reduce((s, w) => s + workTotal(w), 0),
);

const progress = computed(() => {
    if (!works.value.length || locked.value) {
        return situation.progressCumul;
    }

    return (
        Math.round(
            (works.value.reduce(
                (s, w) => s + workTotal(w) * (workProgress[w.id] ?? 0),
                0,
            ) /
                totalWorks.value) *
                10,
        ) / 10
    );
});
const calc = computed(() =>
    computeSituation({ ...situation, progressCumul: progress.value }),
);

// L'avancement cumulé ne peut pas diminuer d'une situation à l'autre.
const progressIssue = computed(() =>
    progress.value < situation.previousProgress
        ? `L’avancement cumulé (${progress.value} %) ne peut pas être inférieur à celui de la situation précédente (${situation.previousProgress} %).`
        : '',
);
const ratesIssue = computed(() =>
    situation.retentionRate < 0 ||
    situation.retentionRate > 100 ||
    situation.advanceRefundRate < 0 ||
    situation.advanceRefundRate > 100 ||
    situation.vatRate < 0
        ? 'Les taux doivent être compris entre 0 et 100 %.'
        : '',
);

function validate() {
    situation.progressCumul = progress.value;
    situation.status = 'validee';
    toast.success(
        `Situation n°${situation.number} validée et figée : une correction passera par la suivante.`,
    );
}

function askValidate() {
    if (progressIssue.value || ratesIssue.value) {
        toast.error('Corrigez les valeurs signalées avant de valider.');

        return;
    }

    validateOpen.value = true;
}

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Situation n°${situation.number}`" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="`Situation n°${situation.number}`"
            :description="`${situation.contractNumber} · ${situation.clientName} · ${dateFr(situation.date)}`"
        >
            <StatusBadge kind="situation" :status="situation.status" />
            <Button v-if="contract" variant="outline" as-child
                ><Link :href="`/marches/${contract.id}`"
                    >Voir le marché</Link
                ></Button
            >
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer</Button
            >
            <Button
                v-if="!locked && can('situations.validate')"
                class="bg-success text-white hover:bg-success/90"
                @click="askValidate"
                >Valider et figer</Button
            >
        </PageHeader>

        <p
            v-if="locked"
            class="flex items-center gap-2 rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            <Lock class="size-4" /> Situation figée : une correction passe par
            la situation suivante.
        </p>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="font-display font-semibold">Avancement</h2>

                <div v-if="works.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Ouvrage</th>
                                <th class="py-1 text-right font-medium">
                                    Montant du marché
                                </th>
                                <th class="py-1 text-right font-medium">
                                    Cumul réalisé
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="w in works" :key="w.id" class="border-t">
                                <td class="py-1.5 pr-2">{{ w.label }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ fcfa(workTotal(w)) }}
                                </td>
                                <td class="py-1.5 text-right">
                                    <Input
                                        v-if="!locked"
                                        v-model.number="workProgress[w.id]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="ml-auto w-20 text-right"
                                        :aria-label="`Avancement cumulé : ${w.label}`"
                                    />
                                    <span v-else class="tabular-nums">{{
                                        percent(situation.progressCumul)
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Cumul de l’ouvrage = quantités réalisées × prix du
                        marché (RG11.1).
                    </p>
                </div>
                <label v-else class="grid gap-1.5 text-sm">
                    Avancement cumulé (%)
                    <input
                        v-model.number="situation.progressCumul"
                        type="range"
                        min="0"
                        max="100"
                        :disabled="locked"
                        class="h-8 w-full accent-brand"
                    />
                </label>

                <p class="font-display text-2xl font-semibold tabular-nums">
                    {{ percent(progress, 1) }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >d’avancement cumulé</span
                    >
                </p>
                <ProgressBar
                    :value="progress"
                    :marker="situation.previousProgress"
                    label="Cumul (trait = situation précédente)"
                    :show-value="false"
                />
                <p
                    v-if="progressIssue"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ progressIssue }}
                </p>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <label class="grid gap-1"
                        >Retenue (%)<Input
                            v-model.number="situation.retentionRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="locked"
                    /></label>
                    <label class="grid gap-1"
                        >Acompte (%)<Input
                            v-model.number="situation.advanceRefundRate"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="locked"
                    /></label>
                    <label class="grid gap-1"
                        >TVA (%)<Input
                            v-model.number="situation.vatRate"
                            type="number"
                            min="0"
                            :disabled="locked"
                    /></label>
                </div>
                <p
                    v-if="ratesIssue"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ ratesIssue }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Taux repris du marché (conditions de paiement, RG6.3 et
                    RG11.2).
                </p>
            </section>

            <section class="print-area rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Calcul du net à payer
                </h2>
                <dl class="space-y-1.5 text-sm tabular-nums">
                    <div class="flex justify-between">
                        <dt>Montant du marché</dt>
                        <dd>{{ fcfa(situation.marketAmount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Cumul n ({{ percent(progress, 1) }})</dt>
                        <dd>{{ fcfa(calc.cumul) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Cumul n−1 ({{
                                percent(situation.previousProgress)
                            }})
                        </dt>
                        <dd>−{{ fcfa(calc.previousCumul) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-1.5 font-medium"
                    >
                        <dt>Travaux de la période</dt>
                        <dd>{{ fcfa(calc.amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Retenue de garantie ({{ situation.retentionRate }}
                            %)
                        </dt>
                        <dd>−{{ fcfa(calc.retention) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>
                            Remboursement d’acompte ({{
                                situation.advanceRefundRate
                            }}
                            %)
                        </dt>
                        <dd>−{{ fcfa(calc.advanceRefund) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-1.5 font-medium"
                    >
                        <dt>Net à payer hors taxes</dt>
                        <dd>{{ fcfa(calc.netHt) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>TVA ({{ situation.vatRate }} %)</dt>
                        <dd>+{{ fcfa(calc.vat) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t-2 border-brand pt-1.5 font-display text-lg font-semibold"
                    >
                        <dt>Net à payer TTC</dt>
                        <dd>{{ fcfa(calc.netTtc) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <Button variant="ghost" as-child
            ><Link href="/situations">Retour à la liste</Link></Button
        >

        <ConfirmDialog
            v-model:open="validateOpen"
            title="Valider et figer la situation"
            description="Une situation validée est numérotée et figée : elle ne pourra plus être modifiée. Une correction passera par la situation suivante (RG11.3)."
            confirm-label="Valider et figer"
            @confirm="validate"
        />
    </div>
</template>
