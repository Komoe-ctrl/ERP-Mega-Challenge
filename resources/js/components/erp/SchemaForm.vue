<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { validateAll, validateField } from '@/lib/validation';
import type { FieldDef, FieldValue, FieldValues } from '@/types/forms';

/**
 * Formulaire décrit par un schéma de champs : rendu, validation côté client
 * avec messages en français, boutons Enregistrer / Annuler.
 * Mobile : champs hauts (44 px) et texte 16 px (pas de zoom automatique iOS).
 */
const props = withDefaults(
    defineProps<{
        fields: FieldDef[];
        initial?: FieldValues;
        submitLabel?: string;
        cancelLabel?: string;
        showCancel?: boolean;
    }>(),
    {
        initial: () => ({}),
        submitLabel: 'Enregistrer',
        cancelLabel: 'Annuler',
        showCancel: true,
    },
);

const emit = defineEmits<{ submit: [values: FieldValues]; cancel: [] }>();

function blank(field: FieldDef): FieldValue {
    if (field.type === 'checkbox') {
        return false;
    }

    return field.type === 'number' ? null : '';
}

const values = reactive<FieldValues>(
    Object.fromEntries(
        props.fields.map((f) => [f.name, props.initial[f.name] ?? blank(f)]),
    ),
);
const errors = ref<Record<string, string>>({});
const touched = reactive<Record<string, boolean>>({});
const submitted = ref(false);

function onBlur(field: FieldDef) {
    touched[field.name] = true;
    const message = validateField(field, values[field.name], values);
    errors.value = { ...errors.value, [field.name]: message ?? '' };
}

function onInput(field: FieldDef) {
    if (touched[field.name] || submitted.value) {
        onBlur(field);
    }
}

const errorCount = computed(
    () => Object.values(errors.value).filter(Boolean).length,
);

function submit() {
    submitted.value = true;
    errors.value = validateAll(props.fields, values);

    if (Object.keys(errors.value).length === 0) {
        emit('submit', { ...values });
    }
}

const control =
    'h-11 w-full rounded-md border border-input bg-card px-3 text-base shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40 aria-[invalid=true]:border-destructive sm:h-9 sm:text-sm';
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="f in fields"
                :key="f.name"
                class="grid content-start gap-1.5"
                :class="f.wide || f.type === 'textarea' ? 'sm:col-span-2' : ''"
            >
                <Label v-if="f.type !== 'checkbox'" :for="`f-${f.name}`">
                    {{ f.label
                    }}<span
                        v-if="f.required"
                        class="text-destructive"
                        aria-hidden="true"
                    >
                        *</span
                    >
                </Label>

                <select
                    v-if="f.type === 'select'"
                    :id="`f-${f.name}`"
                    v-model="values[f.name]"
                    :class="control"
                    :aria-invalid="!!errors[f.name]"
                    @change="onInput(f)"
                    @blur="onBlur(f)"
                >
                    <option value="" disabled>Choisir…</option>
                    <option
                        v-for="o in f.options"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>

                <textarea
                    v-else-if="f.type === 'textarea'"
                    :id="`f-${f.name}`"
                    v-model="values[f.name] as string"
                    rows="3"
                    :placeholder="f.placeholder"
                    :class="control + ' h-auto py-2'"
                    :aria-invalid="!!errors[f.name]"
                    @input="onInput(f)"
                    @blur="onBlur(f)"
                />

                <label
                    v-else-if="f.type === 'checkbox'"
                    class="flex min-h-11 items-center gap-2 text-sm sm:min-h-9"
                >
                    <input
                        :id="`f-${f.name}`"
                        v-model="values[f.name]"
                        type="checkbox"
                        class="size-5 accent-brand"
                        @change="onInput(f)"
                    />
                    {{ f.label }}
                </label>

                <input
                    v-else-if="f.type === 'file'"
                    :id="`f-${f.name}`"
                    type="file"
                    :class="control + ' py-2'"
                    :aria-invalid="!!errors[f.name]"
                    @change="
                        values[f.name] =
                            ($event.target as HTMLInputElement).files?.[0]
                                ?.name ?? '';
                        onInput(f);
                    "
                />

                <div v-else class="flex items-center gap-2">
                    <Input
                        :id="`f-${f.name}`"
                        v-model="values[f.name] as string | number"
                        :type="f.type ?? 'text'"
                        :min="f.min"
                        :max="f.max"
                        :step="f.step"
                        :inputmode="f.type === 'number' ? 'decimal' : undefined"
                        :placeholder="f.placeholder"
                        :aria-invalid="!!errors[f.name]"
                        class="h-11 text-base sm:h-9 sm:text-sm"
                        @update:model-value="onInput(f)"
                        @blur="onBlur(f)"
                    />
                    <span
                        v-if="f.unit"
                        class="text-sm whitespace-nowrap text-muted-foreground"
                        >{{ f.unit }}</span
                    >
                </div>

                <p
                    v-if="errors[f.name]"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ errors[f.name] }}
                </p>
                <p v-else-if="f.hint" class="text-xs text-muted-foreground">
                    {{ f.hint }}
                </p>
            </div>
        </div>

        <p
            v-if="submitted && errorCount"
            class="rounded-lg bg-danger-soft p-2 text-sm text-danger"
            role="alert"
        >
            {{ errorCount }} champ(s) à corriger avant d’enregistrer.
        </p>

        <div class="flex flex-wrap gap-2">
            <slot name="actions" />
            <Button type="submit">{{ submitLabel }}</Button>
            <Button
                v-if="showCancel"
                type="button"
                variant="ghost"
                @click="emit('cancel')"
                >{{ cancelLabel }}</Button
            >
        </div>
    </form>
</template>
