<?php

namespace App\Services\Pos;

use App\Exceptions\UnknownBarcodeException;

/**
 * Parses the barcodes the ACLAS LS6 prints on its weight labels.
 *
 * Driven entirely by config/pos.php. The format was inferred from a single
 * sample label, so it must stay configurable: confirming the real format from
 * the scale's settings menu should be an env change, never a code change.
 *
 * A barcode whose length does not match the weighted layout is treated as a
 * plain unit barcode rather than an error, so an ordinary retail EAN scans
 * straight through to a products.barcode lookup.
 *
 * The JS mirror of this class is public/js/pos/barcode.js. Both read the same
 * config, injected into the page from Blade, so there is one source of truth.
 */
class WeightBarcodeParser
{
    private int $prefixLength;
    private int $pluLength;
    private int $valueLength;
    private string $valueType;
    private bool $hasCheckDigit;

    public function __construct(?array $config = null)
    {
        $config ??= config('pos.barcode');

        $this->prefixLength = (int) ($config['prefix_length'] ?? 0);
        $this->pluLength = (int) ($config['plu_length'] ?? 4);
        $this->valueLength = (int) ($config['value_length'] ?? 5);
        $this->valueType = $config['value_type'] ?? 'weight_grams';
        $this->hasCheckDigit = (bool) ($config['has_check_digit'] ?? false);
    }

    /** Total digits a weighted label is expected to have. */
    public function weightedLength(): int
    {
        return $this->prefixLength + $this->pluLength + $this->valueLength + ($this->hasCheckDigit ? 1 : 0);
    }

    /**
     * @throws UnknownBarcodeException when the input is not digits at all.
     */
    public function parse(string $barcode): ParsedBarcode
    {
        $raw = trim($barcode);

        if ($raw === '' || ! ctype_digit($raw)) {
            throw new UnknownBarcodeException($barcode);
        }

        // Not the weighted layout: an ordinary unit barcode.
        if (strlen($raw) !== $this->weightedLength()) {
            return ParsedBarcode::unit($raw);
        }

        $offset = $this->prefixLength;

        $plu = substr($raw, $offset, $this->pluLength);
        $offset += $this->pluLength;

        $value = (int) substr($raw, $offset, $this->valueLength);

        // A weighted label with a zero value is meaningless — a zero-weight sale
        // would ring up as free. Better to reject it than to charge nothing.
        if ($value <= 0) {
            throw new UnknownBarcodeException($barcode, 'الباركود يحتوي على وزن صفر');
        }

        return $this->valueType === 'price_piastres'
            ? ParsedBarcode::pricedByWeight($raw, $plu, $value)
            : ParsedBarcode::weighted($raw, $plu, $value);
    }

    /** Non-throwing variant for hot paths that prefer a null check. */
    public function tryParse(string $barcode): ?ParsedBarcode
    {
        try {
            return $this->parse($barcode);
        } catch (UnknownBarcodeException) {
            return null;
        }
    }
}
