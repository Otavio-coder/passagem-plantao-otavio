<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware( [ 'auth', 'verify.authorization' ] )->group( function () {

    Route::view( '/', 'index' )->name( 'home' );

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
            Route::post('/update', [SystemConfigurationController::class, 'updateConfigurations'])->name('system-configuration.update');
            Route::post('/cascade-preview', [SystemConfigurationController::class, 'getCascadePreview'])->name('system-configuration.cascade-preview');
            Route::get('/load-manual-beds', [SystemConfigurationController::class, 'loadManualBeds'])->name('system-configuration.load-manual-beds');
            Route::get('/sectors-for-hospital', [SystemConfigurationController::class, 'getSectorsForHospital'])->name('system-configuration.sectors-for-hospital');
            Route::get('/beds-for-sector', [SystemConfigurationController::class, 'getBedsForSector'])->name('system-configuration.beds-for-sector');
            Route::get('/sector-count-for-hospital', [SystemConfigurationController::class, 'getSectorCountForHospital'])->name('system-configuration.sector-count-for-hospital');
        });

        Route::view( 'logs', 'vendor.log-viewer.index' )->middleware('can:ver logs')->name( 'logs' );

        Route::get('/sbar', function() {
            return view('sbar.report');
        })->name('sbar.report');
    });

});

// Model Testing Routes
Route::get('/test/sbar-model', function () {
    return view('tests.sbar-model');
});
Route::post('/test/sbar-model/debug', [App\Http\Controllers\SbarReportController::class, 'debugModelMethod'])->name('test.sbar-model.debug');

Route::get('teste', function (){
});

require __DIR__.'/auth.php';
