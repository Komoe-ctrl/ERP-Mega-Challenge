<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Camera, Minus, Plus } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import ProgressController from '@/actions/App/Http/Controllers/Chantiers/ProgressController';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr } from '@/lib/format';
import type { Task } from '@/types/erp';

const props = defineProps<{
    chantier: { id: number; code: string; name: string };
    tasks: Task[];
    thresholds: { risk: number; staleDays: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

// RG7.4 : avancement en %, commentaire et photos, par tâche.
type Entry = { progress: number; comment: string; files: File[] };
const entries = reactive<Record<number, Entry>>(
    Object.fromEntries(
        props.tasks.map((t) => [
            t.id,
            { progress: t.progress, comment: '', files: [] },
        ]),
    ),
);

function step(taskId: number, delta: number) {
    const e = entries[taskId];
    e.progress = Math.min(100, Math.max(0, e.progress + delta));
}

function addPhotos(taskId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    entries[taskId].files = Array.from(input.files ?? []);
}

const changed = computed(() =>
    props.tasks.filter(
        (t) =>
            entries[t.id].progress !== t.progress ||
            entries[t.id].comment ||
            entries[t.id].files.length,
    ),
);

// Le serveur recalcule l'état des tâches (retard, risque, non renseignée) à l'enregistrement.
function submit() {
    router.post(
        ProgressController.store.url(props.chantier.id),
        {
            entries: changed.value.map((t) => ({
                task_id: t.id,
                progress: entries[t.id].progress,
                comment: entries[t.id].comment || null,
                photos: entries[t.id].files,
            })),
        },
        {
            forceFormData: true,
            onError: (errors) => toast.error(Object.values(errors)[0]),
        },
    );
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
            v-for="t in tasks"
            :key="t.id"
            class="space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-medium">{{ t.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ dateFr(t.start) }} → {{ dateFr(t.end) }} · théorique
                        {{ t.theoretical }} %
                    </p>
                </div>
                <StatusBadge v-if="t.health" kind="task" :status="t.health" />
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
                :marker="t.theoretical"
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
                    entries[t.id].files.length
                        ? `${entries[t.id].files.length} photo(s) ajoutée(s)`
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

        <p v-if="!tasks.length" class="text-sm text-muted-foreground">
            Aucune tâche à renseigner : le planning de ce chantier est vide.
        </p>

        <!-- Barre d'action collée en bas : pouce accessible sur smartphone -->
        <div
            class="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 p-3 backdrop-blur"
        >
            <div class="mx-auto flex max-w-2xl gap-2">
                <Button
                    variant="outline"
                    size="lg"
                    class="flex-1"
                    @click="router.visit(`/chantiers/${chantier.id}`)"
                >
                    Annuler
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
