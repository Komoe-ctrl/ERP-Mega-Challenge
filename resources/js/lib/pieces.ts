import type { Tone } from '@/lib/status';
import type { PieceStatusValue } from '@/types/pieces';

/** Ton du badge d'état d'une pièce. */
export const pieceTone: Record<PieceStatusValue, Tone> = {
    saisie: 'neutral',
    en_validation: 'warning',
    approuvee: 'success',
    rejetee: 'danger',
};

/** Libellé d'une action de l'historique du circuit. */
export const eventLabel: Record<string, string> = {
    saisie: 'Saisie',
    soumise: 'Soumise à validation',
    validee: 'Étape validée',
    approuvee: 'Approuvée',
    rejetee: 'Rejetée',
    remboursee: 'Remboursée',
};
