<?php
declare(strict_types=1);

namespace App;

final class Log
{
    private static $dir = '';
    private static $execId = '';

    public static function init(string $dir, ?string $execId = null): void
    {
        $d = rtrim($dir, '/');
        if ($d === '') {
            $d = sys_get_temp_dir() . '/currency-logs';
        }
        if (!is_dir($d)) {
            @mkdir($d, 0777, true);
        }
        self::$dir = (is_dir($d) && is_writable($d)) ? $d : sys_get_temp_dir();
        self::$execId = $execId ?: (string) getmypid();
    }

    private static function fileFor(string $type): string
    {
        $date = date('Y-m-d');
        return self::$dir . '/' . $type . '-' . $date . '.log';
    }

    private static function write(string $type, array $record): void
    {
        if (self::$dir === '') {
            self::init(sys_get_temp_dir());
        }
        $record = array_merge([
            'ts'      => date('c'),
            'exec_id' => self::$execId,
        ], $record);

        @file_put_contents(
            self::fileFor($type),
            json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    // Log de acceso
    public static function access(array $data): void
    {
        self::write('access', $data);
    }

    // Log de errores
    public static function error(string $message, array $context = []): void
    {
        self::write('error', array_merge(['message' => $message], $context));
    }
}
