<?php

use App\Http\Controllers\ProfileController;
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
