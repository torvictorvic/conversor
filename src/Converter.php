<?php
declare(strict_types=1);

namespace App;

use App\Providers\RateProvider;
use App\Log; 

final class Converter
{
    /** @var RateProvider[] */
    private array $providers;

    public function __construct(RateProvider ...$providers)
    {
        $this->providers = $providers;
    }

    public function convertAmountToUsd(float $amount, string $currency): array
    {
        $out = [];
        foreach ($this->providers as $p) {
            try {
                $q = $p->getConversion($currency);  // rate = MONEDA por 1 USD
                $usd = $amount / $q['rate'];  // monto MONEDA  USD
                $out[] = [
                    'provider' => (new \ReflectionClass($p))->getShortName(),
                    'source'   => $q['source'],
                    'usd'      => $usd,
                    'rate'     => $q['rate'],
                    'date'     => $q['date'],
                ];
            } catch (\Throwable $e) {
                // Log error
                Log::error('Provider error', [
                    'provider' => get_class($p),
                    'currency' => $currency,
                    'amount'   => $amount,
                    'error'    => $e->getMessage(),
                ]);

                $out[] = [
                    'provider' => (new \ReflectionClass($p))->getShortName(),
                    'source'   => (new \ReflectionClass($p))->getShortName(),
                    'usd'      => NAN,
                    'rate'     => NAN,
                    'date'     => '',
                ];
            }
        }
        return $out;
    }
}
