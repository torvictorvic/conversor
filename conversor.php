<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/HttpClient.php';
require __DIR__ . '/src/Providers/RateProvider.php';
require __DIR__ . '/src/Providers/OpenErApi.php';
require __DIR__ . '/src/Providers/FixerApi.php';
require __DIR__ . '/src/Cache.php';
require __DIR__ . '/src/Providers/CachedProvider.php';
require __DIR__ . '/src/Converter.php';
require __DIR__ . '/src/Log.php';

use App\Log;
use App\Converter;
use App\Env;
use App\Providers\{OpenErApi, FixerApi, CachedProvider};


// Configuracion de del CLI OPTS
function println($s=''){ echo $s . PHP_EOL; }
function color($s, $c){ return "\033[".$c."m".$s."\033[0m"; } // 32=verde, 31=rojo, 36=cyan

$USAGE = <<<TXT
Uso:
  php convert.php [--format=table|json] [--providers=open,fixer] [--ttl=60] <monto> <moneda>
Ejemplos:
  php convert.php 100 ARS
  php convert.php --format=json --ttl=120 250000 COP
TXT;

Env::load(__DIR__ . '/.env');

// Inicializado logs
$execId = bin2hex(random_bytes(4));              // id de ejecución (para correlación)
$logDir = Env::get('LOG_DIR') ?: __DIR__ . '/logs';
Log::init($logDir, $execId);


// Preparando formatos de salidas
$longopts = ['format::','providers::','ttl::','help'];
$opts = getopt('', $longopts, $optind);

if (isset($opts['help'])) { println($USAGE); exit(0); }

$args = array_slice($argv, $optind);
if (count($args) !== 2) {
    fwrite(STDERR, $USAGE . PHP_EOL);
    exit(2);
}

$format    = isset($opts['format']) ? strtolower((string)$opts['format']) : 'table';
$provCsv   = isset($opts['providers']) ? strtolower((string)$opts['providers']) : 'open,fixer';
$ttl       = isset($opts['ttl']) ? (int)$opts['ttl'] : (int)(getenv('RATE_TTL') ?: 60);

$amountRaw = $args[0];
$currency  = strtoupper($args[1]);

if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) { fwrite(STDERR, "Monto inválido.\n"); exit(2); }
$amount = (float)$amountRaw;

$SUPPORTED = ['ARS','COP'];
if (!in_array($currency, $SUPPORTED, true)) {
    fwrite(STDERR, "Error: moneda no soportada. Use: " . implode(', ', $SUPPORTED) . "\n");
    exit(2);
}

// Inicializando los Proveedores de APIs
$providersSel = array_map('trim', explode(',', $provCsv));
$providers = [];
foreach ($providersSel as $p) {
    if ($p === 'open')  $providers[] = new CachedProvider(new OpenErApi(), $ttl);
    if ($p === 'fixer') $providers[] = new CachedProvider(new FixerApi(),  $ttl);
}
if (!$providers) {
    fwrite(STDERR, "Sin proveedores válidos. Usa --providers=open,fixer\n");
    exit(2);
}

$converter = new Converter(...$providers);
$results   = $converter->convertAmountToUsd($amount, $currency);

// Preprando formatos de salida
$now = date('Y-m-d H:i');

if ($format === 'json') {
    $payload = [
        'amount'   => $amount,
        'currency' => $currency,
        'results'  => array_map(function($r){
            return [
                'provider' => $r['provider'],
                'source'   => $r['source'],
                'usd'      => is_nan($r['usd']) ? null : round($r['usd'], 2),
                'rate'     => is_nan($r['rate']) ? null : $r['rate'],
                'date'     => $r['date'],
            ];
        }, $results),
        'updated_at' => $now,
    ];
    echo json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}


// Lineas monto convertido a USD usando el source de cada provider
foreach ($results as $r) {
    $label = $r['source']; // ej: "Open.er (open.er-api.com)"
    if (is_nan($r['usd'])) {
        println(color($label . ': N/A (error al obtener la tasa)', '31'));
    } else {
        println(color($label . ': ' . number_format($r['usd'], 2, '.', ',') . ' USD', '32'));
    }
}

println(''); // espacio

// Nota con la TASA usada MONEDA por 1 USD
$noteNameMap = [
    'OpenErApi' => 'Open.er',
    'FixerApi'  => 'Fixer',
];

// Prepara pares [left, value]
$pairs = [];
$maxLeftLen = 0;
foreach ($results as $r) {
    $name = isset($noteNameMap[$r['provider']]) ? $noteNameMap[$r['provider']] : $r['provider'];
    $left = "Valor por cambio por 1 USD en {$name} es";
    $value = is_nan($r['rate'])
        ? 'N/A'
        : number_format($r['rate'], 2, '.', ' ') . ' ' . $currency;

    $pairs[] = [$left, $value];
    $maxLeftLen = max($maxLeftLen, strlen($left));
}

println('Nota:');
foreach ($pairs as [$left, $value]) {
    // alinear columnas
    $line = str_pad($left, $maxLeftLen, ' ', STR_PAD_RIGHT) . ' = ' . $value;
    println($line);
}

println('');
println('Fecha Actualizacion: ' . color($now, '36'));
