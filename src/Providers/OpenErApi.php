<?php
declare(strict_types=1);

namespace App\Providers;
use App\HttpClient; 

final class OpenErApi implements RateProvider
{
    private const URL = 'https://open.er-api.com/v6/latest/USD';

    public function getConversion(string $currency): array
    {
        $currency = strtoupper($currency);
        $raw = HttpClient::get(self::URL);
        $json = json_decode($raw, true);

        /*
        Formato salida API
        {
            "result": "success",
            "provider": "https://www.exchangerate-api.com",
            "documentation": "https://www.exchangerate-api.com/docs/free",
            "terms_of_use": "https://www.exchangerate-api.com/terms",
            "time_last_update_unix": 1758067351,
            "time_last_update_utc": "Wed, 17 Sep 2025 00:02:31 +0000",
            "time_next_update_unix": 1758155101,
            "time_next_update_utc": "Thu, 18 Sep 2025 00:25:01 +0000",
            "time_eol_unix": 0,
            "base_code": "USD",
            "rates": {
                "USD": 1,
                "AED": 3.6725,
                "AFN": 68.341034,
                "ALL": 82.17678,
                "AMD": 383.220203,
            }
        }
        */
 
        if (!is_array($json) || ($json['result'] ?? '') !== 'success') {
            throw new RuntimeException('Open.er: respuesta inválida');
        }
        if (!isset($json['rates'][$currency])) {
            throw new RuntimeException("Open.er: moneda no soportada $currency");
        }
        $rate = (float)$json['rates'][$currency]; // MONEDA por 1 USD
        if ($rate <= 0) {
            throw new RuntimeException('Open.er: tasa inválida');
        }
        $date = isset($json['time_last_update_utc']) ? (string)$json['time_last_update_utc'] : '';
        return [
            'rate'   => $rate,
            'date'   => $date,
            'source' => 'Open.er (open.er-api.com)',
        ];
    }
}
