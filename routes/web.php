<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductAndServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/contactnya', [ContactController::class, 'viewContackForm']);
Route::post('/createContact', [ContactController::class, 'createContact']);
Route::get('/getContact', [ContactController::class, 'getContact']);

//get product & 

Route::get('/list_productAndService', [ProductAndServiceController::class, 'viewProduct']);