<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class RoadmapController
{
    public static function index(): void
    {
        $path = base_path('ROADMAP.md');
        $content = is_file($path) ? (string)file_get_contents($path) : "# Roadmap\n\nAucune entrée pour le moment.\n";

        require base_path('views/roadmap/index.php');
    }
}
