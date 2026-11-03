<?php

namespace App\Exceptions;

use Exception;

class TaxRuleNotFoundException extends Exception
{
    public function __construct(
        string $taxCategory,
        string $province,
        string $paymentMethod
    ) {
        parent::__construct(
            "No active tax rule found for category [{$taxCategory}], province [{$province}], and payment method [{$paymentMethod}]."
        );
    }
}
