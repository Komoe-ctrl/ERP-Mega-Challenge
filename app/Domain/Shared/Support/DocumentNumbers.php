<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Administration\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation des documents (RG1.3). Le format est un paramètre
 * (ex. « BC-{YYYY}-{SEQ:5} ») : {YYYY} est l'année, {SEQ:n} un compteur sur n
 * chiffres, repartant de 1 chaque année. Le compteur est incrémenté dans la
 * transaction appelante avec verrou : deux documents n'ont jamais le même numéro.
 */
final class DocumentNumbers
{
    /**
     * @param  string  $type  clé du compteur (ex. bon_commande, consultation)
     * @param  string  $default  format utilisé si aucun paramètre `numbering.<type>` n'existe
     */
    public static function next(string $type, string $default): string
    {
        $setting = Setting::get("numbering.{$type}");
        $format = is_string($setting) && str_contains($setting, '{SEQ:') ? $setting : $default;
        $year = now()->year;

        $sequence = DB::transaction(function () use ($type, $year): int {
            $row = DB::table('number_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('number_sequences')->insert(['type' => $type, 'year' => $year, 'last' => 1]);

                return 1;
            }

            $next = (int) $row->last + 1;
            DB::table('number_sequences')->where('id', $row->id)->update(['last' => $next]);

            return $next;
        });

        $number = str_replace('{YYYY}', (string) $year, $format);

        return (string) preg_replace_callback(
            '/\{SEQ:(\d+)\}/',
            fn (array $m): string => str_pad((string) $sequence, (int) $m[1], '0', STR_PAD_LEFT),
            $number,
        );
    }
}
