import { describe, expect, it } from 'vitest';
import { isEmpty, validateAll, validateField } from '@/lib/validation';
import type { FieldDef } from '@/types/forms';

const field = (over: Partial<FieldDef>): FieldDef => ({
    name: 'x',
    label: 'X',
    ...over,
});

describe('validation des champs (messages en français)', () => {
    it('signale un champ obligatoire vide', () => {
        expect(validateField(field({ required: true }), '', {})).toBe(
            'Ce champ est obligatoire.',
        );
        expect(validateField(field({ required: true }), '   ', {})).toBe(
            'Ce champ est obligatoire.',
        );
        expect(
            validateField(field({ required: true, type: 'number' }), null, {}),
        ).toBe('Ce champ est obligatoire.');
    });

    it('accepte un champ facultatif vide', () => {
        expect(validateField(field({}), '', {})).toBeNull();
    });

    it('valide une adresse e-mail', () => {
        expect(validateField(field({ type: 'email' }), 'pas-un-mail', {})).toBe(
            'Adresse e-mail invalide.',
        );
        expect(
            validateField(field({ type: 'email' }), 'a.kone@example.com', {}),
        ).toBeNull();
    });

    it('contrôle les bornes d’un nombre', () => {
        const f = field({ type: 'number', min: 1, max: 100 });

        expect(validateField(f, 0, {})).toBe('La valeur minimale est 1.');
        expect(validateField(f, 101, {})).toBe('La valeur maximale est 100.');
        expect(validateField(f, 50, {})).toBeNull();
        expect(validateField(f, 'abc', {})).toBe('Saisissez un nombre valide.');
    });

    it('contrôle la longueur et le format', () => {
        expect(validateField(field({ minLength: 5 }), 'abc', {})).toBe(
            'Au moins 5 caractères.',
        );
        expect(
            validateField(
                field({
                    pattern: /^CH-\d{3}$/,
                    patternMessage: 'Format CH-001.',
                }),
                'CH-1',
                {},
            ),
        ).toBe('Format CH-001.');
        expect(
            validateField(field({ pattern: /^CH-\d{3}$/ }), 'CH-001', {}),
        ).toBeNull();
    });

    it('applique une règle métier supplémentaire', () => {
        const f = field({
            validate: (v) => (v === 'doublon' ? 'Ce code existe déjà.' : null),
        });

        expect(validateField(f, 'doublon', {})).toBe('Ce code existe déjà.');
        expect(validateField(f, 'neuf', {})).toBeNull();
    });

    it('permet de comparer deux champs (fin après début)', () => {
        const end = field({
            name: 'end',
            type: 'date',
            validate: (v, all) =>
                all.start && v && String(v) < String(all.start)
                    ? 'La fin doit être postérieure au début.'
                    : null,
        });

        expect(validateField(end, '2026-10-01', { start: '2026-10-10' })).toBe(
            'La fin doit être postérieure au début.',
        );
        expect(
            validateField(end, '2026-10-20', { start: '2026-10-10' }),
        ).toBeNull();
    });

    it('valide tout un formulaire et ne renvoie que les champs en erreur', () => {
        const errors = validateAll(
            [field({ name: 'a', required: true }), field({ name: 'b' })],
            { a: '', b: '' },
        );

        expect(Object.keys(errors)).toEqual(['a']);
    });

    it('isEmpty : une case décochée est vide', () => {
        expect(isEmpty(false)).toBe(true);
        expect(isEmpty(true)).toBe(false);
        expect(isEmpty(0)).toBe(false);
    });
});
