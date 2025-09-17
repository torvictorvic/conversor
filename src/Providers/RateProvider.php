<?php
declare(strict_types=1);

namespace App\Providers;

interface RateProvider
{
    public function getConversion(string $currency): array;
}
