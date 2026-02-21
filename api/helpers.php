<?php
// helpers.php

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

function setupLogger($logFilePath, $channelName = 'app') {
    $log = new Logger($channelName);

    // Vercel logs everything from stderr/stdout to its dashboard.
    // We use php://stderr for error-level visibility.
    $handler = new StreamHandler('php://stderr', Logger::DEBUG);
    
    $formatter = new LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        "Y-m-d H:i:s.u",
        true, 
        true  
    );
    
    $handler->setFormatter($formatter);
    $log->pushHandler($handler);

    return $log;
}