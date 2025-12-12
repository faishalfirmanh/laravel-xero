<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\InvoicesDuplicateController;
use App\Http\Controllers\ProductAndServiceController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\XeroContactController;
use App\Http\Controllers\InvoiceItem2Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

//local contact
Route::get('/get-contact-local', [ContactController::class, 'getContactLocal']);

Route::get('getCodeBeforeToken', [ConfigController::class, 'getAuthUrl']);
Route::post('getToken', [ConfigController::class, 'getToken']);
Route::get('/xero/login', [ConfigController::class, 'redirect']);
Route::get('/xero/callback', [ConfigController::class, 'callback']);

Route::post('/create-data', [ContactController::class, 'createContact']);


//xero refresh token
// 1. Route untuk inisiasi login (Jalankan ini saat xero_token.json masih kosong)
Route::get('/xero/connect', [XeroContactController::class, 'connect']);
// 2. Route Callback (Wajib sama dengan Redirect URI di Xero Developer)
Route::get('/xero/callback', [XeroContactController::class, 'callback']);
// 3. Route untuk melihat data (Ini yang akan dipakai sehari-hari)
Route::get('/xero/contacts', [XeroContactController::class, 'getContacts']);
//---------xero refresh token---------




Route::post('/save-data-product', [ProductAndServiceController::class, 'updateProduct']);
//Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/get-data', [ContactController::class, 'getContact']);
//});
//proudct
Route::get('/get-data-product', [ProductAndServiceController::class, 'getProduct']);
Route::get('/get-data-no-limit', [ProductAndServiceController::class, 'getProductAllNoBearer']);
Route::get('/get-by-id/{id}', [ProductAndServiceController::class, 'getProductById']);

//payment
Route::post('/updateDeletedPayment/{payment_id}/{status}', [PaymentController::class, 'updatePaymentStatus']);
Route::get('/getDetailPayment/{idPayment}', [InvoicesController::class, 'getDetailPayment']);
Route::post('/createPayments', [PaymentController::class, 'createPayments']);

//invoice
Route::get('/getInvoiceByIdPaket/{itemCode}', [InvoicesController::class, 'getInvoiceByIdPaket']);
Route::get('/getDetailInvoice/{idInvoice}', [InvoicesController::class, 'getDetailInvoice']);
Route::get('/get-invoices', [InvoicesController::class, 'getAllInvoices']);
Route::post('/submitUpdateinvoices', [InvoicesDuplicateController::class, 'updateInvoiceSelected']);//update semua select submit

//save per rows
Route::post('/invoice/item/save', [InvoiceItem2Controller::class, 'saveItem'])->name('invoice.item.save');
Route::delete('/invoice/item/{id}', [InvoiceItem2Controller::class, 'deleteItem'])->name('invoice.item.delete');
//tax rate
Route::get('/tax_rate', [TaxRateController::class, 'getTaxRate']);

//kategory (tracking)
Route::get('/get_divisi', [TrackingController::class, 'getKategory']);
Route::get('/get_agent', [TrackingController::class, 'getAgent']);

//getAcountDetailAcount Invoice
Route::get('/getAllAccount', [PaymentController::class, 'getGroupedAccounts']);

Route::post('/updatePerbaris/{parent_id}/{amount_input}/{line_item_id}', [InvoicesController::class, 'updateInvoicePerRows']);//untuk testing
