<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\XeroAuthService;
use Illuminate\Support\Facades\Http;
class ProductAndServiceController extends Controller
{
    public function viewProduct()
    {
        return view('product');
    }

    private function getTenantId($token)
    {
        $config = \App\Models\ConfigSettingXero::first();
        if($config->xero_tenant_id) return $config->xero_tenant_id;

        $response = Http::withToken($token)->get('https://api.xero.com/connections');
        $tenantId = $response->json()[0]['tenantId'];
        $config->update(['xero_tenant_id' => $tenantId]);
        return $tenantId;
    }

     public function getProductAllNoBearer(XeroAuthService $xeroService)
    {
        try {
            $token = $xeroService->getToken();
        } catch (\Exception $e) {
           return response()->json([
            'status' => 'error',
            'message' => 'Detail Error: ' . $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
        }
        $tenantId = $this->getTenantId($token);
        $response = Http::withToken($token)
            ->withHeaders(['xero-tenant-id' => $tenantId])
            ->get('https://api.xero.com/api.xro/2.0/Items');

        return $response->json();
    }

   public function getProduct(Request $request)
{
    try {
        // 1. Ambil parameter page dan search dari request client
        $page = $request->query('page', 1); // Default page 1
        $search = $request->query('search', ''); // Default kosong

        // 2. Siapkan parameter query untuk Xero
        $queryParams = [
            'page' => $page,
        ];

        // 3. Tambahkan filter search jika ada
        // Xero menggunakan parameter 'where' untuk filtering
        if (!empty($search)) {
            // Mencari berdasarkan Code atau Name (Case Sensitive di Xero)
            // Contoh: Code.Contains("Air") OR Name.Contains("Air")
            $queryParams['where'] = 'Code.Contains("' . $search . '") OR Name.Contains("' . $search . '")';
        }

        // 4. Panggil Xero API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Items', $queryParams);

        // 5. Kembalikan respons
        // Kita kirimkan data page saat ini juga agar frontend tahu
        $data = $response->json();
        $data['current_page'] = (int)$page;

        return response()->json($data ?: ['message' => 'Xero API Error'], $response->status());

    } catch (\Exception $e) {
        return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
    }
}

    public function getProductById($productId)
    {
        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items/' . $productId);

            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function createProduct(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Items', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Items', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

}
