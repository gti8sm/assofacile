<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Session;

final class ChangelogController
{
    public static function index(): void
    {
        $path = base_path('CHANGELOG.md');
        $content = is_file($path) ? (string)file_get_contents($path) : "# Changelog\n\nAucune version pour le moment.\n";

        require base_path('views/changelog/index.php');
    }
}
