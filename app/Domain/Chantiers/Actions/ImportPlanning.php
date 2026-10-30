<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Support\SheetReader;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import du planning depuis Excel (RG7.3). Colonnes : Tâche, Début, Fin prévue,
 * Pondération, puis facultativement Jalon (oui/non). La première ligne est
 * l'en-tête. Tout ou rien : une seule ligne invalide annule l'import.
 */
final class ImportPlanning
{
    public const MAX_ROWS = 500;

    /** @return int nombre de tâches créées */
    public function execute(Chantier $chantier, UploadedFile $file): int
    {
        $rows = array_slice(SheetReader::read($file), 1);
        $rows = array_values(array_filter($rows, fn (array $r): bool => count(array_filter($r, fn ($c) => $c !== null && $c !== '')) > 0));

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Le fichier ne contient aucune tâche.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Le fichier dépasse '.self::MAX_ROWS.' tâches.']);
        }

        $tasks = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $milestone = in_array(mb_strtolower(trim((string) (is_scalar($row[4] ?? null) ? $row[4] : ''))), ['oui', 'o', '1', 'yes'], true);
            $weight = $row[3] ?? null;
            $task = [
                'name' => is_scalar($row[0] ?? null) ? trim((string) $row[0]) : '',
                'start_date' => $this->date($row[1] ?? null),
                'end_date' => $this->date($row[2] ?? null),
                // Un jalon n'a pas de poids : une pondération vide vaut 0.
                'weight' => ($weight === null || $weight === '') && $milestone ? 0 : $weight,
                'is_milestone' => $milestone,
            ];

            $validator = Validator::make($task, [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'weight' => ['required', 'integer', 'min:0', 'max:100'],
            ], [
                'name.required' => 'la tâche est vide',
                'name.min' => 'le nom est trop court',
                'start_date.required' => 'début invalide',
                'start_date.date' => 'début invalide',
                'end_date.required' => 'fin prévue invalide',
                'end_date.date' => 'fin prévue invalide',
                'end_date.after_or_equal' => 'la fin précède le début',
                'weight.required' => 'pondération manquante',
                'weight.integer' => 'la pondération doit être un entier',
                'weight.min' => 'la pondération doit être comprise entre 0 et 100',
                'weight.max' => 'la pondération doit être comprise entre 0 et 100',
            ]);

            if ($validator->fails()) {
                $errors[] = "Ligne {$line} : ".implode(', ', $validator->errors()->all()).'.';

                continue;
            }

            $tasks[] = $task;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode(' ', array_slice($errors, 0, 5))]);
        }

        DB::transaction(function () use ($chantier, $tasks): void {
            foreach ($tasks as $task) {
                $chantier->tasks()->create([
                    'name' => $task['name'],
                    'start_date' => $task['start_date'],
                    'end_date' => $task['is_milestone'] ? $task['start_date'] : $task['end_date'],
                    'weight' => is_numeric($task['weight']) ? (int) $task['weight'] : 0,
                    'is_milestone' => $task['is_milestone'],
                    'progress' => 0,
                    'last_update_on' => now()->toDateString(),
                ]);
            }
        });

        return count($tasks);
    }

    /** Date Excel (numéro de série) ou texte JJ/MM/AAAA ou AAAA-MM-JJ, renvoyée en AAAA-MM-JJ. */
    private function date(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, trim($value));
            } catch (InvalidFormatException) {
                continue;
            }

            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === trim($value)) {
                return $parsed->toDateString();
            }
        }

        return null;
    }
}
