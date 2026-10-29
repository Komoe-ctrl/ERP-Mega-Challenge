<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Sélecteur de profil pour la démo : visible uniquement en local
 * (le serveur ne partage la prop `demo` que si APP_ENV=local).
 */
const page = usePage();
const demo = computed(() => page.props.demo);

function change(event: Event) {
    const role = (event.target as HTMLSelectElement).value;

    router.post('/demo/role', { role }, { preserveScroll: true });
}
</script>

<template>
    <div
        v-if="demo"
        class="rounded-lg border border-sidebar-border p-2 group-data-[collapsible=icon]:hidden"
    >
        <label
            for="demo-role"
            class="mb-1 block text-[11px] font-medium text-sidebar-foreground/70"
        >
            Démo — profil affiché
        </label>
        <select
            id="demo-role"
            class="h-8 w-full rounded-md border border-sidebar-border bg-sidebar-accent px-2 text-xs text-sidebar-accent-foreground outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
            :value="demo.current ?? ''"
            @change="change"
        >
            <option
                v-for="role in demo.roles"
                :key="role.value"
                :value="role.value"
            >
                {{ role.label }}
            </option>
        </select>
    </div>
</template>
