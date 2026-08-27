<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;
use Monolog\LogRecord;
use Nexus\Nexus;

/**
 * Switches all handlers on a Monolog instance to JSON format.
 *
 * Used by the ``json`` log channel for production structured logging.
 * Each line is a self-contained JSON object with datetime, level,
 * channel, message, context, extra, and request_id — ready for
 * ingestion by ELK, Loki, Datadog, or similar log pipelines.
 */
final class JsonLogFormatter
{
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Logger) {
            return;
        }

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formatter->includeStacktraces();

        $requestId = 'NO_REQUEST_ID';
        $nexus = Nexus::instance();
        if ($nexus) {
            $requestId = $nexus->getRequestId();
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }

        // Inject request_id into every log record's extra data.
        $monolog->pushProcessor(function (LogRecord $record) use ($requestId): LogRecord {
            $record->extra['request_id'] = $requestId;

            return $record;
        });
    }
}
