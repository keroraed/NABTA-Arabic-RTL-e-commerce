<?php

namespace App\Services\Pos;

/**
 * The result of parsing a scanned barcode.
 *
 * Either a weighted label from the scale (PLU plus an embedded weight), or a
 * plain unit barcode to be looked up on products.barcode.
 */
final class ParsedBarcode
{
    private function __construct(
        public readonly string $raw,
        public readonly bool $isWeighted,
        public readonly ?string $plu = null,
        public readonly ?int $weightGrams = null,
        public readonly ?int $pricePiastres = null,
        public readonly ?string $unitBarcode = null,
    ) {}

    public static function weighted(string $raw, string $plu, int $weightGrams): self
    {
        return new self($raw, true, plu: $plu, weightGrams: $weightGrams);
    }

    /**
     * Some scales embed the price rather than the weight. Supported so switching
     * the scale's configuration does not require a code change.
     */
    public static function pricedByWeight(string $raw, string $plu, int $pricePiastres): self
    {
        return new self($raw, true, plu: $plu, pricePiastres: $pricePiastres);
    }

    public static function unit(string $raw): self
    {
        return new self($raw, false, unitBarcode: $raw);
    }

    public function weightKg(): ?float
    {
        return $this->weightGrams === null ? null : round($this->weightGrams / 1000, 3);
    }
}
