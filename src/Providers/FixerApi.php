<?php
declare(strict_types=1);

namespace App\Providers;

use App\Env;
use App\HttpClient;

final class FixerApi implements RateProvider
{
    // API data.fixer.io
    private const URL = 'https://data.fixer.io/api/latest';

    public function getConversion(string $currency): array
    {
        $currency = strtoupper($currency);
        $key = Env::get('FIXER_API_KEY');
        if (!$key) throw new RuntimeException('Fixer: falta FIXER_API_KEY en .env');

        // Parsear en params el access_key y symbols
        $url = self::URL . '?access_key=' . rawurlencode($key) . '&symbols=USD,' . $currency;
        $raw = HttpClient::get($url);
        $json = json_decode($raw, true);

        if (!is_array($json) || empty($json['success'])) {
            $err = isset($json['error']['type']) ? $json['error']['type'] : 'respuesta inválida';
            throw new RuntimeException("Fixer: $err");
        }

        $rates = $json['rates'] ?? null;
        if (!is_array($rates) || !isset($rates['USD'], $rates[$currency])) {
            throw new RuntimeException("Fixer: faltan tasas USD/$currency");
        }

        /*
        Formato salida API
        {
            "base": "EUR",
            "rates": {
              "USD": 1.20,
              "ARS": 130.50,
              "COP": 4700.25
            }
        }*/
        $usdPerEur = (float)$rates['USD']; // USD por 1 EUR
        $curPerEur = (float)$rates[$currency]; // MONEDA por 1 EUR
        if ($usdPerEur <= 0 || $curPerEur <= 0) {
            throw new RuntimeException('Fixer: tasas inválidas');
        }

        // MONEDA por 1 USD:
        $curPerUsd = $curPerEur / $usdPerEur;

        return [
            'rate'   => $curPerUsd,
            'date'   => (string)($json['date'] ?? ''),
            'source' => 'Fixer (data.fixer.io)',
        ];
    }
}
