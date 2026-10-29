<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { consultations } from '@/mocks/v2';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Consultations', href: '/achats/consultations' },
        ],
    },
});

const { can } = usePermissions();
const consultation = computed(
    () => consultations.find((c) => c.id === props.id) ?? consultations[0],
);
const sorted = computed(() =>
    [...consultation.value.quotes].sort((a, b) => a.unitTotal - b.unitTotal),
);
const best = computed(() => sorted.value[0]);
const fastest = computed(
    () =>
        [...consultation.value.quotes].sort(
            (a, b) => a.delayDays - b.delayDays,
        )[0],
);
const missing = computed(() =>
    Math.max(
        0,
        consultation.value.minSuppliers - consultation.value.quotes.length,
    ),
);
const chosen = ref<number | null>(
    consultation.value.quotes.find((q) => q.selected)?.supplierId ?? null,
);

function choose() {
    toast.success(
        'Fournisseur retenu : le bon de commande est créé (maquette).',
    );
}
</script>

<template>
    <Head :title="consultation.number" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="consultation.number"
            :description="`${consultation.requestNumber} · ${consultation.chantierName}`"
        >
            <StatusBadge kind="consultation" :status="consultation.status" />
        </PageHeader>
        <SubNav :links="achatsNav" />

        <p class="text-sm text-muted-foreground">{{ consultation.items }}</p>

        <p
            v-if="missing"
            class="flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            Il manque {{ missing }} offre(s) : au moins
            {{ consultation.minSuppliers }} fournisseurs doivent être consultés
            avant de commander.
        </p>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Choix</th>
                            <th class="p-3 font-medium">Fournisseur</th>
                            <th class="p-3 text-right font-medium">Total HT</th>
                            <th class="p-3 text-right font-medium">
                                Écart / meilleure offre
                            </th>
                            <th class="p-3 text-right font-medium">Délai</th>
                            <th class="p-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="q in sorted"
                            :key="q.supplierId"
                            class="border-t"
                            :class="
                                chosen === q.supplierId
                                    ? 'bg-success-soft/50'
                                    : ''
                            "
                        >
                            <td class="p-3">
                                <input
                                    v-model="chosen"
                                    type="radio"
                                    name="offer"
                                    :value="q.supplierId"
                                    :disabled="!can('achats.update')"
                                    :aria-label="`Retenir ${q.supplierName}`"
                                    class="size-4"
                                />
                            </td>
                            <td class="p-3 font-medium">
                                {{ q.supplierName }}
                                <Trophy
                                    v-if="q.supplierId === best.supplierId"
                                    class="ml-1 inline size-4 text-brand"
                                />
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ fcfa(q.unitTotal) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                <span
                                    v-if="q.unitTotal === best.unitTotal"
                                    class="text-success"
                                    >Meilleure offre</span
                                >
                                <span v-else
                                    >+{{
                                        fcfa(q.unitTotal - best.unitTotal)
                                    }}</span
                                >
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ q.delayDays }} j
                                <span
                                    v-if="q.supplierId === fastest.supplierId"
                                    class="block text-xs text-success"
                                    >Le plus rapide</span
                                >
                            </td>
                            <td class="p-3">
                                <StatusBadge
                                    v-if="q.selected"
                                    label="Retenu"
                                    tone="success"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can('achats.update')"
                :disabled="
                    chosen === null ||
                    missing > 0 ||
                    consultation.status === 'attribuee'
                "
                @click="choose"
            >
                Retenir ce fournisseur et créer le bon de commande
            </Button>
            <Button variant="outline" as-child
                ><Link href="/achats/consultations">Retour</Link></Button
            >
        </div>
    </div>
</template>
