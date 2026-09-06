<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdvisorAssignmentController;
use App\Http\Controllers\Api\DosenDashboardController;
use App\Http\Controllers\Api\DosenStudentController;
use App\Http\Controllers\Api\MahasiswaDashboardController;
use App\Http\Controllers\Api\PerwalianController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::middleware('password.changed')->group(function () {
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::get('/users', [UserController::class, 'index'])->middleware('role:admin');
        Route::get('/users/identifier-suggestion', [UserController::class, 'identifierSuggestion'])->middleware('role:admin');
        Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
        Route::post('/users/import', [UserController::class, 'import'])->middleware('role:admin');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('role:admin');
        Route::patch('/users/{user}/password', [UserController::class, 'resetPassword'])->middleware('role:admin');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware('role:admin');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('role:admin');

        Route::get('/admin/dashboard', [AdminDashboardController::class, 'show'])->middleware('role:admin');

        Route::get('/advisor-assignments', [AdvisorAssignmentController::class, 'index'])->middleware('role:admin');
        Route::put('/advisor-assignments/{mahasiswa}', [AdvisorAssignmentController::class, 'update'])->middleware('role:admin');

        Route::get('/reports/perwalian', [ReportController::class, 'perwalian'])->middleware('role:admin');

        Route::get('/dosen/dashboard', [DosenDashboardController::class, 'show'])->middleware('role:dosen');
        Route::get('/dosen/mahasiswa-wali', [DosenStudentController::class, 'index'])->middleware('role:dosen');
        Route::get('/dosen/mahasiswa-wali/{mahasiswa}', [DosenStudentController::class, 'show'])->middleware('role:dosen');
        Route::patch('/dosen/perwalians/{perwalian}/status', [DosenStudentController::class, 'updateStatus'])->middleware('role:dosen');

        Route::get('/mahasiswa/dashboard', [MahasiswaDashboardController::class, 'show'])->middleware('role:mahasiswa');
        Route::get('/perwalians', [PerwalianController::class, 'index']);
        Route::post('/perwalians', [PerwalianController::class, 'store'])->middleware('role:mahasiswa');
        Route::put('/perwalians/{perwalian}', [PerwalianController::class, 'update'])->middleware('role:mahasiswa');
        Route::patch('/perwalians/{perwalian}/cancel', [PerwalianController::class, 'cancel'])->middleware('role:mahasiswa');
    });
});
