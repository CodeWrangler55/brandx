<?php
declare(strict_types=1);

final class JsonStorage
{
    private static function path(string $name): string
    {
        if (!preg_match('/^[a-z0-9-]+\.json$/', $name)) {
            throw new InvalidArgumentException('Invalid storage filename.');
        }
        $base = trim((string) config('APP_STORAGE_DIR', ''));
        return ($base !== '' ? rtrim($base, DIRECTORY_SEPARATOR) : APP_ROOT . '/storage') . DIRECTORY_SEPARATOR . $name;
    }

    public static function read(string $name, mixed $default = []): mixed
    {
        $path = self::path($name);
        if (!is_file($path)) {
            return $default;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : $default;
    }

    public static function update(string $name, callable $mutator, mixed $default = []): mixed
    {
        $path = self::path($name);
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock local storage.');
        }
        try {
            $current = self::read($name, $default);
            $next = $mutator($current);
            $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded === false) {
                throw new RuntimeException('Unable to encode local storage.');
            }
            $temporary = $path . '.' . bin2hex(random_bytes(5)) . '.tmp';
            if (file_put_contents($temporary, $encoded . PHP_EOL, LOCK_EX) === false || !rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to save local storage.');
            }
            return $next;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
