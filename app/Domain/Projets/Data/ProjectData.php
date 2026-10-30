<?php

declare(strict_types=1);

namespace App\Domain\Projets\Data;

use App\Domain\Projets\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Projet tel que l'affichent les écrans (type TypeScript Project).
 */
final class ProjectData
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Project $project, bool $withDocuments = false): array
    {
        $data = [
            'id' => $project->id,
            'number' => $project->number,
            'name' => $project->name,
            'clientId' => $project->client_id,
            'clientName' => self::text($project, 'client.name'),
            'location' => $project->location,
            'managerId' => $project->manager_id,
            'manager' => self::text($project, 'manager.name'),
            'agencyId' => $project->agency_id,
            'agency' => self::text($project, 'agency.name'),
            'budget' => $project->budget,
            'startDate' => $project->start_date->toDateString(),
            'endDate' => $project->end_date->toDateString(),
            'status' => $project->status->getValue(),
            'abandonReason' => $project->abandon_reason,
        ];

        if ($withDocuments) {
            $data['documents'] = $project->getMedia('documents')->map(fn (Media $m): array => [
                'name' => $m->file_name,
                'size' => number_format($m->size / 1_048_576, 1, ',', ' ').' Mo',
                'date' => $m->created_at?->toDateString() ?? '',
            ])->values()->all();
        }

        return $data;
    }

    private static function text(Project $project, string $path): string
    {
        $value = data_get($project, $path);

        return is_scalar($value) ? (string) $value : '';
    }
}
