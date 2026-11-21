<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// صفحة الموظفين (بديل الشورت كود) — وأيضًا ?et_file=... لفتح المرفقات المحمية
Route::get('/', [AppController::class, 'index'])->name('tracker');

// دخول مديري النظام
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// لوحة الإدارة
Route::middleware('auth')->group(function () {
    Route::redirect('/admin', '/admin/employee-tracker');

    // حسابات المديرين
    Route::get('/admin/et-admins', [AdminUsersController::class, 'index'])->name('admins.index');
    Route::post('/admin/et-admins', [AdminUsersController::class, 'store'])->name('admins.store');
    Route::post('/admin/et-admins/{user}/password', [AdminUsersController::class, 'updatePassword'])->name('admins.password');
    Route::post('/admin/et-admins/{user}/delete', [AdminUsersController::class, 'destroy'])->name('admins.destroy');

    Route::match(['get', 'post'], '/admin/{page}', [AdminController::class, 'show'])
        ->where('page', 'employee-tracker|et-[a-z-]+')
        ->name('admin.page');
});
