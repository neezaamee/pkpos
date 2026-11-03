<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $productId,
        public readonly string $locationId,
        public readonly int $requested,
        public readonly int $available
    ) {
        parent::__construct(
            "Insufficient stock for product [{$productId}] at location [{$locationId}]. Requested [{$requested}], but only [{$available}] available."
        );
    }
}
