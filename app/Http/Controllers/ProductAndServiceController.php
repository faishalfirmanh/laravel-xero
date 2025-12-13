<?php

namespace App\Http\Controllers;

use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use App\Services\XeroAuthService;
use Illuminate\Support\Facades\Http;

class ProductAndServiceController extends Controller
{
    use ConfigRefreshXero;
    public function viewProduct()
    {
        return view('product');
    }

    private function getTenantId($token)
    {
        $config = \App\Models\ConfigSettingXero::first();
        if ($config->xero_tenant_id)
            return $config->xero_tenant_id;

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
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            // --- KONFIGURASI LIMIT ---
            $limit = 10;           // Kita ingin limit 10
            $xeroBatchSize = 100;  // Xero selalu return 100

            // 1. Ambil input dari frontend
            $frontendPage = (int) $request->query('page', 1);
            $search = $request->query('search', '');

            // 2. LOGIKA MATEMATIKA: Konversi Halaman Frontend ke Halaman Xero
            // Contoh: User minta Page 2 (Item 11-20). Itu masih ada di Xero Page 1.

            // Rumus: Halaman Xero mana yang harus kita panggil?
            $xeroPageTarget = ceil(($frontendPage * $limit) / $xeroBatchSize);
            if ($xeroPageTarget < 1)
                $xeroPageTarget = 1;

            // Rumus: Mulai potong dari index ke berapa?
            // Contoh Page 2: ((2-1)*10) % 100 = 10. Kita mulai ambil dari index 10.
            $offsetInBatch = (($frontendPage - 1) * $limit) % $xeroBatchSize;

            // 3. Siapkan Query ke Xero
            $queryParams = [
                'page' => $xeroPageTarget, // Kirim halaman hasil hitungan, BUKAN halaman frontend
            ];

            if (!empty($search)) {
                $queryParams['where'] = 'Code.Contains("' . $search . '") OR Name.Contains("' . $search . '")';
            }

            // 4. Panggil Xero API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items', $queryParams);

            if ($response->failed()) {
                return response()->json(['message' => 'Xero API Error', 'details' => $response->json()], $response->status());
            }

            $xeroData = $response->json();
            $allItems = $xeroData['Items'] ?? [];

            // 5. SLICING DATA (Kunci agar terlimit 10)
            // Ambil array, mulai dari offset, ambil sebanyak $limit (10)
            $pagedItems = array_slice($allItems, $offsetInBatch, $limit);

            // 6. Cek apakah masih ada halaman selanjutnya
            // Ada next page jika:
            // a. Kita dapat full 10 item saat ini.
            // b. DAN (Masih ada sisa item di batch 100 ini ATAU batch ini penuh 100 yang artinya mungkin ada batch berikutnya)
            $hasMore = count($pagedItems) === $limit && (count($allItems) > ($offsetInBatch + $limit) || count($allItems) === 100);

            // 7. Kembalikan respons dengan struktur yang rapi
            return response()->json([
                'current_page' => $frontendPage,
                'limit' => $limit,
                'total_in_page' => count($pagedItems),
                'has_more' => $hasMore,
                'data' => $pagedItems, // Ini isinya array yang sudah dipotong jadi maks 10
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function getProductById($productId)
    {
        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],//env('BARER_TOKEN'),
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
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
