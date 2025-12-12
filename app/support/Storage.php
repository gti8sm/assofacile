<?php

declare(strict_types=1);

namespace App\Support;

final class Storage
{
    public static function privatePath(string $relative = ''): string
    {
        $base = base_path('storage/private');
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }

        if ($relative === '') {
            return $base;
        }

        $relative = ltrim($relative, '/\\');
        return $base . DIRECTORY_SEPARATOR . $relative;
    }
}
