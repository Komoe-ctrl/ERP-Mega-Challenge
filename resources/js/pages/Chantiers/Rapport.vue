<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { toast } from 'vue-sonner';
import DailyReportController from '@/actions/App/Http/Controllers/Chantiers/DailyReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateFr } from '@/lib/format';

const props = defineProps<{
    chantier: { id: number; code: string; name: string };
    today: string;
    alreadySent: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

// RG7.9 : travaux, effectif, matériaux reçus et consommés, incidents, photos.
const form = useForm({
    works: '',
    headcount: 0,
    materials_received: '',
    materials_used: '',
    incidents: '',
    photos: [] as File[],
});

function submit() {
    form.post(DailyReportController.store.url(props.chantier.id), {
        forceFormData: true,
        onError: (errors) => toast.error(Object.values(errors)[0]),
    });
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
                {{ chantier.code }} · {{ chantier.name }} ·
                {{ dateFr(today) }}
            </p>
        </div>

        <p
            v-if="alreadySent"
            class="rounded-lg bg-warning-soft p-3 text-sm text-warning"
        >
            Un rapport a déjà été envoyé aujourd’hui pour ce chantier : un seul
            rapport est accepté par jour.
        </p>

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
                <p v-if="form.errors.works" class="text-sm text-destructive">
                    {{ form.errors.works }}
                </p>
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
                <p
                    v-if="form.errors.headcount"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.headcount }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="received">Matériaux reçus</Label>
                <textarea
                    id="received"
                    v-model="form.materials_received"
                    rows="2"
                    :class="fieldClass"
                />
            </div>
            <div class="grid gap-2">
                <Label for="used">Matériaux consommés</Label>
                <textarea
                    id="used"
                    v-model="form.materials_used"
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
                {{
                    form.photos.length
                        ? `${form.photos.length} photo(s)`
                        : 'Ajouter des photos'
                }}
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    class="sr-only"
                    @change="
                        form.photos = Array.from(
                            ($event.target as HTMLInputElement).files ?? [],
                        )
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
                    @click="router.visit(`/chantiers/${chantier.id}`)"
                >
                    Annuler
                </Button>
                <Button
                    type="submit"
                    size="lg"
                    class="flex-[2] bg-brand text-brand-foreground hover:bg-brand/90"
                    :disabled="form.processing || alreadySent"
                >
                    Envoyer le rapport
                </Button>
            </div>
        </div>
    </form>
</template>
