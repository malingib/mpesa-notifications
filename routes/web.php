<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialDashboardController;
use App\Http\Controllers\PaymentReconciliationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\AdminPaymentsController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Middleware\EnsureUserIsAdmin;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// User dashboard (client)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/calculate-earnings', [PaymentController::class, 'calculateEarnings'])->name('payments.calculate-earnings');
    Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
    
    // SMS Templates
    Route::get('/sms-templates', [SmsTemplateController::class, 'index'])->name('sms-templates.index');
    Route::get('/sms-templates/create', [SmsTemplateController::class, 'create'])->name('sms-templates.create');
    Route::post('/sms-templates', [SmsTemplateController::class, 'store'])->name('sms-templates.store');
    Route::get('/sms-templates/{id}/edit', [SmsTemplateController::class, 'edit'])->name('sms-templates.edit');
    Route::put('/sms-templates/{id}', [SmsTemplateController::class, 'update'])->name('sms-templates.update');
    Route::delete('/sms-templates/{id}', [SmsTemplateController::class, 'destroy'])->name('sms-templates.destroy');
    Route::post('/sms-templates/preview', [SmsTemplateController::class, 'preview'])->name('sms-templates.preview');
    
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/sms', [SettingsController::class, 'updateSmsSettings'])->name('settings.update-sms');
    Route::put('/settings/payment', [SettingsController::class, 'updatePaymentSettings'])->name('settings.update-payment');
    Route::post('/settings/test-sms', [SettingsController::class, 'testSmsConnection'])->name('settings.test-sms');
    Route::post('/settings/test-payment', [SettingsController::class, 'testPaymentCredentials'])->name('settings.test-payment');
    Route::post('/settings/register-urls', [SettingsController::class, 'registerUrls'])->name('settings.register-urls');
    Route::get('/settings/balance', [SettingsController::class, 'getBalance'])->name('settings.balance');
    
    // Customers
    Route::resource('customers', CustomerController::class);
    
    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail'])->name('invoices.send-email');
    Route::post('/invoices/{invoice}/send-sms', [InvoiceController::class, 'sendSms'])->name('invoices.send-sms');
    Route::post('/invoices/{invoice}/mark-sent', [InvoiceController::class, 'markAsSent'])->name('invoices.mark-sent');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    
    // Expenses
    Route::resource('expenses', ExpenseController::class);
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])->name('expenses.receipt');
    
    // Financial Dashboard
    Route::prefix('financial')->name('financial.')->group(function () {
        Route::get('/dashboard', [FinancialDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profit-loss', [FinancialDashboardController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/cash-flow', [FinancialDashboardController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/balance-sheet', [FinancialDashboardController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/tax-report', [FinancialDashboardController::class, 'taxReport'])->name('tax-report');
        Route::get('/revenue-trends', [FinancialDashboardController::class, 'revenueTrends'])->name('revenue-trends');
    });
    
    // Payment Reconciliation
    Route::prefix('reconciliation')->name('reconciliation.')->group(function () {
        Route::get('/', [PaymentReconciliationController::class, 'index'])->name('index');
        Route::post('/match', [PaymentReconciliationController::class, 'match'])->name('match');
        Route::post('/unmatch/{payment}', [PaymentReconciliationController::class, 'unmatch'])->name('unmatch');
        Route::post('/auto-match', [PaymentReconciliationController::class, 'autoMatch'])->name('auto-match');
        Route::get('/suggestions/{payment}', [PaymentReconciliationController::class, 'suggestions'])->name('suggestions');
        Route::get('/report', [PaymentReconciliationController::class, 'report'])->name('report');
    });
});

// Admin routes
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [AdminUsersController::class, 'index'])->name('users');
    Route::get('/payments', [AdminPaymentsController::class, 'index'])->name('payments');
    
    // Impersonation routes
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'loginAs'])->name('impersonate');
});

// Impersonation stop route (accessible to all authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::post('/stop-impersonating', [ImpersonationController::class, 'stopImpersonating'])->name('stop-impersonating');
});
