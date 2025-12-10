<?php

namespace App\Http\Controllers;

use App\Servics\ConfigXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class TaxRateController extends Controller
{


   
    public function getTaxRate(Request $request)
    {

     $response = Http::withHeaders([ 'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',])
        ->get('https://api.xero.com/api.xro/2.0/TaxRates', [
            'where' => 'Status=="ACTIVE"' 
        ]);

        // 3. Return JSON ke Frontend
        return response()->json($response->json());
    }

}
