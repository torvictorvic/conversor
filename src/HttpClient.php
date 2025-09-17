<?php
declare(strict_types=1);

namespace App;

use App\Log;

final class HttpClient
{
    public static function get(string $url, int $timeout = 12): string
    {
        $start = microtime(true);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'currency-cli-php74/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $body = curl_exec($ch);
        $ms   = (int) round((microtime(true) - $start) * 1000);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = ($body === false) ? curl_error($ch) : '';

        // Log acceso
        Log::access([
            'url'    => $url,
            'status' => $body === false ? null : $code,
            'ms'     => $ms,
            'bytes'  => $body === false ? 0 : strlen((string)$body),
            'error'  => $err ?: null,
        ]);

        if ($body === false) {
            curl_close($ch);
            
            // Log de error
            Log::error('HTTP error (cURL)', ['url' => $url, 'error' => $err, 'ms' => $ms]);
            throw new \RuntimeException("HTTP error: $err");
        }

        curl_close($ch);

        if ($code < 200 || $code >= 300) {

            // Log de error
            Log::error('HTTP non-2xx', ['url' => $url, 'status' => $code, 'ms' => $ms]);
            throw new \RuntimeException("HTTP status $code for $url");
        }
        if ($body === '') {

            // Log de error
            Log::error('HTTP empty body', ['url' => $url, 'status' => $code, 'ms' => $ms]);
            throw new \RuntimeException("Empty body from $url");
        }
        return $body;
    }
}