<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Administration/RoleController';
import PageHeader from '@/components/erp/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RoleForm = {
    id: number;
    name: string;
    label: string;
    is_profile: boolean;
    permissions: string[];
};

const props = defineProps<{
    role: RoleForm | null;
    actions: { value: string; label: string }[];
    modules: { value: string; label: string; version: number }[];
}>();

const isEdit = computed(() => props.role !== null);
const title = computed(() =>
    props.role ? `Droits : ${props.role.label}` : 'Nouveau rôle',
);
const formProps = computed(() =>
    props.role
        ? RoleController.update.form(props.role.id)
        : RoleController.store.form(),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Rôles', href: '/administration/roles' },
        ],
    },
});
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
        <PageHeader
            :title="title"
            description="Les droits sont définis par module et par action. Ils s’appliquent immédiatement, sans développement."
        />

        <Form
            v-bind="formProps"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nom du rôle</Label>
                <input
                    v-if="role?.is_profile"
                    type="hidden"
                    name="name"
                    :value="role.name"
                />
                <Input
                    id="name"
                    :name="role?.is_profile ? undefined : 'name'"
                    :default-value="role?.is_profile ? role.label : role?.name"
                    :disabled="role?.is_profile"
                    required
                />
                <p
                    v-if="role?.is_profile"
                    class="text-xs text-muted-foreground"
                >
                    Profil du cahier des charges : le nom ne peut pas être
                    modifié.
                </p>
                <InputError :message="errors.name" />
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="p-3 font-medium">Module</th>
                            <th
                                v-for="action in actions"
                                :key="action.value"
                                class="p-3 text-center font-medium"
                            >
                                {{ action.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="module in modules"
                            :key="module.value"
                            class="border-b last:border-0"
                        >
                            <th class="p-3 text-left font-normal">
                                {{ module.label }}
                                <span class="ml-1 text-xs text-muted-foreground"
                                    >V{{ module.version }}</span
                                >
                            </th>
                            <td
                                v-for="action in actions"
                                :key="action.value"
                                class="p-3 text-center"
                            >
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    :value="`${module.value}.${action.value}`"
                                    :checked="
                                        role?.permissions.includes(
                                            `${module.value}.${action.value}`,
                                        )
                                    "
                                    :aria-label="`${module.label} : ${action.label}`"
                                    class="size-4 rounded border-input"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <InputError :message="errors.permissions" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ isEdit ? 'Enregistrer' : 'Créer le rôle' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="RoleController.index.url()">Annuler</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
