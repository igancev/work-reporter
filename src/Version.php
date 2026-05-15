<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

final class Version
{
    private const string DEFAULT = 'dev';

    public static function fromFile(string $path): string
    {
        if (!file_exists($path)) {
            return self::DEFAULT;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return self::DEFAULT;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return self::DEFAULT;
        }

        $version = $decoded['version'] ?? null;
        if (!is_string($version) || $version === '') {
            return self::DEFAULT;
        }

        return $version;
    }
}
