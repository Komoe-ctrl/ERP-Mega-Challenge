import type { FieldDef, FieldValue, FieldValues } from '@/types/forms';

/** Un champ est vide s'il n'a pas de valeur utile (case décochée comprise). */
export function isEmpty(value: FieldValue | undefined): boolean {
    return (
        value === null ||
        value === undefined ||
        value === false ||
        (typeof value === 'string' && value.trim() === '')
    );
}

/**
 * Valide un champ et renvoie le premier message d'erreur en français, ou null.
 * Les messages sont affichés tels quels sous le champ.
 */
export function validateField(
    field: FieldDef,
    value: FieldValue | undefined,
    values: FieldValues,
): string | null {
    if (isEmpty(value)) {
        return field.required ? 'Ce champ est obligatoire.' : null;
    }

    const type = field.type ?? 'text';

    if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value))) {
        return 'Adresse e-mail invalide.';
    }

    if (type === 'number') {
        const n = Number(value);

        if (!Number.isFinite(n)) {
            return 'Saisissez un nombre valide.';
        }

        if (field.min !== undefined && n < field.min) {
            return `La valeur minimale est ${field.min}.`;
        }

        if (field.max !== undefined && n > field.max) {
            return `La valeur maximale est ${field.max}.`;
        }
    }

    if (type === 'date' && Number.isNaN(Date.parse(String(value)))) {
        return 'Date invalide.';
    }

    if (typeof value === 'string') {
        if (
            field.minLength !== undefined &&
            value.trim().length < field.minLength
        ) {
            return `Au moins ${field.minLength} caractères.`;
        }

        if (field.maxLength !== undefined && value.length > field.maxLength) {
            return `Au plus ${field.maxLength} caractères.`;
        }

        if (field.pattern && !field.pattern.test(value)) {
            return field.patternMessage ?? 'Format invalide.';
        }
    }

    return field.validate?.(value ?? null, values) ?? null;
}

/** Valide tout un formulaire : renvoie les erreurs par nom de champ. */
export function validateAll(
    fields: FieldDef[],
    values: FieldValues,
): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const field of fields) {
        const message = validateField(field, values[field.name], values);

        if (message) {
            errors[field.name] = message;
        }
    }

    return errors;
}
