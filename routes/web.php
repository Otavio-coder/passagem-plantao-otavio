<?php

use App\Http\Controllers\ChatArchiveController;
use App\Http\Controllers\HandoverMetricsController;
use App\Http\Controllers\PatientPrescriptionsController;
use App\Http\Controllers\PendingEventsReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SectorPreferencesController;
use App\Http\Controllers\SectorWarmController;
use App\Http\Controllers\SystemConfigurationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Página offline do PWA (acessada pelo service worker quando sem conexão)
Route::get('/offline', fn () => view('errors.offline'))->name('offline');

// Rota pública para documentação do sistema
Route::get('/doc-passagem.pdf', function () {
    $path = public_path('doc-passagem.pdf');
    if (file_exists($path)) {
        return response()->file($path);
    }
    abort(404, 'Documentação não encontrada');
})->name('documentation');

Route::middleware(['auth', 'verify.authorization'])->group(function () {

    // Session heartbeat — keeps Redis session alive on idle tablets
    Route::post('/session/heartbeat', function () {
        session()->put('heartbeat_at', now()->toISOString());

        return response()->json(['ok' => true]);
    })->name('session.heartbeat');

    Route::view('/', 'dashboard.index')->name('home');
    Route::post('/sector-preferences', [SectorPreferencesController::class, 'save'])->name('sector-preferences.save');

    Route::view('/feedback', 'dashboard.feedback')->name('feedback');

    Route::view('/manual', 'manual.index')->name('manual.index');

    // Rotas SBAR - Fora do grupo de administração
    Route::view('/sbar', 'sbar.report.page')->name('sbar.report');

    Route::get('/huddle', fn () => view('huddle.coming-soon'))
        ->name('huddle.report');

    // Prescriptions – batch cache warm (called by SBAR page after sector loads)
    Route::post('/patient-care/prescriptions/warm',
        [PatientPrescriptionsController::class, 'warmCache'])
        ->name('patient.prescriptions.warm');

    // Patient details – batch cache warm (called by SBAR page so modal navigation is instant)
    Route::post('/patient-care/details/warm',
        [PatientPrescriptionsController::class, 'warmDetailsCache'])
        ->name('patient.details.warm');

    // Sector cache warm – pre-warms other user sectors in the background
    Route::post('/sectors/warm',
        [SectorWarmController::class, 'warm'])
        ->name('sectors.warm');

    // Preferências do Usuário - Configurar hospitais/setores/leitos
    Route::get('/minhas-preferencias', [SystemConfigurationController::class, 'index'])
        ->name('user.preferences.index');
    Route::post('/minhas-preferencias', [SystemConfigurationController::class, 'update'])
        ->name('user.preferences.update');

    /* Rotas de Administração do Sistema */

    Route::prefix('administracao')->group(function () {

        // Relatório de Pendências
        Route::get('/pendencias', [PendingEventsReportController::class, 'index'])
            ->name('pending.report');
        Route::post('/pendencias/refresh', [PendingEventsReportController::class, 'refresh'])
            ->name('pending.report.refresh');
        Route::get('/pendencias/export', [PendingEventsReportController::class, 'export'])
            ->name('pending.report.export');

        // Histórico de anotações arquivadas
        Route::middleware('can:ver historico chat')->prefix('historico')->group(function () {
            Route::get('/', [ChatArchiveController::class, 'index'])->name('chat.archive.index');
            Route::get('/dt', [ChatArchiveController::class, 'datatables'])->name('chat.archive.datatables');
            Route::get('/data', [ChatArchiveController::class, 'clientData'])->name('chat.archive.client-data');
            Route::get('/passagens/metricas', [HandoverMetricsController::class, 'index'])->name('handover.metrics');
            Route::get('/{nr}', [ChatArchiveController::class, 'show'])->name('chat.archive.show');
        });

        Route::middleware('can:ver usuarios')->prefix('usuarios')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('users.index');
            Route::get('search-user', [UserController::class, 'searchUserAD']);
            Route::post('create-user', [UserController::class, 'createUser'])->middleware('can:criar usuarios')->name('users.create');
            Route::post('update-user', [UserController::class, 'updateUser'])->middleware('can:editar usuarios')->name('users.update');
            Route::post('block-user', [UserController::class, 'blockUser'])->middleware('can:bloquear usuarios')->name('users.block');
            Route::post('access-user', [UserController::class, 'accessAs'])->middleware('can:acessar como')->name('users.access.as');
        });

        Route::middleware('can:ver perfis')->prefix('perfis')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])->name('profiles.index');
            Route::post('create', [ProfileController::class, 'create'])->middleware('can:criar perfis')->name('profiles.create');
            Route::post('update', [ProfileController::class, 'edit'])->middleware('can:editar perfis')->name('profiles.edit');
        });

    });

});

require __DIR__.'/auth.php';
