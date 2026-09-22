<?php

use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\BrandingController as AdminBrandingController;
use App\Http\Controllers\Admin\PaymentGatewayController as AdminPaymentGatewayController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Workspace\BillingController as WorkspaceBillingController;
use App\Http\Controllers\Workspace\InvoiceController as WorkspaceInvoiceController;
use App\Http\Controllers\Workspace\ProjectController as WorkspaceProjectController;
use App\Http\Controllers\Workspace\TaskController as WorkspaceTaskController;
use App\Http\Controllers\Workspace\TeamController as WorkspaceTeamController;
use App\Http\Controllers\Workspace\VendorController as WorkspaceVendorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::inertia('about', 'about')->name('about');
Route::inertia('customers', 'customers')->name('customers');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Public blog. Only published posts are reachable; see BlogController.
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// Public vendor storefront.
Route::get('store/{vendor:slug}', [StoreController::class, 'show'])->name('store.show');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('cart/items/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('cart/items/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Checkout — cart -> order + Stripe PaymentIntent -> confirmation.
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('checkout/{order:order_number}/confirmation', [CheckoutController::class, 'confirmation'])
        ->name('checkout.confirmation');
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

        Route::get('team', [WorkspaceTeamController::class, 'index'])->name('team.index');
        Route::post('team/invitations', [WorkspaceTeamController::class, 'invite'])->name('team.invitations.store');
        Route::delete('team/invitations/{invitation}', [WorkspaceTeamController::class, 'revoke'])->name('team.invitations.revoke');

        Route::get('billing', [WorkspaceBillingController::class, 'index'])->name('billing.index');
        Route::post('billing/change-plan', [WorkspaceBillingController::class, 'changePlan'])->name('billing.change');
        Route::post('billing/portal', [WorkspaceBillingController::class, 'portal'])->name('billing.portal');
        Route::post('billing/cancel', [WorkspaceBillingController::class, 'cancel'])->name('billing.cancel');

        // Vendor store — open + manage your own storefront.
        Route::get('vendor', [WorkspaceVendorController::class, 'edit'])->name('vendor.edit');
        Route::post('vendor', [WorkspaceVendorController::class, 'store'])->name('vendor.store');
        Route::put('vendor', [WorkspaceVendorController::class, 'update'])->name('vendor.update');
    });

// Public Stripe webhook — no auth, signature verified inside the
// controller. CSRF exemption is configured globally in bootstrap/app.php.
Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// Public invitation accept flow — anyone with the token can land here,
// auth is gated at the accept step.
Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('products', AdminProductController::class)->except(['show']);
        Route::resource('blog-posts', AdminBlogPostController::class)->except(['show']);

        Route::get('branding', [AdminBrandingController::class, 'edit'])->name('branding.edit');
        Route::post('branding', [AdminBrandingController::class, 'update'])->name('branding.update');
        Route::delete('branding/logo', [AdminBrandingController::class, 'destroyLogo'])->name('branding.logo.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role.update');

        // Payment gateways management.
        Route::post('payment-gateways/reorder', [AdminPaymentGatewayController::class, 'reorder'])->name('payment-gateways.reorder');
        Route::post('payment-gateways/{paymentGateway}/toggle', [AdminPaymentGatewayController::class, 'toggle'])->name('payment-gateways.toggle');
        Route::post('payment-gateways/{paymentGateway}/default', [AdminPaymentGatewayController::class, 'setDefault'])->name('payment-gateways.default');
        Route::post('payment-gateways/{paymentGateway}/test', [AdminPaymentGatewayController::class, 'test'])->name('payment-gateways.test');
        Route::resource('payment-gateways', AdminPaymentGatewayController::class)->except(['show']);
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
