<script setup lang="ts">
import { computed } from 'vue';
import { TODAY, addDays, dateFr, daysBetween } from '@/lib/format';
import { taskHealth, theoreticalProgress } from '@/lib/planning';
import { statusMeta } from '@/lib/status';
import type { Task, TaskHealth } from '@/types/erp';

/**
 * Gantt maison en CSS (aucune bibliothèque, décision reportée : ADR 0007).
 * Une ligne par tâche, une barre positionnée en % de la période affichée,
 * l'avancement réel en plein et le trait vertical « aujourd'hui ».
 * Couleurs : tâches en retard, à risque ou non renseignées signalées.
 */
const props = defineProps<{ tasks: Task[] }>();

const range = computed(() => {
    const starts = props.tasks.map((t) => t.start).sort();
    const ends = props.tasks.map((t) => t.end).sort();
    const start = addDays(starts[0] ?? TODAY, -3);
    const end = addDays(ends[ends.length - 1] ?? TODAY, 3);

    return { start, end, total: Math.max(1, daysBetween(start, end)) };
});

const months = computed(() => {
    const list: { label: string; left: number }[] = [];
    const [y0, m0] = range.value.start.split('-').map(Number);
    let y = y0;
    let m = m0;

    for (let i = 0; i < 40; i++) {
        const first = `${y}-${String(m).padStart(2, '0')}-01`;
        const offset = daysBetween(range.value.start, first);

        if (offset > range.value.total) {
            break;
        }

        if (offset >= 0) {
            list.push({
                label: new Date(Date.UTC(y, m - 1, 1)).toLocaleDateString(
                    'fr-FR',
                    {
                        month: 'short',
                        year: '2-digit',
                        timeZone: 'UTC',
                    },
                ),
                left: (offset / range.value.total) * 100,
            });
        }

        m += 1;

        if (m > 12) {
            m = 1;
            y += 1;
        }
    }

    return list;
});

const todayLeft = computed(() => {
    const offset = daysBetween(range.value.start, TODAY);

    return offset >= 0 && offset <= range.value.total
        ? (offset / range.value.total) * 100
        : null;
});

function bar(task: Task) {
    const left =
        (daysBetween(range.value.start, task.start) / range.value.total) * 100;
    const width = Math.max(
        0.8,
        ((daysBetween(task.start, task.end) + 1) / range.value.total) * 100,
    );

    return { left: `${left}%`, width: `${width}%` };
}

const barTone: Record<TaskHealth, string> = {
    done: 'bg-success',
    late: 'bg-danger',
    risk: 'bg-warning',
    stale: 'bg-brand',
    ok: 'bg-info',
    upcoming: 'bg-muted-foreground/60',
};

const legend: TaskHealth[] = [
    'done',
    'ok',
    'risk',
    'late',
    'stale',
    'upcoming',
];
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <span
                v-for="health in legend"
                :key="health"
                class="flex items-center gap-1.5"
            >
                <span class="size-2.5 rounded-sm" :class="barTone[health]" />
                {{ statusMeta('task', health).label }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-0.5 bg-foreground" /> Aujourd’hui ({{
                    dateFr(TODAY)
                }})
            </span>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card">
            <div class="min-w-[760px]">
                <!-- En-tête : mois -->
                <div
                    class="grid grid-cols-[200px_1fr] border-b bg-muted/50 text-xs text-muted-foreground"
                >
                    <div class="px-3 py-2 font-medium">Tâche</div>
                    <div class="relative h-8">
                        <span
                            v-for="month in months"
                            :key="month.label"
                            class="absolute top-2 border-l pl-1"
                            :style="{ left: `${month.left}%` }"
                        >
                            {{ month.label }}
                        </span>
                    </div>
                </div>

                <!-- Lignes -->
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    class="grid grid-cols-[200px_1fr] border-b last:border-0"
                >
                    <div class="px-3 py-2 text-sm">
                        <p class="truncate font-medium" :title="task.name">
                            {{ task.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ dateFr(task.start) }} → {{ dateFr(task.end) }}
                        </p>
                    </div>
                    <div class="relative h-14">
                        <span
                            v-if="todayLeft !== null"
                            class="absolute top-0 z-10 h-full w-0.5 bg-foreground/70"
                            :style="{ left: `${todayLeft}%` }"
                        />
                        <template v-if="task.milestone">
                            <span
                                class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-brand"
                                :style="{ left: bar(task).left }"
                                :title="`Jalon : ${task.name}`"
                            />
                        </template>
                        <div
                            v-else
                            class="absolute top-1/2 h-5 -translate-y-1/2 overflow-hidden rounded-md bg-muted"
                            :style="bar(task)"
                            :title="`${task.name} — ${task.progress} % (théorique ${task.theoretical ?? theoreticalProgress(task)} %)`"
                        >
                            <div
                                class="h-full"
                                :class="
                                    barTone[task.health ?? taskHealth(task)]
                                "
                                :style="{ width: `${task.progress}%` }"
                            />
                            <span
                                class="absolute inset-0 flex items-center justify-center text-[10px] font-semibold tabular-nums"
                            >
                                {{ task.progress }} %
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
