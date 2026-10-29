<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { number } from '@/lib/format';
import { stocksNav } from '@/lib/navs';
import { inventoryLines } from '@/mocks/v2';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stocks', href: '/stocks' },
            { title: 'Inventaire', href: '/stocks/inventaire' },
        ],
    },
});

const { can } = usePermissions();
const lines = reactive(inventoryLines.map((l) => ({ ...l })));
const gaps = computed(() => lines.filter((l) => l.counted !== l.theoretical));

// CA9 : écart stock théorique / stock compté par article, ajusté après validation.
function adjust() {
    toast.success(
        `${gaps.value.length} écart(s) soumis à validation (maquette).`,
    );
}
</script>

<template>
    <Head title="Inventaire" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Inventaire"
            description="Magasin Palmiers Bloc A · comptage du 29/10/2026. Les écarts sont ajustés après validation."
        />
        <SubNav :links="stocksNav" />

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Article</th>
                            <th class="p-3 text-right font-medium">
                                Théorique
                            </th>
                            <th class="p-3 text-right font-medium">Compté</th>
                            <th class="p-3 text-right font-medium">Écart</th>
                            <th class="p-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in lines" :key="l.id" class="border-t">
                            <td class="p-3">{{ l.label }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(l.theoretical) }} {{ l.unit }}
                            </td>
                            <td class="p-3 text-right">
                                <input
                                    v-model.number="l.counted"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    class="h-9 w-28 rounded-md border border-input bg-transparent px-2 text-right tabular-nums"
                                    :aria-label="`Quantité comptée — ${l.label}`"
                                />
                            </td>
                            <td
                                class="p-3 text-right font-medium tabular-nums"
                                :class="
                                    l.counted - l.theoretical < 0
                                        ? 'text-danger'
                                        : l.counted - l.theoretical > 0
                                          ? 'text-success'
                                          : 'text-muted-foreground'
                                "
                            >
                                {{ l.counted - l.theoretical > 0 ? '+' : ''
                                }}{{ number(l.counted - l.theoretical) }}
                            </td>
                            <td class="p-3">
                                <StatusBadge
                                    v-if="l.counted !== l.theoretical"
                                    label="Écart à valider"
                                    tone="warning"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Button
            v-if="can('stocks.validate') || can('stocks.update')"
            :disabled="!gaps.length"
            @click="adjust"
        >
            Soumettre les {{ gaps.length }} écart(s) à validation
        </Button>
    </div>
</template>
