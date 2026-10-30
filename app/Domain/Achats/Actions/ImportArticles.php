<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Article;
use App\Domain\Shared\Support\SheetReader;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RG8.16 : le référentiel est chargé par import Excel ou CSV. Colonnes :
 * référence, libellé, catégorie, unité, prix unitaire (FCFA). La première
 * ligne est l'en-tête. Tout ou rien : une ligne invalide annule l'import.
 * Une référence existante est mise à jour (son changement de prix est historisé).
 */
final class ImportArticles
{
    public const MAX_ROWS = 2000;

    public function __construct(private readonly SaveArticle $save) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function execute(UploadedFile $file, User $by): array
    {
        $rows = array_values(array_filter(
            array_slice(SheetReader::read($file), 1),
            fn (array $r): bool => count(array_filter($r, fn ($c) => $c !== null && trim((string) (is_scalar($c) ? $c : '')) !== '')) > 0,
        ));

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Le fichier ne contient aucun article.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Le fichier dépasse '.self::MAX_ROWS.' articles.']);
        }

        [$articles, $errors] = $this->parse($rows);

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode(' ', array_slice($errors, 0, 5))]);
        }

        return DB::transaction(function () use ($articles, $by): array {
            $created = 0;
            $updated = 0;

            foreach ($articles as $data) {
                $existing = Article::query()->where('reference', $data['reference'])->first();

                if ($existing === null) {
                    $article = Article::query()->create($data + ['is_active' => true]);
                    $this->save->recordPrice($article, $by, 'Import initial du référentiel');
                    $created++;
                } elseif ($existing->only(array_keys($data)) !== $data) {
                    $this->save->update($existing, $data, 'Import du référentiel', $by);
                    $updated++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{0: list<array{reference: string, label: string, category: string, unit: string, price: int}>, 1: list<string>}
     */
    private function parse(array $rows): array
    {
        $articles = [];
        $errors = [];
        $seen = [];
        $categories = array_combine(array_map('mb_strtolower', Article::CATEGORIES), Article::CATEGORIES);
        $units = array_combine(array_map('mb_strtolower', Article::UNITS), Article::UNITS);

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $problems = [];
            $text = fn (int $i): string => is_scalar($row[$i] ?? null) ? trim((string) $row[$i]) : '';

            $reference = strtoupper($text(0));
            $label = $text(1);
            $category = $categories[mb_strtolower($text(2))] ?? null;
            $unit = $units[mb_strtolower($text(3))] ?? null;
            $price = $this->price($row[4] ?? null);

            if (preg_match('/^[A-Z]{3}-\d{3}$/', $reference) !== 1) {
                $problems[] = 'référence invalide (ex. CIM-003)';
            } elseif (isset($seen[$reference])) {
                $problems[] = "référence déjà présente ligne {$seen[$reference]}";
            }

            if (mb_strlen($label) < 3) {
                $problems[] = 'libellé trop court';
            }

            if ($category === null) {
                $problems[] = 'catégorie inconnue';
            }

            if ($unit === null) {
                $problems[] = 'unité inconnue';
            }

            if ($price === null || $price < 1) {
                $problems[] = 'prix invalide (entier en FCFA)';
            }

            if ($problems !== []) {
                $errors[] = "Ligne {$line} : ".implode(', ', $problems).'.';

                continue;
            }

            $seen[$reference] = $line;
            $articles[] = ['reference' => $reference, 'label' => $label, 'category' => (string) $category, 'unit' => (string) $unit, 'price' => (int) $price];
        }

        return [$articles, $errors];
    }

    /** Prix entier en FCFA : « 6500 », « 6 500 », « 6500,00 » ; jamais de décimales. */
    private function price(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return floor($value) === $value ? (int) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $clean = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($value));
        $clean = str_replace(',', '.', $clean);

        return preg_match('/^\d+(\.0+)?$/', $clean) === 1 ? (int) $clean : null;
    }
}
