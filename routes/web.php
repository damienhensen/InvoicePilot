<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices/upload', [InvoiceController::class, 'upload'])->name('invoices.upload');
Route::post('/invoices/upload', [InvoiceController::class, 'process'])->name('invoices.process');
