<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\File\DestroyFileController;
use App\Http\Controllers\File\DownloadFileController;
use App\Http\Controllers\File\IndexFileController;
use App\Http\Controllers\File\ShowUploadFileController;
use App\Http\Controllers\File\UpdateFileGroupController;
use App\Http\Controllers\File\UpdateFilePublicController;
use App\Http\Controllers\FileGroup\DestroyFileGroupController;
use App\Http\Controllers\FileGroup\SearchFileGroupController;
use App\Http\Controllers\FileGroup\ShowFileGroupController;
use App\Http\Controllers\FileGroup\StoreFileGroupController;
use App\Http\Controllers\FileGroup\UpdateFileGroupTagsController;
use App\Http\Controllers\Profile\ShowProfileController;
use App\Http\Controllers\Profile\UpdateOwnPasswordController;
use App\Http\Controllers\Secret\CreateSecretController;
use App\Http\Controllers\Secret\DestroySecretController;
use App\Http\Controllers\Secret\DownloadSecretFileController;
use App\Http\Controllers\Secret\IndexSecretController;
use App\Http\Controllers\Secret\RevealSecretController;
use App\Http\Controllers\Secret\StoreSecretController;
use App\Http\Controllers\SecretGroup\DestroySecretGroupController;
use App\Http\Controllers\SecretGroup\SearchSecretGroupController;
use App\Http\Controllers\SecretGroup\ShowSecretGroupController;
use App\Http\Controllers\SecretGroup\StoreSecretGroupController;
use App\Http\Controllers\SecretGroup\UpdateSecretGroupTagsController;
use App\Http\Controllers\Tus\TusdHookController;
use App\Http\Controllers\User\DestroyUserController;
use App\Http\Controllers\User\IndexUserController;
use App\Http\Controllers\User\StoreUserController;
use App\Http\Controllers\User\UpdateUserController;
use App\Http\Controllers\User\UpdateUserPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::get('download/{file}', DownloadFileController::class)->name('files.download');

Route::post('internal/tus-hooks', TusdHookController::class)
    ->middleware('tusd.hooks')
    ->name('tus.hooks');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('profile', ShowProfileController::class)->name('profile.show');
    Route::patch('profile/password', UpdateOwnPasswordController::class)->name('profile.password');

    Route::get('/', IndexFileController::class)->name('files.index');

    Route::get('upload', ShowUploadFileController::class)->name('files.upload');

    Route::get('files/groups/{fileGroup}', ShowFileGroupController::class)->name('files.group');
    Route::patch('files/{file}/public', UpdateFilePublicController::class)->name('files.public');
    Route::patch('files/{file}/group', UpdateFileGroupController::class)->name('files.group.assign');
    Route::delete('files/{file}', DestroyFileController::class)->name('files.destroy');

    Route::get('file-groups/search', SearchFileGroupController::class)->name('file-groups.search');
    Route::post('file-groups', StoreFileGroupController::class)->name('file-groups.store');
    Route::patch('file-groups/{fileGroup}/tags', UpdateFileGroupTagsController::class)->name('file-groups.tags');
    Route::delete('file-groups/{fileGroup}', DestroyFileGroupController::class)->name('file-groups.destroy');

    Route::get('secrets', IndexSecretController::class)->name('secrets.index');
    Route::get('secrets/create', CreateSecretController::class)->name('secrets.create');
    Route::post('secrets', StoreSecretController::class)->name('secrets.store');
    Route::get('secrets/groups/{secretGroup}', ShowSecretGroupController::class)->name('secrets.group');
    Route::get('secrets/{secret}/reveal', RevealSecretController::class)->name('secrets.reveal');
    Route::get('secrets/{secret}/files/{secretFile}', DownloadSecretFileController::class)->name('secrets.files.download');
    Route::delete('secrets/{secret}', DestroySecretController::class)->name('secrets.destroy');

    Route::get('secret-groups/search', SearchSecretGroupController::class)->name('secret-groups.search');
    Route::post('secret-groups', StoreSecretGroupController::class)->name('secret-groups.store');
    Route::patch('secret-groups/{secretGroup}/tags', UpdateSecretGroupTagsController::class)->name('secret-groups.tags');
    Route::delete('secret-groups/{secretGroup}', DestroySecretGroupController::class)->name('secret-groups.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('users', IndexUserController::class)->name('users.index');
        Route::post('users', StoreUserController::class)->name('users.store');
        Route::patch('users/{user}', UpdateUserController::class)->name('users.update');
        Route::patch('users/{user}/password', UpdateUserPasswordController::class)->name('users.password');
        Route::delete('users/{user}', DestroyUserController::class)->name('users.destroy');
    });
});
