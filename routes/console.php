<?php

use App\Services\PatientData\PatientDataLoader;
use App\Services\PendingEvents\PatientPendingEventsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Recalcula contagem de mensagens arquivadas por plantonista (domingos 03:00)
Schedule::command('cache:rebuild-archive-stats')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();

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

// Materializa o dia do Huddle (Red2Green): cria os registros do dia em vermelho
// para cada paciente ativo dos setores. Roda em dias úteis às 06:30, antes do
// pré-aquecimento das 06:45. Fim de semana/feriado é ignorado pelo próprio comando.
Schedule::command('huddle:open-day')
    ->weekdays()
    ->at('06:30')
    ->withoutOverlapping()
    ->runInBackground();

/**
 * Retorna todos os sector IDs ativos: union de nurse_handover_beds + user_sector_preferences.
 * Garante cobertura de setores que não possuem leitos cadastrados no handover.
 *
 * @return int[]
 */
$activeSectorIds = function (): array {
    $fromBeds = DB::table('nurse_handover_beds')->distinct()->pluck('sector_id')->map(fn ($v) => (int) $v);
    $fromPrefs = DB::table('user_sector_preferences')->distinct()->pluck('sector_code')->map(fn ($v) => (int) $v);

    return $fromBeds->merge($fromPrefs)->filter()->unique()->values()->all();
};

// Limpa e re-aquece caches de dados de setor a cada hora.
// Aquecimento de pending events é síncrono (sem queue) para garantir que o cache fique
// quente imediatamente após a limpeza — sem depender de queue workers.
Schedule::call(function () use ($activeSectorIds) {
    $sectorIds = $activeSectorIds();
    $pendingService = app(PatientPendingEventsService::class);

    $staticPrefixes = ['sector_demographics_', 'sector_scales_', 'sector_clinical_',
        'sector_multi_', 'sector_surgery_'];

    foreach ($sectorIds as $sectorId) {
        // Limpa dados estáticos (demografia, escalas, clínico, multidisciplinar, cirurgia)
        foreach ($staticPrefixes as $prefix) {
            Cache::forget($prefix.$sectorId);
        }

        // Re-aquece pending events agora (síncrono) para o próximo usuário encontrar cache quente
        Cache::forget("sector_pending_fast_{$sectorId}");
        try {
            $pendingService->getPendingEventsForSector($sectorId);
        } catch (Throwable $e) {
            Log::warning('[schedule:sbar-cache-clear] Falha ao aquecer pending events', [
                'sector_id' => $sectorId,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->hourly()->name('sbar-cache-clear')->withoutOverlapping();

// Pre-aquece TODOS os dados de setor 15 min antes de cada troca de turno
// (06:45 → manhã 07h, 12:45 → tarde 13h, 18:45 → noite 19h).
// Síncrono — sem queue — para garantir execução mesmo sem workers.
$warmAllSectors = function () use ($activeSectorIds) {
    $sectorIds = $activeSectorIds();
    $pendingService = app(PatientPendingEventsService::class);

    foreach ($sectorIds as $sectorId) {
        try {
            PatientDataLoader::forSector($sectorId)
                ->include('demographics', 'scales', 'clinical', 'multidisciplinary', 'surgery')
                ->get();
            $pendingService->getPendingEventsForSector($sectorId);
        } catch (Throwable $e) {
            Log::warning('[schedule:sbar-pre-warm] Falha ao aquecer setor', [
                'sector_id' => $sectorId,
                'error' => $e->getMessage(),
            ]);
        }
    }
};

Schedule::call($warmAllSectors)->dailyAt('06:45')->name('sbar-pre-warm-morning')->withoutOverlapping();
Schedule::call($warmAllSectors)->dailyAt('12:45')->name('sbar-pre-warm-afternoon')->withoutOverlapping();
Schedule::call($warmAllSectors)->dailyAt('18:45')->name('sbar-pre-warm-night')->withoutOverlapping();
