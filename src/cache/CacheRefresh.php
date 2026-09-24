<?php

class CacheRefresh
{
    public static function isFresh(?string $path, int $hours): bool
    {
        if ($path === null || !is_file($path)) {
            return false;
        }

        return (time() - filemtime($path)) < ($hours * 3600);
    }

    public static function startIfStale(string $binName, string $path, int $hours): bool
    {
        if (self::isFresh($path, $hours)) {
            return false;
        }

        return self::start($binName);
    }

    public static function start(string $binName): bool
    {
        $safe = preg_replace('/[^a-z0-9-]/', '', $binName) ?: 'cache';
        $lock = APP_ROOT . '/storage/' . $safe . '.refresh.lock';
        if (is_file($lock) && (time() - filemtime($lock)) < 3600) {
            return false;
        }

        file_put_contents($lock, gmdate('c'));
        $script = APP_ROOT . '/bin/' . $safe . '.php';
        if (!is_file($script)) {
            return false;
        }

        $log = APP_ROOT . '/storage/' . $safe . '-refresh.log';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' fetch';
        exec($command . ' > ' . escapeshellarg($log) . ' 2>&1 &');
        return true;
    }
}
