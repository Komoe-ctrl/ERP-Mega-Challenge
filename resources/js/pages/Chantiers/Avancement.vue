<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Camera, Minus, Plus } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr } from '@/lib/format';
import { taskHealth, theoreticalProgress } from '@/lib/planning';
import { chantiers, tasksOf } from '@/mocks/chantiers';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const chantier = computed(
    () => chantiers.find((c) => c.id === props.id) ?? chantiers[0],
);
const editable = computed(() =>
    tasksOf(chantier.value.id).filter((t) => !t.milestone),
);

// RG7.4 : avancement en %, commentaire et photos, par tâche.
const entries = reactive<
    Record<number, { progress: number; comment: string; photos: number }>
>(
    Object.fromEntries(
        editable.value.map((t) => [
            t.id,
            { progress: t.progress, comment: '', photos: 0 },
        ]),
    ),
);

function step(taskId: number, delta: number) {
    const e = entries[taskId];
    e.progress = Math.min(100, Math.max(0, e.progress + delta));
}

function addPhotos(taskId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    entries[taskId].photos += input.files?.length ?? 0;
}

const changed = computed(() =>
    editable.value.filter(
        (t) =>
            entries[t.id].progress !== t.progress ||
            entries[t.id].comment ||
            entries[t.id].photos,
    ),
);

function submit() {
    toast.success(
        `${changed.value.length} tâche(s) mise(s) à jour (maquette).`,
    );
    router.visit(`/chantiers/${chantier.value.id}`);
}
</script>

<template>
    <Head title="Saisie d’avancement" />

    <div class="mx-auto w-full max-w-2xl space-y-4 p-4 pb-28">
        <div>
            <h1 class="font-display text-xl font-semibold">Avancement</h1>
            <p class="text-sm text-muted-foreground">
                {{ chantier.code }} · {{ chantier.name }}
            </p>
        </div>

        <article
            v-for="t in editable"
            :key="t.id"
            class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-medium">{{ t.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ dateFr(t.start) }} → {{ dateFr(t.end) }} · théorique
                        {{ theoreticalProgress(t) }} %
                    </p>
                </div>
                <StatusBadge
                    kind="task"
                    :status="
                        taskHealth({ ...t, progress: entries[t.id].progress })
                    "
                />
            </div>

            <div class="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="icon-lg"
                    aria-label="Moins 5 points"
                    @click="step(t.id, -5)"
                >
                    <Minus />
                </Button>
                <div class="flex-1 text-center">
                    <p class="font-display text-3xl font-semibold tabular-nums">
                        {{ entries[t.id].progress }} %
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="icon-lg"
                    aria-label="Plus 5 points"
                    @click="step(t.id, 5)"
                >
                    <Plus />
                </Button>
            </div>
            <input
                v-model.number="entries[t.id].progress"
                type="range"
                min="0"
                max="100"
                step="5"
                class="h-8 w-full accent-brand"
                :aria-label="`Avancement de ${t.name}`"
            />
            <ProgressBar
                :value="entries[t.id].progress"
                :marker="theoreticalProgress(t)"
                :show-value="false"
            />

            <textarea
                v-model="entries[t.id].comment"
                rows="2"
                placeholder="Commentaire (facultatif)"
                class="w-full rounded-lg border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40"
            />

            <label
                class="flex h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed text-sm font-medium hover:bg-accent"
            >
                <Camera class="size-5" />
                {{
                    entries[t.id].photos
                        ? `${entries[t.id].photos} photo(s) ajoutée(s)`
                        : 'Ajouter des photos'
                }}
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    class="sr-only"
                    @change="addPhotos(t.id, $event)"
                />
            </label>
        </article>

        <!-- Barre d'action collée en bas : pouce accessible sur smartphone -->
        <div
            class="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 p-3 backdrop-blur"
        >
            <div class="mx-auto flex max-w-2xl gap-2">
                <Button variant="outline" size="lg" class="flex-1" as-child>
                    <Link :href="`/chantiers/${chantier.id}`">Annuler</Link>
                </Button>
                <Button
                    size="lg"
                    class="flex-[2] bg-brand text-brand-foreground hover:bg-brand/90"
                    :disabled="!changed.length"
                    @click="submit"
                >
                    Enregistrer ({{ changed.length }})
                </Button>
            </div>
        </div>
    </div>
</template>
