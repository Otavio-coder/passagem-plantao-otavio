<?php

namespace App\Services\PendingEvents;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all pending event handlers.
 *
 * Encapsulates the common boilerplate shared by every handler:
 *   - Empty-guard
 *   - Microtime instrumentation
 *   - Chunking the attendance list (configurable via $chunkSize)
 *   - Placeholder generation for Oracle IN-clauses
 *   - Log::info timing entry
 *
 * Concrete handlers only implement:
 *   - handlerName(): string  — label used in log output
 *   - processChunk(array &$results, array $chunk): void  — one DB round-trip per chunk
 */
abstract class AbstractPendingHandler implements PendingEventHandler
{
    /** Oracle IN-clause limit per query. Max safe Oracle IN-list is ~1000; 200 is a good balance. */
    protected int $chunkSize = 200;

    abstract protected function handlerName(): string;

    abstract protected function processChunk(array &$results, array $chunk): void;

    final public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start = microtime(true);

        foreach (array_chunk($attendances, $this->chunkSize) as $chunk) {
            $this->processChunk($results, $chunk);
        }

        try {
            Log::info(sprintf(
                '[PendingEvents] %s: %.2fms',
                $this->handlerName(),
                (microtime(true) - $start) * 1000
            ));
        } catch (\Throwable) {}
    }

    /** Returns a comma-separated list of '?' placeholders matching the chunk size. */
    protected function placeholders(array $chunk): string
    {
        return implode(',', array_fill(0, count($chunk), '?'));
    }
}
