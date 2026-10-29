<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Administration/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { id: number; name: string };
type RoleOption = { name: string; label: string };
type UserForm = {
    id: number;
    name: string;
    email: string;
    agency_id: number | null;
    service_id: number | null;
    phone: string | null;
    is_active: boolean;
    roles: string[];
};

const props = defineProps<{
    user: UserForm | null;
    agencies: Option[];
    services: Option[];
    roles: RoleOption[];
}>();

const isEdit = computed(() => props.user !== null);
const title = computed(() =>
    isEdit.value ? 'Modifier l’utilisateur' : 'Nouvel utilisateur',
);
const formProps = computed(() =>
    props.user
        ? UserController.update.form(props.user.id)
        : UserController.store.form(),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Utilisateurs', href: '/administration/users' },
        ],
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
        <Heading
            :title="title"
            description="Un compte est désactivé, jamais supprimé, afin de conserver l’historique."
        />

        <Form
            v-bind="formProps"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nom complet</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user?.name"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="user?.email"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">
                    {{
                        isEdit
                            ? 'Nouveau mot de passe (laisser vide pour ne pas le changer)'
                            : 'Mot de passe initial'
                    }}
                </Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    :required="!isEdit"
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="agency_id">Agence</Label>
                    <select
                        id="agency_id"
                        name="agency_id"
                        :class="selectClass"
                        :value="user?.agency_id ?? ''"
                    >
                        <option value="">—</option>
                        <option
                            v-for="agency in agencies"
                            :key="agency.id"
                            :value="agency.id"
                        >
                            {{ agency.name }}
                        </option>
                    </select>
                    <InputError :message="errors.agency_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="service_id">Service</Label>
                    <select
                        id="service_id"
                        name="service_id"
                        :class="selectClass"
                        :value="user?.service_id ?? ''"
                    >
                        <option value="">—</option>
                        <option
                            v-for="service in services"
                            :key="service.id"
                            :value="service.id"
                        >
                            {{ service.name }}
                        </option>
                    </select>
                    <InputError :message="errors.service_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="phone">Téléphone</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    :default-value="user?.phone ?? ''"
                    autocomplete="off"
                />
                <InputError :message="errors.phone" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">Rôles</legend>
                <label
                    v-for="role in roles"
                    :key="role.name"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="roles[]"
                        :value="role.name"
                        :checked="user?.roles.includes(role.name)"
                        class="size-4 rounded border-input"
                    />
                    {{ role.label }}
                </label>
                <InputError :message="errors.roles" />
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0" />
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    :checked="user?.is_active ?? true"
                    class="size-4 rounded border-input"
                />
                Compte actif
            </label>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    Enregistrer
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="UserController.index.url()">Annuler</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
