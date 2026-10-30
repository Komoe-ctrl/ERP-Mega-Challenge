<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import SettingController from '@/actions/App/Http/Controllers/Administration/SettingController';
import AdminNav from '@/components/erp/AdminNav.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr } from '@/lib/format';
import type { SettingGroup } from '@/types/erp';

type FiscalYearRow = {
    id: number;
    label: string;
    startsOn: string;
    endsOn: string;
    status: 'open' | 'closed';
};

const props = defineProps<{
    settingGroups: SettingGroup[];
    fiscalYears: FiscalYearRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Paramètres', href: '/administration/parametres' },
        ],
    },
});

const { can } = usePermissions();
const canEdit = computed(() => can('administration.update'));

const values = reactive<Record<string, string>>(
    Object.fromEntries(
        props.settingGroups.flatMap((g) =>
            g.items.map((i) => [i.key, i.value]),
        ),
    ),
);
const errors = ref<Record<string, string>>({});

// Le client contrôle la saisie pour le confort ; le serveur refait toute la validation.
function check(key: string, unit: string | undefined, value: string): string {
    if (key.startsWith('numbering.')) {
        return /{SEQ:d+}/.test(value)
            ? ''
            : 'Le format doit contenir un compteur, ex. {SEQ:5}.';
    }

    if (!unit && key !== 'validation.consultation_min_suppliers') {
        return '';
    }

    if (!/^d+([.,]d+)?$/.test(value.trim())) {
        return 'Saisissez un nombre positif.';
    }

    const n = Number(value.replace(',', '.'));

    if (unit === '%' && (n < 0 || n > 100)) {
        return 'Un pourcentage doit être compris entre 0 et 100.';
    }

    if (key === 'validation.consultation_min_suppliers' && n < 3) {
        return 'Au moins trois fournisseurs (RG8.11).';
    }

    if (
        ['FCFA', 'jours', 'points', 'Mo'].includes(unit ?? '') &&
        !Number.isInteger(n)
    ) {
        return 'Saisissez un nombre entier.';
    }

    return '';
}

function save() {
    errors.value = {};

    for (const g of props.settingGroups) {
        for (const i of g.items) {
            const message = check(i.key, i.unit, values[i.key]);

            if (message) {
                errors.value[i.key] = message;
            }
        }
    }

    if (Object.keys(errors.value).length) {
        toast.error('Corrigez les valeurs signalées avant d’enregistrer.');

        return;
    }

    router.put(
        SettingController.update.url(),
        { values: { ...values } },
        {
            preserveScroll: true,
            onError: (serverErrors) => {
                // Les points des clés sont encodés en « __ » dans les erreurs.
                for (const [field, message] of Object.entries(serverErrors)) {
                    errors.value[
                        field.replace('values.', '').replaceAll('__', '.')
                    ] = message;
                }

                toast.error(
                    'Corrigez les valeurs signalées avant d’enregistrer.',
                );
            },
        },
    );
}

const years = computed(() => props.fiscalYears);
const closing = ref<number | null>(null);
const closeOpen = computed({
    get: () => closing.value !== null,
    set: (v) => {
        if (!v) closing.value = null;
    },
});

function close(reason: string) {
    if (closing.value !== null) {
        router.patch(
            SettingController.closeFiscalYear.url(closing.value),
            { reason },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="Paramètres" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Paramètres"
            description="Seuils, taxes et numérotation, modifiables sans développement."
        />
        <AdminNav />

        <form class="space-y-4" novalidate @submit.prevent="save">
            <section
                v-for="g in settingGroups"
                :key="g.group"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <h2 class="mb-3 font-display font-semibold">{{ g.group }}</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="i in g.items" :key="i.key" class="grid gap-1.5">
                        <Label :for="i.key">{{ i.label }}</Label>
                        <div class="flex items-center gap-2">
                            <Input
                                :id="i.key"
                                v-model="values[i.key]"
                                :disabled="!canEdit"
                                :aria-invalid="!!errors[i.key]"
                            />
                            <span
                                v-if="i.unit"
                                class="text-sm whitespace-nowrap text-muted-foreground"
                                >{{ i.unit }}</span
                            >
                        </div>
                        <p
                            v-if="errors[i.key]"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ errors[i.key] }}
                        </p>
                        <p
                            v-else-if="i.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ i.description }}
                        </p>
                    </div>
                </div>
            </section>
            <Button v-if="canEdit" type="submit"
                >Enregistrer les paramètres</Button
            >
        </form>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Exercices comptables
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="f in years"
                    :key="f.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span>
                        <span class="font-medium">Exercice {{ f.label }}</span>
                        <span class="block text-xs text-muted-foreground"
                            >{{ dateFr(f.startsOn) }} →
                            {{ dateFr(f.endsOn) }}</span
                        >
                    </span>
                    <span class="flex items-center gap-2">
                        <StatusBadge
                            :label="
                                f.status === 'closed'
                                    ? 'Clôturé (lecture seule)'
                                    : 'Ouvert'
                            "
                            :tone="
                                f.status === 'closed' ? 'neutral' : 'success'
                            "
                        />
                        <Button
                            v-if="
                                f.status === 'open' &&
                                can('administration.validate')
                            "
                            variant="outline"
                            size="sm"
                            @click="closing = f.id"
                            >Clôturer</Button
                        >
                    </span>
                </li>
            </ul>
        </section>

        <ConfirmDialog
            v-model:open="closeOpen"
            title="Clôturer l’exercice"
            description="Un exercice clôturé passe en lecture seule : plus aucune écriture n’est possible. Cette action est irréversible."
            confirm-label="Clôturer"
            require-reason
            reason-label="Motif de clôture"
            destructive
            @confirm="close"
        />
    </div>
</template>
