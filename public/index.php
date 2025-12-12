<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Http\Kernel;

$kernel = new Kernel();
$kernel->handle();
