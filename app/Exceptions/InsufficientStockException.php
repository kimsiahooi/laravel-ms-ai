<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by StockService when a movement would drive on-hand below zero. The
 * controller catches it and rethrows a 422 validation error on `quantity`.
 */
class InsufficientStockException extends \RuntimeException {}
