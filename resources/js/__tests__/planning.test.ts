import { describe, expect, it } from 'vitest';
import {
    globalProgress,
    taskHealth,
    theoreticalProgress,
} from '@/lib/planning';
import type { Task } from '@/types/erp';

const task = (over: Partial<Task>): Task => ({
    id: 1,
    chantierId: 1,
    name: 'Tâche',
    start: '2026-10-01',
    end: '2026-10-10',
    progress: 0,
    weight: 1,
    lastUpdate: '2026-10-08',
    ...over,
});

describe('avancement théorique (RG7.5)', () => {
    it('CA7.1 : tâche du 1er au 10, le 8 -> 78 %', () => {
        expect(
            theoreticalProgress(
                { start: '2026-10-01', end: '2026-10-10' },
                '2026-10-08',
            ),
        ).toBe(78);
    });

    it('vaut 0 % avant le début et 100 % après la fin', () => {
        expect(
            theoreticalProgress(
                { start: '2026-10-10', end: '2026-10-20' },
                '2026-10-01',
            ),
        ).toBe(0);
        expect(
            theoreticalProgress(
                { start: '2026-10-01', end: '2026-10-10' },
                '2026-11-01',
            ),
        ).toBe(100);
    });

    it('traite un jalon (début = fin)', () => {
        expect(
            theoreticalProgress(
                { start: '2026-10-10', end: '2026-10-10' },
                '2026-10-09',
            ),
        ).toBe(0);
        expect(
            theoreticalProgress(
                { start: '2026-10-10', end: '2026-10-10' },
                '2026-10-10',
            ),
        ).toBe(100);
    });
});

describe('statut d’une tâche (RG7.6, RG7.7)', () => {
    it('CA7.1 : 30 % réalisés le 8 sur une tâche du 1er au 10 -> à risque', () => {
        expect(taskHealth(task({ progress: 30 }), '2026-10-08')).toBe('risk');
    });

    it('est en retard si la fin prévue est dépassée sans atteindre 100 %', () => {
        expect(taskHealth(task({ progress: 90 }), '2026-10-12')).toBe('late');
    });

    it('est terminée à 100 %, même après la fin prévue', () => {
        expect(taskHealth(task({ progress: 100 }), '2026-10-12')).toBe('done');
    });

    it('n’est pas à risque tant que l’écart reste sous le seuil de 15 points', () => {
        // théorique 78 %, réel 65 % : écart de 13 points
        expect(taskHealth(task({ progress: 65 }), '2026-10-08')).toBe('ok');
    });

    it('est « non renseignée » sans mise à jour depuis plus de 7 jours', () => {
        expect(
            taskHealth(
                task({
                    progress: 20,
                    end: '2026-12-01',
                    lastUpdate: '2026-10-01',
                }),
                '2026-10-12',
            ),
        ).toBe('stale');
    });

    it('est « à venir » avant son début', () => {
        expect(
            taskHealth(
                task({
                    start: '2026-11-01',
                    end: '2026-11-10',
                    lastUpdate: '2026-10-08',
                }),
                '2026-10-12',
            ),
        ).toBe('upcoming');
    });
});

describe('avancement global pondéré (RG7.8)', () => {
    it('pondère l’avancement par le poids de chaque tâche', () => {
        const tasks = [
            task({ progress: 100, weight: 1 }),
            task({ progress: 0, weight: 3 }),
        ];

        expect(globalProgress(tasks)).toBe(25);
    });

    it('renvoie 0 sans tâche ni pondération', () => {
        expect(globalProgress([])).toBe(0);
        expect(globalProgress([task({ weight: 0, progress: 50 })])).toBe(0);
    });
});
