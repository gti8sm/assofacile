<?php

declare(strict_types=1);

namespace Licensing\Support;

final class LicenseKey
{
    public static function generate(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(16)));
        $chunks = str_split($hex, 4);
        return 'AF-' . implode('-', $chunks);
    }
}
