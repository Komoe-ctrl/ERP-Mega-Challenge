/** Description d'un champ de formulaire (SchemaForm / FormDialog). */

export type FieldType =
    | 'text'
    | 'email'
    | 'tel'
    | 'number'
    | 'date'
    | 'select'
    | 'textarea'
    | 'checkbox'
    | 'file';

export type FieldOption = { value: string | number; label: string };

export type FieldValue = string | number | boolean | null;

export type FieldValues = Record<string, FieldValue>;

export type FieldDef = {
    name: string;
    label: string;
    type?: FieldType;
    required?: boolean;
    options?: FieldOption[];
    min?: number;
    max?: number;
    step?: number;
    minLength?: number;
    maxLength?: number;
    pattern?: RegExp;
    patternMessage?: string;
    placeholder?: string;
    hint?: string;
    unit?: string;
    /** Occupe toute la largeur (2 colonnes). */
    wide?: boolean;
    /** Validation métier supplémentaire : renvoie un message d'erreur ou null. */
    validate?: (value: FieldValue, values: FieldValues) => string | null;
};
