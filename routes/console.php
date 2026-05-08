<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Arquiva mensagens com mais de 30 dias (chat_messages → chat_messages_archive)
// Executa diariamente às 03:00
Schedule::command('chat:cleanup --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Remove jobs falhos com mais de 168h (7 dias) da fila
Schedule::command('queue:prune-failed --hours=168')
    ->weekly();

// Limpa caches de dados de setor SBAR a cada hora para forçar re-carregamento
// (demografia, escalas, pendências, multidisciplinar, etc.)
Schedule::call(function () {
    $keys = DB::table('nurse_handover_beds')
        ->distinct()
        ->pluck('sector_id');

    $prefixes = ['sector_demographics_', 'sector_scales_', 'sector_clinical_',
        'sector_multi_', 'sector_surgery_', 'sector_pending_fast_',
        'sector_handover_'];

    foreach ($keys as $sectorId) {
        foreach ($prefixes as $prefix) {
            Cache::forget($prefix.$sectorId);
        }
    }
})->hourly()->name('sbar-cache-clear')->withoutOverlapping();
