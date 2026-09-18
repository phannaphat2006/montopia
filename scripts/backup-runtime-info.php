<?php

use Illuminate\Contracts\Console\Kernel;

// CLI helper for the local backup runner. Deliberately excludes every credential.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
echo json_encode([
    'driver' => $connection,
    'host' => config('database.connections.'.$connection.'.host'),
    'port' => config('database.connections.'.$connection.'.port'),
], JSON_THROW_ON_ERROR);
