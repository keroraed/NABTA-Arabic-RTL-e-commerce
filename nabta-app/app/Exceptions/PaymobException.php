<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Paymob could not be reached, rejected the request, or is not configured.
 * The message is for the log only — customers get a generic Arabic message.
 */
class PaymobException extends RuntimeException {}
