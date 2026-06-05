<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $medicineId,
        public readonly string $medicineName,
        public readonly int $requestedQuantity,
        public readonly int $availableStock,
    ) {
        parent::__construct("Stok {$medicineName} tidak mencukupi.");
    }
}
