<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLegacySession } from '@/composables/useLegacySession';
import { dateTimeFr } from '@/lib/format';
import { services } from '@/mocks/legacy';

/**
 * Identification de l'application actuelle : service → poste (titre) → mot de passe.
 * En maquette, elle choisit seulement le poste affiché ; le compte nominatif reste
 * celui de la connexion (chaque compte est personnel, le titre n'est qu'un poste).
 */
const { session } = useLegacySession();

const serviceId = ref(session.value.serviceId);
const title = ref(session.value.title);
const password = ref('');

const service = computed(
    () => services.find((s) => s.id === serviceId.value) ?? services[0],
);

function pickService() {
    title.value = service.value.titles[0];
}

function confirm() {
    if (!password.value) {
        toast.error('Saisissez votre mot de passe.');

        return;
    }

    session.value.serviceId = serviceId.value;
    session.value.title = title.value;
    toast.success(`Poste « ${title.value} » activé.`);
    router.visit('/dashboard');
}

function clockIn() {
    session.value.arrivedAt = '2026-10-29T08:02:00';
    toast.success('Arrivée enregistrée.');
}
</script>

<template>
    <div class="mx-auto w-full max-w-lg space-y-4 p-4">
        <PageHeader
            title="Identification"
            description="Choisissez votre service et votre poste de travail."
        />
        <form
            class="space-y-4 rounded-xl border bg-card p-5 shadow-xs"
            @submit.prevent="confirm"
        >
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Service</span>
                <select
                    v-model="serviceId"
                    class="h-9 rounded-md border bg-background px-2"
                    @change="pickService"
                >
                    <option v-for="s in services" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Identifiant utilisateur (titre)</span>
                <select
                    v-model="title"
                    class="h-9 rounded-md border bg-background px-2"
                >
                    <option v-for="t in service.titles" :key="t" :value="t">
                        {{ t }}
                    </option>
                </select>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    class="h-9 rounded-md border bg-background px-2"
                />
            </label>
            <div class="flex flex-wrap gap-2">
                <Button type="submit">Valider</Button>
                <Button type="button" variant="outline" @click="clockIn"
                    >Pointage</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    @click="router.visit('/dashboard')"
                    >Fermer</Button
                >
            </div>
            <p
                v-if="session.arrivedAt"
                class="text-sm text-muted-foreground"
                role="status"
            >
                Arrivée pointée le {{ dateTimeFr(session.arrivedAt) }}.
            </p>
        </form>
    </div>
</template>
