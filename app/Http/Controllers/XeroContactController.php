<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class XeroContactController extends Controller
{
    private $tokenFile = 'xero_token.json';

    //get tanpa bearer token
    // =========================================================================
    // BAGIAN 1: PROSES MENDAPATKAN TOKEN PERTAMA KALI (LOGIN)
    // =========================================================================

    /**
     * 1. Redirect User ke Halaman Login Xero
     */
    public function connect()
    {
        $scope = 'offline_access accounting.contacts accounting.settings';

        $url = 'https://login.xero.com/identity/connect/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => env('XERO_CLIENT_ID'),
            'redirect_uri' => env('XERO_REDIRECT_URL'),
            'scope' => $scope,
            'state' => 'SAFD2142432'
        ]);

        return redirect($url);
    }

    /**
     * 2. Callback: Menukar "Code" dengan "Token" & Simpan ke JSON
     */
    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return 'Gagal: Tidak ada code dari Xero.';
        }

        $response = Http::asForm()->withBasicAuth(env('XERO_CLIENT_ID'), env('XERO_CLIENT_SECRET'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'redirect_uri' =>  env('XERO_REDIRECT_URL'),
            ]);

        if ($response->successful()) {
            $tokens = $response->json();
            $this->saveToken($tokens); // Simpan ke file JSON
            // $res_json = [
            //     "pesan" =>"Koneksi Berhasil! Token tersimpan di storage/app/private/{$this->tokenFile}. Sekarang coba akses /xero/contacts",
            // ];
            return "Koneksi Berhasil! Token tersimpan di storage/app/private/{$this->tokenFile}. Sekarang coba akses /xero/contacts";
        }

        return response()->json($response->json(), 400);
    }


    public function getContacts()
    {
        // 1. Ambil Token Valid (Otomatis refresh jika expired)
        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        // 2. Ambil Tenant ID (Biasanya ID organisasi pertama)
        $tenantId = $this->getTenantId($tokenData['access_token']);

        // 3. Request Data Contacts
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json'
        ])->get('https://api.xero.com/api.xro/2.0/Contacts');

        return response()->json($response->json());
    }

    // =========================================================================
    // BAGIAN 3: LOGIKA CORE TOKEN (PRIVATE HELPER)
    // =========================================================================

    /**
     * Cek apakah token expired, jika ya refresh, jika tidak return yang ada
     */
    private function getValidToken()
    {
        if (!Storage::exists($this->tokenFile)) return null;

        $tokens = json_decode(Storage::get($this->tokenFile), true);
        if (empty($tokens)) return null;

        // Cek Expired (Kita beri buffer 2 menit sebelum benar-benar habis)
        // 'expires_at' adalah timestamp yang kita buat saat save
        $now = Carbon::now()->timestamp;

        if (isset($tokens['expires_at']) && $now >= ($tokens['expires_at'] - 120)) {
            // Token Expired: Lakukan Refresh
            return $this->refreshToken($tokens['refresh_token']);
        }

        // Token Masih Aman
        return $tokens;
    }

    /**
     * Menukar Refresh Token lama dengan Access Token Baru
     */
    private function refreshToken($currentRefreshToken)
    {
        $response = Http::asForm()->withBasicAuth(env('XERO_CLIENT_ID'), env('XERO_CLIENT_SECRET'))
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $currentRefreshToken
            ]);

        if ($response->successful()) {
            $newTokens = $response->json();
            $this->saveToken($newTokens); // Timpa file JSON dengan yang baru
            return $newTokens;
        }

        // Jika refresh gagal (misal refresh token expired juga), hapus file agar user login ulang
        Storage::delete($this->tokenFile);
        return null;
    }

    /**
     * Simpan token ke File JSON + Tambah Timestamp Expired
     */
    private function saveToken($tokens)
    {
        // Tambahkan field 'expires_at' manual agar mudah dicek nanti
        // expires_in biasanya 1800 detik (30 menit)
        $tokens['expires_at'] = Carbon::now()->timestamp + $tokens['expires_in'];

        Storage::put($this->tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    /**
     * Helper ambil Tenant ID
     */
    private function getTenantId($accessToken)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->get('https://api.xero.com/connections');

        if ($response->successful()) {
            $data = $response->json();
            return $data[0]['tenantId'] ?? null; // Ambil tenant pertama
        }
        return null;
    }
}
