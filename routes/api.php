<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\ProductAndServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('getCodeBeforeToken', [ConfigController::class, 'getAuthUrl']);
Route::post('getToken', [ConfigController::class, 'getToken']);

Route::post('/create-data', [ContactController::class, 'createContact']);
Route::get('/get-data', [ContactController::class, 'getContact']);

//proudct
Route::post('/save-data-product', [ProductAndServiceController::class, 'updateProduct']);
Route::get('/get-data-product', [ProductAndServiceController::class, 'getProduct']);
Route::get('/get-by-id/{id}', [ProductAndServiceController::class, 'getProductById']);

//invoice
Route::get('/getInvoiceByIdPaket/{itemCode}', [InvoicesController::class, 'getInvoiceByIdPaket']);
Route::get('/getDetailInvoice/{idInvoice}', [InvoicesController::class, 'getDetailInvoice']);
Route::get('/get-invoices', [InvoicesController::class, 'getAllInvoices']);
Route::post('/submitUpdateinvoices', [InvoicesController::class, 'updateInvoiceSelected']);

