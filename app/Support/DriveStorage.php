<?php

declare(strict_types=1);

namespace App\Support;

final class DriveStorage
{
    public static function isAvailable(): bool
    {
        return class_exists('Google_Client') && class_exists('Google_Service_Drive');
    }
}
