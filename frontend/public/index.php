<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server' && ($_SERVER['REQUEST_URI'] ?? '') === '/assets/app.css') {
    return false;
}

ini_set('display_errors', '0');
header_remove('X-Powered-By');

require __DIR__ . '/../../vendor/autoload.php';

try {
    $kernel = Fang\Application::kernelFromEnvironment();
} catch (Throwable $exception) {
    error_log('Fang: startup failed with ' . $exception::class);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Service unavailable.\n";

    return;
}

$kernel->handle(Fang\Http\Request::fromGlobals())->send();
