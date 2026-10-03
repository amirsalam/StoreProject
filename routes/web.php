<?php

use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\BrandingController as AdminBrandingController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\FileStorageController as AdminFileStorageController;
use App\Http\Controllers\Admin\LicensingController as AdminLicensingController;
use App\Http\Controllers\Admin\MailSettingsController as AdminMailSettingsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentGatewayController as AdminPaymentGatewayController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Payments\CmiController;
use App\Http\Controllers\Payments\PayPalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUploadController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Resources\ApiReferenceController;
use App\Http\Controllers\Resources\StatusController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Workspace\BillingController as WorkspaceBillingController;
use App\Http\Controllers\Workspace\InvoiceController as WorkspaceInvoiceController;
use App\Http\Controllers\Workspace\PaymentGatewayController as WorkspacePaymentGatewayController;
use App\Http\Controllers\Workspace\ProjectController as WorkspaceProjectController;
use App\Http\Controllers\Workspace\TaskController as WorkspaceTaskController;
use App\Http\Controllers\Workspace\TeamController as WorkspaceTeamController;
use App\Http\Controllers\Workspace\VendorController as WorkspaceVendorController;
use App\Http\Controllers\Workspace\VendorProductController as WorkspaceVendorProductController;
use App\Services\CartService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::inertia('about', 'about')->name('about');
Route::inertia('customers', 'customers')->name('customers');

// Legal. The terms page is a marked draft outline, not a policy — it sends
// robots: noindex until real wording replaces it (see pages/legal/terms.tsx).
Route::inertia('terms', 'legal/terms')->name('terms');
Route::inertia('privacy', 'legal/privacy')->name('privacy');
Route::inertia('license', 'legal/license')->name('license');
Route::inertia('refunds', 'legal/refunds')->name('refunds');

// Resources. Docs and guides are marked draft outlines like the legal pages;
// the API reference and status page are built from live routes and checks.
Route::inertia('docs', 'resources/docs')->name('docs');
Route::inertia('guides', 'resources/guides')->name('guides');
Route::get('api-reference', ApiReferenceController::class)->name('api-reference');
Route::get('status', StatusController::class)->name('status');

// Public contact form. Messages are stored (admin inbox) and emailed only
// when contact.notify_to is configured; the POST is rate limited.
Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Public blog. Only published posts are reachable; see BlogController.
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// Public vendor storefront.
Route::get('store/{vendor:slug}', [StoreController::class, 'show'])->name('store.show');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart', [CartController::class, 'add'])->name('cart.add');
// {line}: product id + purchase options, e.g. 12, 12-x (extended license), 12-s (extended support).
Route::patch('cart/items/{line}', [CartController::class, 'update'])->where('line', CartService::LINE_PATTERN)->name('cart.update');
Route::delete('cart/items/{line}', [CartController::class, 'destroy'])->where('line', CartService::LINE_PATTERN)->name('cart.destroy');
Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // What the customer bought: license keys + downloads (files are only
    // served through purchases.download, after an ownership check).
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/downloads/{download}', [PurchaseController::class, 'download'])->name('purchases.download');

    // Product file uploads: a signed URL for cloud storage, or chunks to this server.
    Route::post('uploads/product-file', [ProductUploadController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('uploads.product-file');
    Route::post('uploads/product-file/{token}/chunk', [ProductUploadController::class, 'chunk'])
        ->middleware('throttle:600,1')
        ->where('token', '[A-Za-z0-9]{48}')
        ->name('uploads.product-file.chunk');

    // Checkout — cart -> order + Stripe PaymentIntent -> confirmation.
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('checkout/{order:order_number}/confirmation', [CheckoutController::class, 'confirmation'])
        ->name('checkout.confirmation');

    // PayPal: the buyer's return (approved / cancelled) and "try again".
    Route::get('checkout/{order:order_number}/paypal/return', [PayPalController::class, 'return'])->name('checkout.paypal.return');
    Route::get('checkout/{order:order_number}/paypal/cancel', [PayPalController::class, 'cancel'])->name('checkout.paypal.cancel');
    Route::get('checkout/{order:order_number}/paypal', [PayPalController::class, 'pay'])->name('checkout.paypal.pay');
});

// CMI hosted payment (Morocco). The redirect needs the buyer's session; the
// return URLs and callback are POSTed by CMI, so they are CSRF-exempt (see
// bootstrap/app.php) and verified by the Store Key hash instead of the session.
Route::get('checkout/{order:order_number}/cmi', [CmiController::class, 'redirect'])
    ->middleware('auth')
    ->name('checkout.cmi.redirect');
Route::match(['get', 'post'], 'checkout/{order:order_number}/cmi/ok', [CmiController::class, 'ok'])->name('checkout.cmi.ok');
Route::match(['get', 'post'], 'checkout/{order:order_number}/cmi/fail', [CmiController::class, 'fail'])->name('checkout.cmi.fail');
Route::post('payments/cmi/callback', [CmiController::class, 'callback'])->name('payments.cmi.callback');

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
        // The workspace's Stripe gateway (checkout keys), managed from the billing page.
        Route::put('billing/gateway', [WorkspacePaymentGatewayController::class, 'update'])->name('billing.gateway.update');
        Route::post('billing/gateway/test', [WorkspacePaymentGatewayController::class, 'test'])->name('billing.gateway.test');

        // Vendor store — open + manage your own storefront.
        Route::get('vendor', [WorkspaceVendorController::class, 'edit'])->name('vendor.edit');
        Route::post('vendor', [WorkspaceVendorController::class, 'store'])->name('vendor.store');
        Route::put('vendor', [WorkspaceVendorController::class, 'update'])->name('vendor.update');

        // The seller's own products (with the file buyers download) and sales.
        Route::resource('products', WorkspaceVendorProductController::class)->except(['show']);
        Route::get('sales', [WorkspaceVendorProductController::class, 'sales'])->name('sales.index');
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
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');

        // Outgoing email (SMTP) — replaces MAIL_* in .env.
        Route::get('mail', [AdminMailSettingsController::class, 'edit'])->name('mail.edit');
        Route::put('mail', [AdminMailSettingsController::class, 'update'])->name('mail.update');
        Route::post('mail/test', [AdminMailSettingsController::class, 'test'])->name('mail.test');

        // Store-wide Extended License rule.
        Route::get('licensing', [AdminLicensingController::class, 'edit'])->name('licensing.edit');
        Route::put('licensing', [AdminLicensingController::class, 'update'])->name('licensing.update');

        // Where product files live (server disk or S3 / Google Cloud Storage / R2 …).
        Route::get('storage', [AdminFileStorageController::class, 'edit'])->name('storage.edit');
        Route::put('storage', [AdminFileStorageController::class, 'update'])->name('storage.update');
        Route::post('storage/test', [AdminFileStorageController::class, 'test'])->name('storage.test');
        Route::resource('blog-posts', AdminBlogPostController::class)->except(['show']);

        // Contact inbox.
        Route::get('contact', [AdminContactMessageController::class, 'index'])->name('contact.index');
        Route::patch('contact/{contactMessage}', [AdminContactMessageController::class, 'update'])->name('contact.update');
        Route::delete('contact/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact.destroy');

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
