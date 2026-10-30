<script setup lang="ts">
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { defaultWorkflow, services } from '@/mocks/legacy';

const steps = ref(defaultWorkflow.map((s) => ({ ...s })));
const titles = services.flatMap((s) => s.titles);

function save() {
    toast.success('Circuit de validation enregistré (maquette).');
}

function move(i: number, d: -1 | 1) {
    const j = i + d;

    if (j < 1 || j >= steps.value.length) {
        return;
    }

    [steps.value[i], steps.value[j]] = [steps.value[j], steps.value[i]];
}
</script>

<template>
    <div class="space-y-4 p-4">
        <PageHeader
            title="Étapes de validation"
            description="Paramétrez le circuit des pièces : saisie, validation du chef de service, approbation du Directeur."
        >
            <Button size="sm" @click="save">Enregistrer</Button>
        </PageHeader>
        <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[40rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Ordre</th>
                        <th class="px-3 py-2">Étape</th>
                        <th class="px-3 py-2">Poste habilité</th>
                        <th class="px-3 py-2">Bouton</th>
                        <th class="px-3 py-2">Active</th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(s, i) in steps"
                        :key="s.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 tabular-nums">{{ i + 1 }}</td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.label"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Libellé de l’étape ${i + 1}`"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <select
                                v-model="s.title"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Poste de l’étape ${i + 1}`"
                            >
                                <option v-for="t in titles" :key="t" :value="t">
                                    {{ t }}
                                </option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.button"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Bouton de l’étape ${i + 1}`"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.enabled"
                                type="checkbox"
                                :disabled="i === 0"
                                :aria-label="`Étape ${i + 1} active`"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                :disabled="i < 2"
                                aria-label="Monter"
                                @click="move(i, -1)"
                                >↑</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                :disabled="i === 0 || i === steps.length - 1"
                                aria-label="Descendre"
                                @click="move(i, 1)"
                                >↓</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
