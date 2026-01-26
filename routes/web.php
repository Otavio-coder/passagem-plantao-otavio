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

Route::middleware( [ 'auth', 'verify.authorization' ] )->group( function () {

    Route::view( '/', 'index' )->name( 'home' );

    Route::view('/feedback', 'feedback')->name('feedback');

    /* Rotas de Administração do Sistema */

    Route::prefix( 'administracao' )->group( function () {

        Route::middleware( 'can:ver usuarios' )->prefix( 'usuarios' )->group( function () {
            Route::get( '/', [ \App\Http\Controllers\UserController::class, 'index' ] )->name( 'users.index' );
            Route::get( 'search-user', [ \App\Http\Controllers\UserController::class, 'searchUserAD' ] );
            Route::post( 'create-user', [ \App\Http\Controllers\UserController::class, 'createUser' ] )->middleware('can:criar usuarios')->name( 'users.create' );
            Route::post( 'update-user', [ \App\Http\Controllers\UserController::class, 'updateUser' ] )->middleware('can:editar usuarios')->name( 'users.update' );
            Route::post( 'access-user', [ \App\Http\Controllers\UserController::class, 'accessAs' ] )->middleware( 'can:acessar como' )->name( 'users.access.as' );
        });

        Route::middleware( 'can:ver perfis' )->prefix( 'perfis' )->group( function () {
            Route::get( '/', [ \App\Http\Controllers\ProfileController::class, 'index' ] )->name( 'profiles.index' );
            Route::post( 'create', [ \App\Http\Controllers\ProfileController::class, 'create' ] )->middleware('can:criar perfis')->name( 'profiles.create' );
            Route::post( 'update', [ \App\Http\Controllers\ProfileController::class, 'edit' ] )->middleware('can:editar perfis')->name( 'profiles.edit' );
        });

        Route::middleware('can:configurar sistema')->prefix('configuracoes')->group(function () {
            Route::get('/', [SystemConfigurationController::class, 'index'])->name('system-configuration.index');
            Route::post('/update', [SystemConfigurationController::class, 'update'])->name('system-configuration.update');
            Route::post('/cascade-preview', [SystemConfigurationController::class, 'getCascadePreview'])->name('system-configuration.cascade-preview');
            Route::post('/system-configuration/sectors-by-hospital', [SystemConfigurationController::class, 'getSectorsByHospital'])->name('system-configuration.sectors-by-hospital');
            Route::post('/system-configuration/beds-by-sector', [SystemConfigurationController::class, 'getBedsBySector'])->name('system-configuration.beds-by-sector');
            Route::get('/load-manual-beds', [SystemConfigurationController::class, 'loadManualBeds'])->name('system-configuration.load-manual-beds');
            Route::get('/sectors-for-hospital', [SystemConfigurationController::class, 'getSectorsForHospital'])->name('system-configuration.sectors-for-hospital');
            Route::get('/beds-for-sector', [SystemConfigurationController::class, 'getBedsForSector'])->name('system-configuration.beds-for-sector');
            Route::get('/sector-count-for-hospital', [SystemConfigurationController::class, 'getSectorCountForHospital'])->name('system-configuration.sector-count-for-hospital');
        });

        Route::middleware(['auth'])->get('/chat/messages', function (\Illuminate\Http\Request $request) {
            $patientId = $request->get('patientId');
            $shift = $request->get('shift');
            $date = $request->get('date', now()->toDateString());
            $repo = new \App\Repositories\MySQL\Chat\ChatRepository();
            $messages = $repo->getMessages($patientId, $shift, $date, 50);
            return response()->json(['messages' => $messages]);
        });

        Route::get('/sbar/avaliacoes-turno',
            [App\Http\Controllers\ChatAuditoriaController::class, 'avaliacoesTurno'])
            ->name('sbar.avaliacoes.turno');

        Route::get('/sbar/avaliacoes-turno/export',
            [App\Http\Controllers\ExportController::class, 'exportAvaliacoesTurno'])
            ->name('sbar.avaliacoes.export');

        Route::view( 'logs', 'vendor.log-viewer.index' )->middleware('can:ver logs')->name( 'logs' );

        Route::get('/sbar', function() {
            return view('sbar.report');
        })->name('sbar.report');

    });

});

require __DIR__.'/auth.php';
