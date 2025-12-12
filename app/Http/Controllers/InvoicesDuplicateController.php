<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Log;

class InvoicesDuplicateController extends Controller
{
    function xeroDateToPhp($xeroDate, $format = 'Y-m-d') {
        if (empty($xeroDate)) return null;
        preg_match('/\/Date\((-?\d+)/', $xeroDate, $matches);
        if (!isset($matches[1])) return null;
        return date($format, $matches[1] / 1000);
    }

    public function updateInvoiceSelected(Request $request)
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        $array = [];
        $errors = [];

        foreach ($data['items'] as $value) {
            try {
                Log::info("=== MULAI PROSES INVOICE: " . $value['no_invoice'] . " ===");

                $hasPayment = ($value['no_payment'] != "kosong" && !empty($value['no_payment']));

                if ($hasPayment) {
                    Log::info("Status: Ada Payment (" . $value['no_payment'] . "). Melakukan Backup & Void.");
                    self::getDetailPayment($value['no_payment']);
                    self::updateInvoicePaidPerRows($value['no_payment']);
                    sleep(2);
                }

                // Update Item
                Log::info("Status: Memulai Update Invoice ke Xero...");
                self::updateInvoicePerRows($value['parentId'], $data['price_update'], $value['lineItemId']);

                if ($hasPayment) {
                    usleep(500000);
                    Log::info("Status: Restore Payment...");
                    self::createPayments($value['parentId'], $value['no_payment']);
                    self::deletedRowInvoiceId($value['no_payment']);
                }

                $array[] = ['no_invoice' => $value['no_invoice'], 'status' => 'Success'];
                Log::info("=== SELESAI SUCCESS ===");

            } catch (\Exception $e) {
                Log::error("ERROR pada Invoice " . $value['no_invoice'] . ": " . $e->getMessage());
                $errors[] = ['no_invoice' => $value['no_invoice'], 'message' => $e->getMessage()];
            }
        }

        if (count($errors) > 0) return response()->json(['success' => $array, 'errors' => $errors], 207);
        return response()->json($array, 200);
    }

    public function deletedRowInvoiceId($paymentsId) {
        $find = PaymentParams::where('payments_id', $paymentsId)->first();
        if($find) $find->delete();
    }

    public function getDetailPayment($idPayment) {
        // ... (Kode sama seperti sebelumnya) ...
        // Agar tidak kepanjangan, bagian ini aman jika data tersimpan di DB
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

        if ($response->failed()) throw new \Exception("Gagal Get Payment: " . $response->body());

        $data = $response->json();
        if (empty($data['Payments'])) return;
        $payment = $data['Payments'][0];
        if ($payment['Status'] == 'DELETED') return;

        self::insertToDb(
            $payment["Amount"],
            $payment['Account']['Code'] ?? $payment['Account']['AccountID'],
            self::xeroDateToPhp($payment["Date"]),
            $payment["Invoice"]["InvoiceID"],
            $payment["Reference"] ?? "Re-payment api",
            $idPayment
        );
    }

    public function insertToDb($amount, $account_code, $date, $invoice_id, $reference_id, $idPayment) {
        PaymentParams::updateOrCreate(
            ['payments_id' => $idPayment],
            ['invoice_id' => $invoice_id, 'account_code' => $account_code, 'date' => $date, 'amount' => $amount, 'reference' => $reference_id]
        );
    }

    public function updateInvoicePaidPerRows($payment_id) {
        // ... (Kode sama) ...
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id", ["Status" => "DELETED"]);

        if ($response->failed() && $response->status() != 404) {
             throw new \Exception("Gagal Hapus Payment: " . $response->body());
        }
    }

    // --- BAGIAN KRUSIAL (DEBUGGING LOGIC) ---
    public function updateInvoicePerRows($parent_id, $amount_input, $line_item_id) {
        $cleanId = str_replace('"', '', $parent_id);

        // 1. Cek Status Invoice
        $maxRetries = 3;
        $attempt = 0;
        $isReady = false;
        $data = null;

        while ($attempt < $maxRetries && !$isReady) {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");

            if ($res->successful()) {
                $data = $res->json();
                $status = $data['Invoices'][0]['Status'];
                Log::info("Try $attempt: Status Invoice saat ini: $status");

                if ($status == 'AUTHORISED' || $status == 'DRAFT') {
                    $isReady = true;
                } else {
                    sleep(1);
                }
            }
            $attempt++;
        }

        if (!$isReady) throw new \Exception("Gagal Buka Gembok Invoice. Status Masih: " . ($data['Invoices'][0]['Status'] ?? 'Unknown'));

        // 2. Susun Item Payload
        $itemsPayload = [];
        $found = false;

        Log::info("Mencari Target ID: " . $line_item_id);
        Log::info("Harga Baru yang diminta: " . $amount_input);

        foreach ($data['Invoices'] as $inv) {
            foreach ($inv['LineItems'] as $item) {

                // DEBUG: Log setiap item yang ada di invoice ini
                Log::info("Cek Item Xero ID: " . $item['LineItemID'] . " | Amount Asli: " . $item['UnitAmount']);

                // PERBANDINGAN ID
                if (strcasecmp(trim($item['LineItemID']), trim($line_item_id)) == 0) {
                    Log::info(">>> KETEMU! Update ID " . $item['LineItemID'] . " menjadi " . $amount_input);
                    $newAmount = $amount_input;
                    $found = true;
                } else {
                    $newAmount = $item['UnitAmount'];
                }

                $payload = [
                    'LineItemID' => $item['LineItemID'],
                    'Description' => $item['Description'], // Tidak ubah desc
                    'UnitAmount' => $newAmount,
                    'Quantity' => $item['Quantity'],
                ];

                // Masukkan field wajib lain
                if (isset($item['ItemCode'])) $payload['ItemCode'] = $item['ItemCode'];
                if (isset($item['AccountCode'])) $payload['AccountCode'] = $item['AccountCode'];
                if (isset($item['TaxType'])) $payload['TaxType'] = $item['TaxType'];

                $itemsPayload[] = $payload;
            }
        }

        if (!$found) {
            Log::error("CRITICAL: Target ID tidak ditemukan di list item Xero!");
            throw new \Exception("Item ID $line_item_id tidak ditemukan di Invoice $cleanId. Cek Log!");
        }

        // 3. Kirim Update
        $resUpdate = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Invoices', [
            'InvoiceID' => $parent_id,
            'LineItems' => $itemsPayload
        ]);

        if ($resUpdate->failed()) {
            Log::error("Gagal Update API: " . $resUpdate->body());
            throw new \Exception("Gagal Update Invoice: " . $resUpdate->body());
        }

        Log::info("Berhasil Update Invoice. Response Xero: " . $resUpdate->status());
    }

    public function createPayments($invoice_id, $old_payment_id) {
        //cek kode ini apakah bisa bayar lebih dari yang di bayarkan
        // ... (Kode sama, pastikan logika amountDue tetap ada) ...
        $backup = PaymentParams::where('payments_id', $old_payment_id)->first();
        if (!$backup) return;

        $resInv = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$invoice_id");

        $payAmount = (float)$backup->amount;

        $totNya = 0;
        if ($resInv->successful()) {

           foreach ($resInv['Invoices'] as $key => $value) {
                foreach ($value["LineItems"] as $key2 => $value2) {
                  $totNya +=(float)  $value2["LineAmount"];
                }
           }
            $invData = $resInv->json();
            $due = (float)$invData['Invoices'][0]['AmountDue'];
            // dd($resInv);
            Log::info("Create Payment: Backup Amount: $payAmount | Tagihan Xero (Due): $due");

            if ($payAmount > $due) {
                Log::info("Penyesuaian: Nominal bayar diturunkan menjadi $due");
                $payAmount = $due;
            }
        }
        //dd($totNya);

        if ($payAmount <= 0) return;

        $resPay = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Payments', [
            "Payments" => [[
                "Invoice" => ["InvoiceID" => $invoice_id],
                // "Account" => ["Code" => $backup->account_code],
                "Account" => ["AccountID" => $backup->account_code],
                "Date" => $backup->date,
                "Amount" =>$totNya, //$payAmount,
                "Reference" => $backup->reference
            ]]
        ]);

        if ($resPay->failed()) throw new \Exception("Gagal Restore Payment: " . $resPay->body());
    }
}
