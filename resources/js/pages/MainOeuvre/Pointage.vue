<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/erp/EmptyState.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { categoryLabels, employees } from '@/mocks/v4';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { chantiers } from '@/mocks/chantiers';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Main-d’œuvre', href: '/main-oeuvre' }] },
});

const tab = ref('pointage');
const activeChantiers = chantiers.filter((c) => c.status === 'en_cours');
const chantierId = ref(activeChantiers[0].id);
const chantierName = computed(
    () => chantiers.find((c) => c.id === chantierId.value)?.name ?? '',
);

const team = computed(() =>
    employees.filter((e) => e.chantier === chantierName.value),
);

// RG12.1 : le chef de chantier saisit chaque jour présences et heures, depuis son téléphone.
const entries = reactive<Record<number, { present: boolean; hours: number }>>(
    {},
);
const entryOf = (id: number) => (entries[id] ??= { present: true, hours: 8 });

function step(id: number, delta: number) {
    const e = entryOf(id);
    e.hours = Math.min(14, Math.max(0, e.hours + delta));
}

// RG13.2 : un employé en congé ne peut pas être pointé.
const present = computed(() =>
    team.value.filter((e) => !e.onLeave && entryOf(e.id).present),
);
const totalHours = computed(() =>
    present.value.reduce((s, e) => s + entryOf(e.id).hours, 0),
);
// RG12.2 : coût = heures pointées x coût horaire (charges comprises), imputé en coût réel.
const totalCost = computed(() =>
    present.value.reduce((s, e) => s + entryOf(e.id).hours * e.hourlyCost, 0),
);

function submit() {
    toast.success(
        `Pointage enregistré : ${present.value.length} présent(s), ${totalHours.value} h (maquette).`,
    );
    router.visit('/main-oeuvre');
}

// Coût de la semaine par catégorie (CA12), calculé sur 5 jours de 8 h.
const weekCost = computed(() => {
    const byCategory = new Map<string, number>();

    for (const e of employees.filter(
        (x) => !x.onLeave && x.chantier !== 'Siège',
    )) {
        byCategory.set(
            e.category,
            (byCategory.get(e.category) ?? 0) + 40 * e.hourlyCost,
        );
    }

    return [...byCategory.entries()].map(([category, cost]) => ({
        category: category as keyof typeof categoryLabels,
        cost,
    }));
});

const field =
    'h-12 w-full rounded-lg border border-input bg-card px-3 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head title="Pointage" />

    <div class="mx-auto w-full max-w-2xl space-y-4 p-4 pb-32">
        <div>
            <h1 class="font-display text-xl font-semibold">Main-d’œuvre</h1>
            <p class="text-sm text-muted-foreground">{{ dateFr(TODAY) }}</p>
        </div>

        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'pointage', label: 'Pointage du jour' },
                { key: 'couts', label: 'Coût de la semaine' },
            ]"
        />

        <template v-if="tab === 'pointage'">
            <div class="grid gap-2">
                <label for="ch" class="text-sm font-medium">Chantier</label>
                <select id="ch" v-model="chantierId" :class="field">
                    <option
                        v-for="c in activeChantiers"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.code }} — {{ c.name }}
                    </option>
                </select>
            </div>

            <article
                v-for="e in team"
                :key="e.id"
                class="rounded-2xl border bg-card p-4 shadow-xs"
                :class="e.onLeave ? 'opacity-60' : ''"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ e.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ e.job }} · {{ categoryLabels[e.category] }}
                        </p>
                    </div>
                    <StatusBadge
                        v-if="e.onLeave"
                        label="En congé : pointage impossible"
                        tone="warning"
                    />
                    <label
                        v-else
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <input
                            v-model="entryOf(e.id).present"
                            type="checkbox"
                            class="size-6 accent-brand"
                            :aria-label="`Présent : ${e.name}`"
                        />
                        Présent
                    </label>
                </div>
                <div
                    v-if="!e.onLeave && entryOf(e.id).present"
                    class="mt-3 flex items-center gap-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-lg"
                        aria-label="Moins une heure"
                        @click="step(e.id, -1)"
                        ><Minus
                    /></Button>
                    <p
                        class="flex-1 text-center font-display text-3xl font-semibold tabular-nums"
                    >
                        {{ entryOf(e.id).hours }} h
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-lg"
                        aria-label="Plus une heure"
                        @click="step(e.id, 1)"
                        ><Plus
                    /></Button>
                </div>
            </article>
            <EmptyState
                v-if="!team.length"
                title="Aucun employé affecté à ce chantier"
            />

            <div
                class="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 p-3 backdrop-blur"
            >
                <div class="mx-auto flex max-w-2xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">
                            {{ present.length }} présent(s) · {{ totalHours }} h
                        </p>
                        <p
                            class="truncate font-display text-lg font-semibold tabular-nums"
                        >
                            {{ fcfa(totalCost) }}
                        </p>
                    </div>
                    <Button
                        size="lg"
                        class="bg-brand text-brand-foreground hover:bg-brand/90"
                        :disabled="!present.length"
                        @click="submit"
                        >Enregistrer le pointage</Button
                    >
                </div>
            </div>
        </template>

        <section
            v-else
            class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
        >
            <h2 class="font-display font-semibold">
                Semaine du 26/10 au 01/11/2026 — par catégorie
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="w in weekCost"
                    :key="w.category"
                    class="flex justify-between py-2.5"
                >
                    <span>{{ categoryLabels[w.category] }}</span>
                    <span class="font-medium tabular-nums">{{
                        fcfa(w.cost)
                    }}</span>
                </li>
                <li class="flex justify-between py-2.5 font-semibold">
                    <span>Total</span>
                    <span class="tabular-nums">{{
                        fcfa(weekCost.reduce((s, w) => s + w.cost, 0))
                    }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
