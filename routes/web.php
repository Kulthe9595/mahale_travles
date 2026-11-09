<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillPreviewController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'app.user'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::resource('cars', CarController::class)->except('show');
    Route::resource('drivers', DriverController::class)->except('show');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);

    Route::resource('trips', TripController::class)->except('destroy');
    Route::post('/trips/{trip}/invoice', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('/invoices/{invoice}/refresh', [InvoiceController::class, 'refresh'])->name('invoices.refresh');
    Route::post('/invoices/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('invoices.reissue');
    Route::post('/trips/{trip}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::post('/bill-preview', BillPreviewController::class)->name('bill.preview');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
