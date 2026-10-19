<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a scanned barcode cannot be parsed, so the POS can show
 * "منتج غير معروف" instead of crashing mid-queue.
 */
class UnknownBarcodeException extends RuntimeException
{
    public function __construct(public readonly string $barcode, string $message = 'باركود غير معروف')
    {
        parent::__construct($message);
    }
}
