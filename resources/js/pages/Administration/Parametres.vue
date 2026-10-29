<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fiscalYears, settingGroups } from '@/mocks/transverse';
import { dateFr } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Paramètres', href: '/administration/parametres' },
        ],
    },
});

const values = reactive<Record<string, string>>(
    Object.fromEntries(
        settingGroups.flatMap((g) => g.items.map((i) => [i.key, i.value])),
    ),
);
</script>

<template>
    <Head title="Paramètres" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Paramètres"
            description="Seuils, taxes et numérotation, modifiables sans développement."
        />
        <AdminNav />

        <form
            class="space-y-4"
            @submit.prevent="
                toast.success('Paramètres enregistrés (maquette).')
            "
        >
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
                            <Input :id="i.key" v-model="values[i.key]" />
                            <span
                                v-if="i.unit"
                                class="text-sm whitespace-nowrap text-muted-foreground"
                                >{{ i.unit }}</span
                            >
                        </div>
                        <p
                            v-if="i.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ i.description }}
                        </p>
                    </div>
                </div>
            </section>
            <Button type="submit">Enregistrer les paramètres</Button>
        </form>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Exercices comptables
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="f in fiscalYears"
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
                        <span
                            :class="
                                f.status === 'closed'
                                    ? 'text-muted-foreground'
                                    : 'text-success'
                            "
                        >
                            {{
                                f.status === 'closed'
                                    ? 'Clôturé (lecture seule)'
                                    : 'Ouvert'
                            }}
                        </span>
                        <Button
                            v-if="f.status === 'open'"
                            variant="outline"
                            size="sm"
                            @click="toast.success('Clôture (maquette).')"
                        >
                            Clôturer
                        </Button>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
