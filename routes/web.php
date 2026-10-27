<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok', 'database' => 'mysql']);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('health');

Route::middleware('throttle:120,15')->group(function (): void {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('/daftar', [PublicController::class, 'create'])->name('registration.create');
    Route::get('/api/public/today-slots', [PublicController::class, 'todaySlots'])->name('registration.today-slots');
    Route::post('/daftar', [PublicController::class, 'store'])->middleware('throttle:8,60')->name('registration.store');
    Route::get('/berhasil', [PublicController::class, 'success'])->name('registration.success');
    Route::get('/cek', [PublicController::class, 'check'])->name('queue.check');
    Route::post('/cek', [PublicController::class, 'lookup'])->name('queue.lookup');
});

Route::get('/bidan', [AdminController::class, 'login'])->name('login');
Route::post('/bidan/login', [AdminController::class, 'authenticate'])->middleware('throttle:admin-login')->name('admin.authenticate');

Route::middleware('auth')->prefix('bidan')->name('admin.')->group(function (): void {
    Route::get('/panel', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::post('/registrations/{id}/status', [AdminController::class, 'updateRegistrationStatus'])->name('registrations.status');
    Route::put('/profile', [AdminController::class, 'updateProfile'])->name('profile.update');
    Route::put('/status', [AdminController::class, 'updatePracticeStatus'])->name('practice.update');
    Route::put('/schedules', [AdminController::class, 'updateSchedules'])->name('schedules.update');
    Route::post('/closures', [AdminController::class, 'storeClosure'])->name('closures.store');
    Route::delete('/closures/{id}', [AdminController::class, 'deleteClosure'])->name('closures.delete');
});