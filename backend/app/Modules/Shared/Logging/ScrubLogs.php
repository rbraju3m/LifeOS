<?php

namespace App\Modules\Shared\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * Log channel `tap` (config/logging.php) that adds ScrubSensitiveData to a channel.
 */
final class ScrubLogs
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new ScrubSensitiveData);
        }
    }
}
