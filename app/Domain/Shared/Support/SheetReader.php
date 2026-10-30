<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Lecture brute d'un fichier Excel (.xlsx, .xls) ou CSV (séparateur , ; ou
 * tabulation, UTF-8 ou Windows-1252) : la première feuille, ligne par ligne.
 * Sert aux imports (planning, référentiel des prix, etc.).
 */
final class SheetReader
{
    /**
     * @return list<list<mixed>>
     */
    public static function read(UploadedFile $file): array
    {
        try {
            $rows = strtolower($file->getClientOriginalExtension()) === 'csv'
                ? self::csv((string) file_get_contents($file->getRealPath()))
                : self::spreadsheet($file);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Le fichier est illisible.']);
        }

        return $rows;
    }

    /**
     * @return list<list<mixed>>
     */
    private static function spreadsheet(UploadedFile $file): array
    {
        $sheet = new SheetRows;
        Excel::import($sheet, $file);

        return array_values(array_map(fn ($row): array => array_values((array) $row), $sheet->rows));
    }

    /**
     * @return list<list<mixed>>
     */
    private static function csv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\R/', $content) ?: [];
        $first = $lines[0] ?? '';
        $delimiter = collect([';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t")])
            ->sortDesc()->keys()->first();
        $delimiter = is_string($delimiter) ? $delimiter : ';';

        return array_values(array_map(
            fn (string $line): array => str_getcsv($line, $delimiter, '"', ''),
            array_filter($lines, fn (string $line): bool => trim($line) !== ''),
        ));
    }
}
