<?php

namespace App\Http\Controllers;

use App\Servics\ConfigXero;
use Illuminate\Http\Request;
use App\Models\DataJamaah;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class ContactController extends Controller
{


    protected $configXero;

    public function __construct()
    {
        // $this->configXero = new ConfigXero();
    }

    public function viewContackForm()
    {
        return view('contact');
    }

     public function getContactLocal()
    {
        // 1. Ambil Data Lokal
       // dd(33);
        $dataLocal = DataJamaah::select(
            "no_ktp",
            "title",
            "tempat_lahir",
            "estimasi_berangkat",
            "leader",
            "id_status",
            "nama_jamaah",
            "alamat_jamaah",
            "jenis_vaksin",
            "tgl_vaksin_1",
            "tgl_vaksin_2",
            "hp_jamaah",
            "no_tlp",
            "keterangan",
            "created_at"
        )
        ->limit(100)
        ->get();

        // 2. Ambil Data dari Xero
        $responseXero = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'), // Sebaiknya pakai env()
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Contacts');

        if ($responseXero->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal ambil data Xero'], 500);
        }

        $xeroContacts = $responseXero->json()['Contacts'];

        // 3. Buat List Nama Kontak Xero (Lower Case) untuk Pengecekan Cepat
        // Menggunakan array_column atau mapping agar mudah dicek
        $existingXeroNames = [];
        foreach ($xeroContacts as $contact) {
            $existingXeroNames[] = strtolower($contact['Name']);
        }

        // 4. Filter Data Lokal yang Belum Ada di Xero
        $newContactsPayload = [];

        foreach ($dataLocal as $jamaah) {
            $namaJamaahLower = strtolower($jamaah->nama_jamaah);

            if (!in_array($namaJamaahLower, $existingXeroNames)) {

                $newContactsPayload[] = [
                    "Name" => $jamaah->nama_jamaah, // Gunakan nama asli (bukan lower) untuk display
                    //"AccountNumber" => $jamaah->no_ktp,
                    "DefaultCurrency" => "IDR",
                    "Addresses" => [
                        [
                            "AddressType" => "STREET",
                            "AddressLine1" => $jamaah->alamat_jamaah
                        ]
                    ],
                    "Phones" => [
                        [
                            "PhoneType" => "MOBILE",
                            "PhoneNumber" => $jamaah->hp_jamaah
                        ]
                    ]
                ];
            }
        }

        $tot_data = count($newContactsPayload);
        $responseData = [];

        // 5. Kirim ke Xero (BULK CREATE) jika ada data baru
        if ($tot_data > 0) {
            // Xero API bisa menerima array contacts sekaligus
            $payload = ["Contacts" => $newContactsPayload];

            $saveResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Contacts', $payload);

            if ($saveResponse->successful()) {
                $responseData = $saveResponse->json();
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal simpan ke Xero',
                    'details' => $saveResponse->body()
                ], 400);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $tot_data > 0 ? "Berhasil menambahkan $tot_data kontak baru." : "Tidak ada kontak baru untuk ditambahkan.",
            'total_added' => $tot_data,
        ], 200);
    }

    public function getContact(Request $request)
    {

        try {
            // $accessToken = $this->configXero->getValidAccessToken();
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Contacts');

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function createContact(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Contacts', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }



}
