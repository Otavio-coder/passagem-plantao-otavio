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

    Route::view('/', 'index')->name('home');

    Route::view('/feedback', 'feedback')->name('feedback');

    // Rotas SBAR - Fora do grupo de administração
    Route::view('/passagem-de-plantao', 'sbar.report')->name('sbar.report');

    // CPOE do Paciente - Usado dentro do SBAR
    Route::get('/passagem-de-plantao/paciente/{attendanceNumber}/cpoe',
        [App\Http\Controllers\PatientCpoeController::class, 'load'])
        ->name('sbar.patient.cpoe');

    // Avaliações do Turno
    Route::get('/passagem-de-plantao/avaliacoes', [\App\Http\Controllers\ExportController::class, 'showShiftEvaluations'])
        ->name('sbar.evaluations.shift');

    // Exportação
    Route::get('/passagem-de-plantao/avaliacoes/exportar', [\App\Http\Controllers\ExportController::class, 'exportShiftEvaluations'])
        ->name('sbar.evaluations.export');

    // Preferências do Usuário - Configurar hospitais/setores/leitos
    Route::get('/minhas-preferencias', [SystemConfigurationController::class, 'index'])
        ->name('user.preferences.index');
    Route::post('/minhas-preferencias', [SystemConfigurationController::class, 'update'])
        ->name('user.preferences.update');

    /* Rotas de Administração do Sistema */

    Route::prefix('administracao')->group(function () {

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
