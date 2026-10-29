<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateFr, TODAY } from '@/lib/format';
import { chantiers } from '@/mocks/chantiers';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const chantier = computed(
    () => chantiers.find((c) => c.id === props.id) ?? chantiers[0],
);

// RG7.9 : travaux, effectif, matériaux reçus et consommés, incidents, photos.
const form = reactive({
    works: '',
    headcount: 0,
    received: '',
    used: '',
    incidents: '',
});
const photos = ref(0);

function submit() {
    toast.success('Rapport journalier enregistré (maquette).');
    router.visit(`/chantiers/${chantier.value.id}`);
}

const fieldClass =
    'w-full rounded-lg border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head title="Rapport journalier" />

    <form
        class="mx-auto w-full max-w-2xl space-y-4 p-4 pb-28"
        @submit.prevent="submit"
    >
        <div>
            <h1 class="font-display text-xl font-semibold">
                Rapport journalier
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ chantier.code }} · {{ chantier.name }} · {{ dateFr(TODAY) }}
            </p>
        </div>

        <div class="space-y-4 rounded-2xl border bg-card p-4 shadow-xs">
            <div class="grid gap-2">
                <Label for="works">Travaux réalisés</Label>
                <textarea
                    id="works"
                    v-model="form.works"
                    rows="4"
                    required
                    :class="fieldClass"
                />
            </div>
            <div class="grid gap-2">
                <Label for="headcount">Effectif présent</Label>
                <Input
                    id="headcount"
                    v-model.number="form.headcount"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="h-12 text-base"
                />
            </div>
            <div class="grid gap-2">
                <Label for="received">Matériaux reçus</Label>
                <textarea
                    id="received"
                    v-model="form.received"
                    rows="2"
                    :class="fieldClass"
                />
            </div>
            <div class="grid gap-2">
                <Label for="used">Matériaux consommés</Label>
                <textarea
                    id="used"
                    v-model="form.used"
                    rows="2"
                    :class="fieldClass"
                />
            </div>
            <div class="grid gap-2">
                <Label for="incidents">Incidents</Label>
                <textarea
                    id="incidents"
                    v-model="form.incidents"
                    rows="2"
                    placeholder="Aucun"
                    :class="fieldClass"
                />
            </div>
            <label
                class="flex h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed text-sm font-medium hover:bg-accent"
            >
                <Camera class="size-5" />
                {{ photos ? `${photos} photo(s)` : 'Ajouter des photos' }}
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    class="sr-only"
                    @change="
                        photos +=
                            ($event.target as HTMLInputElement).files?.length ??
                            0
                    "
                />
            </label>
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 p-3 backdrop-blur"
        >
            <div class="mx-auto flex max-w-2xl gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    class="flex-1"
                    as-child
                >
                    <Link :href="`/chantiers/${chantier.id}`">Annuler</Link>
                </Button>
                <Button
                    type="submit"
                    size="lg"
                    class="flex-[2] bg-brand text-brand-foreground hover:bg-brand/90"
                >
                    Envoyer le rapport
                </Button>
            </div>
        </div>
    </form>
</template>
