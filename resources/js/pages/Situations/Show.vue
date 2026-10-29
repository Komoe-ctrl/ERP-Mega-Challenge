<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Lock, Printer } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { computeSituation } from '@/lib/billing';
import { dateFr, fcfa, percent } from '@/lib/format';
import { situations } from '@/mocks/v3';
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
const calc = computed(() => computeSituation(situation));

function validate() {
    situation.status = 'validee';
    toast.success(`Situation n°${situation.number} validée et figée.`);
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
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer</Button
            >
            <Button
                v-if="!locked && can('situations.validate')"
                class="bg-success text-white hover:bg-success/90"
                @click="validate"
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
                <label class="grid gap-1.5 text-sm">
                    Avancement cumulé (%)
                    <input
                        v-model.number="situation.progressCumul"
                        type="range"
                        min="0"
                        max="100"
                        :disabled="locked"
                        class="h-8 w-full accent-brand"
                    />
                    <span
                        class="font-display text-2xl font-semibold tabular-nums"
                        >{{ percent(situation.progressCumul) }}</span
                    >
                </label>
                <ProgressBar
                    :value="situation.progressCumul"
                    :marker="situation.previousProgress"
                    label="Cumul (trait = situation précédente)"
                    :show-value="false"
                />
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <label class="grid gap-1"
                        >Retenue (%)<Input
                            v-model.number="situation.retentionRate"
                            type="number"
                            min="0"
                            :disabled="locked"
                    /></label>
                    <label class="grid gap-1"
                        >Acompte (%)<Input
                            v-model.number="situation.advanceRefundRate"
                            type="number"
                            min="0"
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
                        <dt>
                            Cumul n ({{ percent(situation.progressCumul) }})
                        </dt>
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
    </div>
</template>
