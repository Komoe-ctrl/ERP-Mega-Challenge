import { TODAY, daysBetween } from '@/lib/format';
import type { Task, TaskHealth } from '@/types/erp';

/** Seuils paramétrables (module Administration, RG7.6 et RG7.7). */
export const PLANNING_RISK_THRESHOLD = 15;
export const PLANNING_STALE_DAYS = 7;

/**
 * RG7.5 : avancement théorique = part du temps écoulé entre le début et la fin
 * prévue. CA7.1 : tâche du 1er au 10, le 8 -> 7 jours écoulés sur 9 = 78 %.
 */
export function theoreticalProgress(
    task: Pick<Task, 'start' | 'end'>,
    today = TODAY,
): number {
    const total = daysBetween(task.start, task.end);

    if (total <= 0) {
        return today >= task.end ? 100 : 0;
    }

    const elapsed = daysBetween(task.start, today);

    return Math.round(Math.min(100, Math.max(0, (elapsed / total) * 100)));
}

/**
 * RG7.6 : en retard si la fin prévue est dépassée sans atteindre 100 % ;
 * à risque si l'avancement réel est inférieur au théorique au-delà du seuil.
 * RG7.7 : « non renseignée » sans mise à jour depuis le délai paramétré.
 */
export function taskHealth(task: Task, today = TODAY): TaskHealth {
    if (task.progress >= 100) {
        return 'done';
    }

    if (today > task.end) {
        return 'late';
    }

    if (
        theoreticalProgress(task, today) - task.progress >
        PLANNING_RISK_THRESHOLD
    ) {
        return 'risk';
    }

    if (daysBetween(task.lastUpdate, today) > PLANNING_STALE_DAYS) {
        return 'stale';
    }

    return today < task.start ? 'upcoming' : 'ok';
}

/** RG7.8 : avancement global pondéré par tâche. */
export function globalProgress(tasks: Task[]): number {
    const weight = tasks.reduce((sum, t) => sum + t.weight, 0);

    if (weight === 0) {
        return 0;
    }

    return Math.round(
        tasks.reduce((sum, t) => sum + t.progress * t.weight, 0) / weight,
    );
}
