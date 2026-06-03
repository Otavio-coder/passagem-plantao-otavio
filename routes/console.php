<?php

use App\Jobs\WarmSectorCacheJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('handover:prune-log --days=180')
    ->weekly()
    ->withoutOverlapping()
    ->runInBackground();

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
        'sector_multi_', 'sector_surgery_', 'sector_pending_fast_'];

    foreach ($keys as $sectorId) {
        foreach ($prefixes as $prefix) {
            Cache::forget($prefix.$sectorId);
        }
    }
})->hourly()->name('sbar-cache-clear')->withoutOverlapping();

// Pre-aquece caches de todos os setores 15 minutos antes de cada troca de turno
// (06:45 → manhã 07h, 12:45 → tarde 13h, 18:45 → noite 19h).
// Nurses arriving exactly at shift change find warm caches instead of 10-15s Oracle loads.
$warmAllSectors = function () {
    $sectorIds = DB::table('user_sector_preferences')
        ->distinct()
        ->pluck('sector_code')
        ->map(fn ($v) => (int) $v)
        ->filter()
        ->unique();

    foreach ($sectorIds as $sectorId) {
        WarmSectorCacheJob::dispatch($sectorId, forceStatic: true);
    }
};

Schedule::call($warmAllSectors)->dailyAt('06:45')->name('sbar-pre-warm-morning')->withoutOverlapping();
Schedule::call($warmAllSectors)->dailyAt('12:45')->name('sbar-pre-warm-afternoon')->withoutOverlapping();
Schedule::call($warmAllSectors)->dailyAt('18:45')->name('sbar-pre-warm-night')->withoutOverlapping();
