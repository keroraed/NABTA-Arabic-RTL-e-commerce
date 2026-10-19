<?php

namespace App\Services\Pos;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Phase 1 of the scale integration: produce the PLU file the ACLAS PC software
 * imports, so shop staff can load it by hand today.
 *
 * Phase 2 — the bridge agent pushing PLUs straight to the scale over TCP or
 * RS-232 — needs the vendor's communication protocol, which we do not have.
 * That is exactly why this exists: nothing here is blocked on the distributor.
 *
 * WARNING: the column order below is a reasonable guess. Confirm it against the
 * ACLAS software's own import screen before the first real export, and adjust
 * `columns()` if it differs. Getting it wrong prices goods incorrectly on the
 * scale, which is worse than not exporting at all.
 */
class PluExporter
{
    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        return Product::query()
            ->where('is_weighted', true)
            ->where('sync_to_scale', true)
            ->where('is_active', true)
            ->whereNotNull('plu_code')
            ->orderBy('plu_code')
            ->get();
    }

    /** @return array<int, string> */
    public function columns(): array
    {
        return ['PLU', 'NAME', 'PRICE', 'UNIT'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->products()->map(fn (Product $p) => [
            (string) $p->plu_code,
            $p->name_ar,
            // The scale wants a plain decimal price per kilo.
            number_format((float) $p->price_per_kg, 2, '.', ''),
            'kg',
        ])->all();
    }

    public function toCsv(): string
    {
        $delimiter = config('pos.plu_export.delimiter', ',');

        $lines = [implode($delimiter, $this->columns())];

        foreach ($this->rows() as $row) {
            $lines[] = implode($delimiter, array_map(
                fn (string $cell) => $this->escape($cell, $delimiter),
                $row,
            ));
        }

        $csv = implode("\r\n", $lines)."\r\n";

        // Some builds of the ACLAS importer are not UTF-8 aware and will mangle
        // Arabic product names. The encoding is configurable for that reason.
        $encoding = config('pos.plu_export.encoding', 'UTF-8');

        if ($encoding !== 'UTF-8' && function_exists('iconv')) {
            $converted = @iconv('UTF-8', $encoding.'//TRANSLIT', $csv);
            $csv = $converted === false ? $csv : $converted;
        }

        return $csv;
    }

    public function filename(): string
    {
        return 'nabta-plu-'.now()->format('Ymd-Hi').'.csv';
    }

    private function escape(string $value, string $delimiter): string
    {
        if (str_contains($value, $delimiter) || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
