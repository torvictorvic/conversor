<?php
declare(strict_types=1);

namespace App;

final class Cache
{
    private static function dir(): string {
        $d = sys_get_temp_dir() . '/currency-cache';
        if (!is_dir($d)) @mkdir($d, 0777, true);
        return $d;
    }

    public static function get(string $key, int $ttl): ?array {
        $file = self::dir() . '/' . sha1($key) . '.json';
        if (!is_file($file)) return null;
        $age = time() - filemtime($file);
        if ($age > $ttl) return null;
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function set(string $key, array $value): void {
        $file = self::dir() . '/' . sha1($key) . '.json';
        @file_put_contents($file, json_encode($value, JSON_UNESCAPED_SLASHES));
    }
}
