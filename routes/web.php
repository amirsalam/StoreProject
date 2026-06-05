<?php

use App\Http\Controllers\Admin\BrandingController as AdminBrandingController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Workspace\InvoiceController as WorkspaceInvoiceController;
use App\Http\Controllers\Workspace\ProjectController as WorkspaceProjectController;
use App\Http\Controllers\Workspace\TaskController as WorkspaceTaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('cart/items/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('cart/items/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth'])
    ->prefix('workspace')
    ->name('workspace.')
    ->group(function () {
        Route::get('projects', [WorkspaceProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [WorkspaceProjectController::class, 'store'])->name('projects.store');
        Route::put('projects/{project:slug}', [WorkspaceProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project:slug}', [WorkspaceProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('tasks', [WorkspaceTaskController::class, 'index'])->name('tasks.index');
        Route::post('tasks', [WorkspaceTaskController::class, 'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [WorkspaceTaskController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [WorkspaceTaskController::class, 'destroy'])->name('tasks.destroy');

        Route::get('invoices', [WorkspaceInvoiceController::class, 'index'])->name('invoices.index');
        Route::post('invoices', [WorkspaceInvoiceController::class, 'store'])->name('invoices.store');
        Route::post('invoices/{invoice}/send', [WorkspaceInvoiceController::class, 'markSent'])->name('invoices.send');
        Route::post('invoices/{invoice}/paid', [WorkspaceInvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::delete('invoices/{invoice}', [WorkspaceInvoiceController::class, 'destroy'])->name('invoices.destroy');
    });

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('products', AdminProductController::class)->except(['show']);

        Route::get('branding', [AdminBrandingController::class, 'edit'])->name('branding.edit');
        Route::post('branding', [AdminBrandingController::class, 'update'])->name('branding.update');
        Route::delete('branding/logo', [AdminBrandingController::class, 'destroyLogo'])->name('branding.logo.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role.update');
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
