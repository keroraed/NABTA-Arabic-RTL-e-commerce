<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The cart no longer matches the catalogue — stock ran out or a product was
 * deactivated between the customer loading checkout and submitting it.
 * Surfaced to the customer in Arabic; never a 500.
 */
class CartChangedException extends RuntimeException {}
