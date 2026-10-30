<script setup lang="ts">
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useLegacySession } from '@/composables/useLegacySession';
import { fcfa } from '@/lib/format';
import { exercices, rubriques } from '@/mocks/legacy';

/** Trois écrans du budget par exercice : choix/ouverture/clôture, élaboration, reconduction. */
defineProps<{
    mode: 'exercice' | 'elaboration' | 'reconduction';
}>();

const { session } = useLegacySession();

const states = ref<Record<number, 'ouvert' | 'cloture' | 'prevu'>>({
    2024: 'cloture',
    2025: 'cloture',
    2026: 'ouvert',
    2027: 'prevu',
});

const amounts = ref(
    rubriques.map((r) => ({ code: r.code, label: r.label, budget: r.budget })),
);
const total = computed(() =>
    amounts.value.reduce((s, r) => s + (r.budget || 0), 0),
);

const source = ref(2025);
const target = ref(2027);
const rate = ref(0);
const preview = computed(() =>
    amounts.value.map((r) => ({
        ...r,
        next: Math.round(r.budget * (1 + rate.value / 100)),
    })),
);

const titles = {
    exercice: [
        'Exercice budgétaire',
        'Choisissez l’exercice en cours, ouvrez-en un nouveau ou clôturez-le.',
    ],
    elaboration: [
        'Paramètres / Élaboration budgétaire',
        'Saisissez le budget de chaque rubrique pour l’exercice en cours.',
    ],
    reconduction: [
        'Reconduire le budget d’une année',
        'Recopiez le budget d’un exercice vers un autre, avec un ajustement en pourcentage.',
    ],
} as const;

function choose(y: number) {
    session.value.exercice = y;
    toast.success(`Exercice ${y} choisi.`);
}

function open(y: number) {
    states.value[y] = 'ouvert';
    toast.success(`Exercice ${y} ouvert (maquette).`);
}

function close(y: number) {
    states.value[y] = 'cloture';
    toast.success(`Exercice ${y} clôturé (maquette).`);
}
</script>

<template>
    <div class="space-y-4 p-4">
        <PageHeader :title="titles[mode][0]" :description="titles[mode][1]" />

        <div
            v-if="mode === 'exercice'"
            class="overflow-x-auto rounded-xl border bg-card shadow-xs"
        >
            <table class="w-full min-w-[32rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Exercice</th>
                        <th class="px-3 py-2">État</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="y in exercices"
                        :key="y"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 font-medium tabular-nums">
                            {{ y }}
                            <span
                                v-if="y === session.exercice"
                                class="text-xs text-brand"
                                >(en cours)</span
                            >
                        </td>
                        <td class="px-3 py-2">
                            <StatusBadge
                                :label="
                                    states[y] === 'ouvert'
                                        ? 'Ouvert'
                                        : states[y] === 'cloture'
                                          ? 'Clôturé'
                                          : 'Prévu'
                                "
                                :tone="
                                    states[y] === 'ouvert'
                                        ? 'success'
                                        : 'neutral'
                                "
                            />
                        </td>
                        <td class="flex flex-wrap gap-2 px-3 py-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="y === session.exercice"
                                @click="choose(y)"
                                >Choisir</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="states[y] !== 'prevu'"
                                @click="open(y)"
                                >Ouvrir</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="states[y] !== 'ouvert'"
                                @click="close(y)"
                                >Clôturer</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template v-else-if="mode === 'elaboration'">
            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2">Rubrique</th>
                            <th class="px-3 py-2">Libellé</th>
                            <th class="px-3 py-2">
                                Budget {{ session.exercice }} (FCFA)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in amounts"
                            :key="r.code"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 font-medium">{{ r.code }}</td>
                            <td class="px-3 py-2">{{ r.label }}</td>
                            <td class="px-3 py-2">
                                <input
                                    v-model.number="r.budget"
                                    type="number"
                                    min="0"
                                    class="h-8 w-44 rounded-md border bg-background px-2 text-right tabular-nums"
                                    :aria-label="`Budget rubrique ${r.code}`"
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-medium">
                            <td colspan="2" class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ fcfa(total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <Button @click="toast.success('Budget enregistré (maquette).')"
                >Enregistrer le budget</Button
            >
        </template>

        <template v-else>
            <div class="flex flex-wrap items-end gap-3">
                <label class="grid gap-1 text-sm"
                    ><span class="font-medium">De l’exercice</span>
                    <select
                        v-model.number="source"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option v-for="y in exercices" :key="y" :value="y">
                            {{ y }}
                        </option>
                    </select></label
                >
                <label class="grid gap-1 text-sm"
                    ><span class="font-medium">Vers l’exercice</span>
                    <select
                        v-model.number="target"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option v-for="y in exercices" :key="y" :value="y">
                            {{ y }}
                        </option>
                    </select></label
                >
                <label class="grid gap-1 text-sm"
                    ><span class="font-medium">Ajustement (%)</span>
                    <input
                        v-model.number="rate"
                        type="number"
                        min="-100"
                        max="100"
                        class="h-9 w-24 rounded-md border bg-background px-2"
                /></label>
                <Button
                    :disabled="source === target"
                    @click="
                        toast.success(
                            `Budget ${source} reconduit vers ${target} (maquette).`,
                        )
                    "
                    >Reconduire</Button
                >
            </div>
            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2">Rubrique</th>
                            <th class="px-3 py-2 text-right">
                                Budget {{ source }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                Budget {{ target }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in preview"
                            :key="r.code"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2">
                                {{ r.code }} – {{ r.label }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fcfa(r.budget) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fcfa(r.next) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
