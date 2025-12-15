<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentParams; // Pastikan model ini ada
use Carbon\Carbon;
use App\ConfigRefreshXero;

class InvoiceItem2Controller extends Controller
{
    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero;
    // Helper Headers
    private function getHeaders()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        //dd($tokenData);
        return [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * MAIN FUNCTION: SAVE ITEM (Add/Edit Row)
     * Menangani logika Paid Invoice secara otomatis.
     */
    public function saveItem(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'invoice_id'    => 'required|string',
            'item_code'     => 'required|string',
            'qty'           => 'required|numeric',
            'price'         => 'required|numeric',
            'disc_amount'   => 'nullable|numeric',
            'agent_id'      => 'nullable|string',
            'divisi_id'     => 'nullable|string',
            // 'status_invoice' => 'required|string', // Tidak wajib, kita cek langsung dari API Xero
        ]);

        $invoiceId = preg_replace('/[^a-zA-Z0-9-]/', '', $request->invoice_id);

        try {
            // ------------------------------------------------------------------
            // LANGKAH 1: AMBIL DATA INVOICE & CEK STATUS PEMBAYARAN
            // ------------------------------------------------------------------
            $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);

            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero'], 500);
            }

            $invoiceData = $response->json()['Invoices'][0];
            $currentLineItems = $invoiceData['LineItems'] ?? [];
            $payments = $invoiceData['Payments'] ?? [];
            $paymentBackups = [];

            // Jika ada pembayaran, lakukan Backup & Void (Delete) Payment
            dd($payment);
            if (!empty($payments)) {
                foreach ($payments as $pay) {
                    $payId = $pay['PaymentID'];
                    // Backup data payment detail dari Xero ke DB Lokal
                    $this->backupPaymentData($payId);
                    // Simpan ID untuk direstore nanti
                    $paymentBackups[] = $payId;
                    // Hapus Payment di Xero agar Invoice bisa diedit
                    $this->voidPaymentInXero($payId);
                }

                // Refresh data invoice setelah payment dihapus (Status harusnya jadi AUTHORISED/DRAFT)
                $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
                $invoiceData = $response->json()['Invoices'][0];
                $currentLineItems = $invoiceData['LineItems'] ?? [];
            }

            // ------------------------------------------------------------------
            // LANGKAH 2: PROSES LOGIKA ITEM (Add/Edit)
            // ------------------------------------------------------------------
            $subtotal = $request->qty * $request->price;
            $discountRate = 0;
            if ($subtotal > 0 && $request->disc_amount > 0) {
                $discountRate = ($request->disc_amount / $subtotal) * 100;
            }

            // Susun Tracking
            $tracking = [];
            if ($request->filled('agent_id')) {
                $tracking[] = ['Name' => 'Agent', 'Option' => '', 'TrackingOptionID' => $request->agent_id];
            }
            if ($request->filled('divisi_id')) {
                $tracking[] = ['Name' => 'Divisi', 'Option' => '', 'TrackingOptionID' => $request->divisi_id];
            }

            // Object Line Item Baru
            $newLineItem = [
                'ItemCode'      => $request->item_code,
                'Description'   => $request->description,
                'Quantity'      => $request->qty,
                'UnitAmount'    => $request->price,
                'DiscountRate'  => round($discountRate, 4),
                'AccountCode'   => $request->account_code ?? '200',
                'TaxType'       => $request->tax_type ?? 'NONE',
                'Tracking'      => $tracking
            ];

            if ($request->filled('line_item_id')) {
                $newLineItem['LineItemID'] = $request->line_item_id;
            }

            // Merge dengan Item Lama
            $updatedLineItems = [];
            $found = false;

            foreach ($currentLineItems as $item) {
                if ($request->filled('line_item_id') && isset($item['LineItemID']) && $item['LineItemID'] == $request->line_item_id) {
                    $updatedLineItems[] = $newLineItem;
                    $found = true;
                } else {
                    // Copy item lama
                    $updatedLineItems[] = [
                        'LineItemID' => $item['LineItemID'],
                        'Quantity'   => $item['Quantity'],
                        'UnitAmount' => $item['UnitAmount'],
                        'ItemCode'   => $item['ItemCode'] ?? null,
                        'Description'=> $item['Description'] ?? null,
                        'AccountCode'=> $item['AccountCode'] ?? null,
                        'TaxType'    => $item['TaxType'] ?? null,
                        'DiscountRate'=> $item['DiscountRate'] ?? 0,
                        'Tracking'   => $item['Tracking'] ?? []
                    ];
                }
            }

            if (!$found && empty($request->line_item_id)) {
                $updatedLineItems[] = $newLineItem;
            }

            // ------------------------------------------------------------------
            // LANGKAH 3: UPDATE INVOICE KE XERO
            // ------------------------------------------------------------------
            $payload = [
                'InvoiceID' => $invoiceId,
                'LineItems' => $updatedLineItems
            ];

            $updateResponse = Http::withHeaders($this->getHeaders())
                ->post($this->xeroBaseUrl . '/Invoices/' . $invoiceId, $payload);

            if ($updateResponse->failed()) {
                // Jika gagal, kembalikan payment (opsional, tapi disarankan)
                // $this->restorePayments($invoiceId, $paymentBackups);
                return response()->json(['status' => 'error', 'message' => 'Xero Update Failed: ' . $updateResponse->body()], 400);
            }

            $updatedInvoice = $updateResponse->json()['Invoices'][0];

            // ------------------------------------------------------------------
            // LANGKAH 4: RESTORE PAYMENT (Bayar Ulang)
            // ------------------------------------------------------------------
            if (!empty($paymentBackups)) {
                foreach ($paymentBackups as $oldPayId) {
                    $this->restorePayment($invoiceId, $oldPayId);
                    // Hapus backup setelah sukses restore
                    PaymentParams::where('payments_id', $oldPayId)->delete();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice updated successfully',
                'data' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // HELPER FUNCTIONS (PRIVATE)
    // =========================================================================

    /**
     * Ambil detail payment dari Xero dan simpan ke DB Lokal
     */
    private function backupPaymentData($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . "/Payments/$paymentId");

        if ($response->failed()) throw new \Exception("Gagal Backup Payment: " . $response->body());

        $data = $response->json();
        if (empty($data['Payments'])) return;

        $payment = $data['Payments'][0];
        if ($payment['Status'] == 'DELETED') return; // Jangan backup yang sudah dihapus

        // Format Tanggal Xero (/Date(123123)/) ke Y-m-d
        $date = $this->parseXeroDate($payment["Date"]);

      //  dd($payment["Account"]["AccountID"]);
        PaymentParams::updateOrCreate(
            ['payments_id' => $paymentId],
            [
                'invoice_id' => $payment["Invoice"]["InvoiceID"],
                'account_code' => $payment["Account"]["AccountID"],//$payment['Account']['Code'] ?? '200', // Bisa Code atau AccountID
                'account_id' => $payment['Account']['AccountID'] ?? null,
                'date' => $date,
                'amount' => $payment["Amount"],
                'reference' => $payment["Reference"] ?? "Re-payment API"
            ]
        );
    }

    /**
     * Hapus (Void) Payment di Xero
     */
    private function voidPaymentInXero($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . "/Payments/$paymentId", ["Status" => "DELETED"]);

        if ($response->failed() && $response->status() != 404) {
             throw new \Exception("Gagal Void Payment: " . $response->body());
        }
    }

    /**
     * Buat Ulang (Restore) Payment di Xero setelah Invoice diedit
     */
    private function restorePayment($invoiceId, $oldPaymentId)
    {
        $backup = PaymentParams::where('payments_id', $oldPaymentId)->first();
        if (!$backup) return;

        // 1. Ambil Data Invoice Terbaru dari Xero untuk Cek Sisa Tagihan (AmountDue)
        $resInv = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . "/Invoices/$invoiceId");

        if ($resInv->failed()) {
            Log::error("Gagal Cek Tagihan Baru saat Restore Payment: " . $resInv->body());
            return;
        }

        $invData = $resInv->json();
        // Pastikan kita mengambil invoice yang benar (index 0)
        $invoiceXero = $invData['Invoices'][0];

        $totNya = 0;
        foreach ($resInv['Invoices'] as $key => $value) {
            foreach ($value["LineItems"] as $key2 => $value2) {
                $totNya +=(float)  $value2["LineAmount"];
            }
        }
        // AmountDue adalah sisa yang BELUM dibayar.
        // InvoiceTotal adalah Total Tagihan Invoice.
        // Karena kita baru saja menghapus payment, AmountDue harusnya = Total Tagihan (kecuali ada payment lain).
        $amountDue = (float)$invoiceXero['AmountDue'];
        $backupAmount = (float)$backup->amount;

        // 2. Logika Penentuan Nominal Pembayaran
        // Skenario A: Invoice sudah lunas (AmountDue 0) oleh payment lain? Skip.
        if ($amountDue <= 0) {
            Log::info("Skip Restore Payment $oldPaymentId: Invoice $invoiceId sudah lunas (AmountDue: 0).");
            return;
        }

        // Skenario B: Nominal Backup LEBIH BESAR dari Sisa Tagihan
        // Contoh: Dulu bayar 1jt. Invoice diedit jadi cuma 500rb.
        // Maka kita hanya boleh bayar 500rb (AmountDue), sisanya dianggap hangus/refund manual.
        $payAmount = $backupAmount;

        if ($backupAmount > $amountDue) {
            Log::info("Adjustment Payment: Nominal Backup ($backupAmount) > Sisa Tagihan ($amountDue). Membayar sebesar Sisa Tagihan.");
            $payAmount = $amountDue;
        }

        // 3. Kirim Payment Baru
        $payloadPayment = [
            "Payments" => [[
                "Invoice" => ["InvoiceID" => $invoiceId],
                // "Account" => ["Code" => $backup->account_code],
                "Account" => ["AccountID" => $backup->account_code],
                "Date" => $backup->date,
                "Amount" => $totNya,//$payAmount, // Gunakan hasil perhitungan di atas
                "Reference" => $backup->reference
            ]]
        ];

        // Fallback Account ID jika Code kosong
        if (empty($backup->account_code) && !empty($backup->account_id)) {
             $payloadPayment['Payments'][0]['Account'] = ["AccountID" => $backup->account_id];
             unset($payloadPayment['Payments'][0]['Account']['Code']);
        }

        $resPay = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . '/Payments', $payloadPayment);

        if ($resPay->failed()) {
            Log::error("Edit Peritem | Gagal Restore Payment $oldPaymentId: " . $resPay->body());
        } else {
            Log::info(" Edit Peritem | Sukses Restore Payment $oldPaymentId sebesar $payAmount");
        }
    }

    /**
     * Helper Format Date Xero (/Date(151515)/) -> Y-m-d
     */
    private function parseXeroDate($xeroDate) {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d'); // Default today
    }


    public function deleteItem(Request $request, $lineId)
    {
        $invoiceId = $request->invoice_id;

        // 1. Ambil Data Awal
        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
        if ($response->failed()) return response()->json(['status' => 'error'], 500);

        $invoiceData = $response->json()['Invoices'][0];
        $payments = $invoiceData['Payments'] ?? [];
        $paymentBackups = [];

        // 2. Backup & Void Payment (Jika Status PAID/Partial)
        //dd($payments);
        if (!empty($payments)) {
            Log::info("Menghapus item dari Paid Invoice: $invoiceId");

            foreach ($payments as $pay) {
                $payId = $pay['PaymentID'];
                $this->backupPaymentData($payId); // Backup ke DB
                $paymentBackups[] = $payId;       // Simpan ID untuk restore
                $this->voidPaymentInXero($payId); // Hapus di Xero
            }

            // REFRESH DATA INVOICE (PENTING!)
            // Kita harus ambil ulang data invoice agar statusnya sudah berubah jadi DRAFT/AUTHORISED
            // dan kita mendapatkan LineItems yang terbaru.
            $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
            $invoiceData = $response->json()['Invoices'][0];
        }

        // 3. Filter Item (Hapus Item dari Array)
        $currentLineItems = $invoiceData['LineItems'] ?? [];
        $newLineItems = [];

        foreach ($currentLineItems as $item) {
            // Masukkan item ke array baru HANYA JIKA ID-nya TIDAK SAMA dengan yang mau dihapus
            if ($item['LineItemID'] != $lineId) {
                $newLineItems[] = $item;
            }
        }

        // 4. Kirim Update ke Xero (LineItems Baru)
        $payload = [
            'InvoiceID' => $invoiceId,
            'LineItems' => $newLineItems
        ];

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $updateResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' .$tokenData["access_token"],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->xeroBaseUrl . '/Invoices/' . $invoiceId, $payload);

        if ($updateResponse->failed()) {
             // Opsional: Restore payment jika update gagal agar data tidak rusak
             // $this->restoreBatchPayments($invoiceId, $paymentBackups);
             return response()->json(['status' => 'error', 'message' => 'Gagal update Xero: ' . $updateResponse->body()], 400);
        }

        // 5. Restore Payment (Jika tadi ada payment)
        if (!empty($paymentBackups)) {
            Log::info("Mengembalikan Payment untuk Invoice: $invoiceId");
            foreach ($paymentBackups as $oldPayId) {
                $this->restorePayment($invoiceId, $oldPayId);
                // Hapus data backup dari DB agar tidak menumpuk
                PaymentParams::where('payments_id', $oldPayId)->delete();
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Item deleted from Xero']);
    }
}
