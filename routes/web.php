<?php

use App\Http\Controllers\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

// Rota pública para documentação do sistema
Route::get('/doc-passagem.pdf', function () {
    $path = public_path('doc-passagem.pdf');
    if (file_exists($path)) {
        return response()->file($path);
    }
    abort(404, 'Documentação não encontrada');
})->name('documentation');

Route::middleware(['auth', 'verify.authorization'])->group(function () {

    Route::view('/', 'dashboard.index')->name('home');

    Route::view('/feedback', 'dashboard.feedback')->name('feedback');

    Route::view('/manual', 'manual.index')->name('manual.index');

    // Rotas SBAR - Fora do grupo de administração
    Route::view('/sbar', 'sbar.report.page')->name('sbar.report');

    // Therapeutic Plan – batch cache warm (called by SBAR page after sector loads)
    Route::post('/patient-care/therapeutic-plan/warm',
        [App\Http\Controllers\PatientTherapeuticPlanController::class, 'warmCache'])
        ->name('patient.therapeutic-plan.warm');

    // Sector cache warm – pre-warms other user sectors in the background
    Route::post('/sectors/warm',
        [App\Http\Controllers\SectorWarmController::class, 'warm'])
        ->name('sectors.warm');

    // Legacy endpoint kept for backward compatibility
    Route::get('/sbar/paciente/{attendanceNumber}/recomendacoes',
        [App\Http\Controllers\PatientRecomendacoesController::class, 'load'])
        ->name('sbar.patient.recomendacoes');

    // Avaliações do Turno
    Route::get('/sbar/avaliacoes', [\App\Http\Controllers\ExportController::class, 'showShiftEvaluations'])
        ->name('sbar.evaluations.shift');

    // Relatório de Pendências
    Route::get('/pendencias', [\App\Http\Controllers\PendingEventsReportController::class, 'index'])
        ->middleware('can:ver relatorio pendencias')
        ->name('pending.report');

    // Exportação
    Route::get('/sbar/avaliacoes/exportar', [\App\Http\Controllers\ExportController::class, 'exportShiftEvaluations'])
        ->name('sbar.evaluations.export');

    // Preferências do Usuário - Configurar hospitais/setores/leitos
    Route::get('/minhas-preferencias', [SystemConfigurationController::class, 'index'])
        ->name('user.preferences.index');
    Route::post('/minhas-preferencias', [SystemConfigurationController::class, 'update'])
        ->name('user.preferences.update');

    /* Rotas de Administração do Sistema */

    Route::prefix('administracao')->group(function () {

        // Histórico de anotações arquivadas
        Route::middleware('can:ver historico chat')->prefix('historico')->group(function () {
            Route::get('/', [\App\Http\Controllers\ChatArchiveController::class, 'index'])->name('chat.archive.index');
            Route::get('/dt', [\App\Http\Controllers\ChatArchiveController::class, 'datatables'])->name('chat.archive.datatables');
            Route::get('/data', [\App\Http\Controllers\ChatArchiveController::class, 'clientData'])->name('chat.archive.client-data');
            Route::get('/{nr}', [\App\Http\Controllers\ChatArchiveController::class, 'show'])->name('chat.archive.show');
        });

        Route::middleware('can:ver usuarios')->prefix('usuarios')->group(function () {
            Route::get('/', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
            Route::get('search-user', [\App\Http\Controllers\UserController::class, 'searchUserAD']);
            Route::post('create-user', [\App\Http\Controllers\UserController::class, 'createUser'])->middleware('can:criar usuarios')->name('users.create');
            Route::post('update-user', [\App\Http\Controllers\UserController::class, 'updateUser'])->middleware('can:editar usuarios')->name('users.update');
            Route::post('block-user', [\App\Http\Controllers\UserController::class, 'blockUser'])->middleware('can:bloquear usuarios')->name('users.block');
            Route::post('access-user', [\App\Http\Controllers\UserController::class, 'accessAs'])->middleware('can:acessar como')->name('users.access.as');
        });

        Route::middleware('can:ver perfis')->prefix('perfis')->group(function () {
            Route::get('/', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profiles.index');
            Route::post('create', [\App\Http\Controllers\ProfileController::class, 'create'])->middleware('can:criar perfis')->name('profiles.create');
            Route::post('update', [\App\Http\Controllers\ProfileController::class, 'edit'])->middleware('can:editar perfis')->name('profiles.edit');
        });

    });

});

require __DIR__.'/auth.php';
