<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Ferramenta de perfilamento de performance para identificar gargalos
 *
 * Uso:
 * $profiler = new PerformanceProfiler('operation-name');
 * $profiler->checkpoint('fase 1');
 * ... código ...
 * $profiler->checkpoint('fase 2');
 * ... código ...
 * $profiler->report();
 */
class PerformanceProfiler
{
    private array $checkpoints = [];
    private string $startTime;
    private string $operationName;
    private int $warningThreshold; // em ms

    public function __construct(string $operationName, int $warningThresholdMs = 1000)
    {
        $this->operationName = $operationName;
        $this->warningThreshold = $warningThresholdMs;
        $this->startTime = hrtime(true);
        $this->checkpoints[] = [
            'name' => 'START',
            'time' => $this->startTime,
            'elapsed' => 0,
        ];
    }

    /**
     * Registra um checkpoint
     */
    public function checkpoint(string $name): void
    {
        $now = hrtime(true);
        $elapsed = intval(($now - $this->startTime) / 1e6);

        $this->checkpoints[] = [
            'name' => $name,
            'time' => $now,
            'elapsed' => $elapsed,
        ];
    }

    /**
     * Retorna array com todos os checkpoints e deltas
     */
    public function getCheckpoints(): array
    {
        $result = [];
        for ($i = 0; $i < count($this->checkpoints); $i++) {
            $current = $this->checkpoints[$i];
            $previous = $this->checkpoints[$i - 1] ?? null;

            $delta = $previous
                ? intval(($current['time'] - $previous['time']) / 1e6)
                : 0;

            $result[] = [
                'checkpoint' => $current['name'],
                'total_ms' => $current['elapsed'],
                'delta_ms' => $delta,
                'is_slow' => $delta > ($this->warningThreshold / 2),
            ];
        }

        return $result;
    }

    /**
     * Retorna o tempo total decorrido
     */
    public function getTotalMs(): int
    {
        if (empty($this->checkpoints)) {
            return 0;
        }

        $last = end($this->checkpoints);

        return $last['elapsed'];
    }

    /**
     * Log formatado para debug
     */
    public function report(bool $logToFile = true): array
    {
        $checkpoints = $this->getCheckpoints();
        $totalMs = $this->getTotalMs();
        $isSlow = $totalMs > $this->warningThreshold;

        $report = [
            'operation' => $this->operationName,
            'total_ms' => $totalMs,
            'is_slow' => $isSlow,
            'checkpoints' => $checkpoints,
        ];

        if ($logToFile) {
            $logLevel = $isSlow ? 'warning' : 'info';
            $statusIcon = $isSlow ? '⚠️ SLOW' : '✅ OK';

            $message = sprintf(
                '%s %s [%dms]',
                $statusIcon,
                $this->operationName,
                $totalMs
            );

            Log::channel('performance')->$logLevel($message, $report);
        }

        return $report;
    }

    /**
     * Retorna formatação em tabela ASCII
     */
    public function table(): string
    {
        $checkpoints = $this->getCheckpoints();
        $totalMs = $this->getTotalMs();

        $lines = [];
        $lines[] = '';
        $lines[] = '╔════════════════════════════════════════════════════════════╗';
        $lines[] = sprintf('║ 📊 Performance: %s', str_pad($this->operationName, 36) . '║');
        $lines[] = '╠═══════════════════════════════╦═════════════╦════════════╣';
        $lines[] = '║ Checkpoint                    ║ Total (ms)  ║ Delta (ms) ║';
        $lines[] = '╠═══════════════════════════════╬═════════════╬════════════╣';

        foreach ($checkpoints as $cp) {
            $name = str_pad(substr($cp['checkpoint'], 0, 30), 31);
            $total = str_pad((string) $cp['total_ms'], 11, ' ', STR_PAD_LEFT);
            $delta = str_pad((string) $cp['delta_ms'], 10, ' ', STR_PAD_LEFT);

            $slowMarker = $cp['is_slow'] ? ' 🐢' : '';
            $lines[] = sprintf('║ %s║ %s║ %s║%s', $name, $total, $delta, $slowMarker);
        }

        $lines[] = '╠═══════════════════════════════╩═════════════╩════════════╣';
        $lines[] = sprintf('║ ⏱️  TOTAL: %sms', str_pad((string) $totalMs, 51, ' ', STR_PAD_LEFT));
        $lines[] = '╚════════════════════════════════════════════════════════════╝';

        return implode("\n", $lines);
    }
}
