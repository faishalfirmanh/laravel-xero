<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class InvoicesController extends Controller
{
    public function viewProduct()
    {
        return view('product');
    }

    public function getAllInvoicesOri(Request $request)
    {
        $i = 0;
        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices');

            if ($response->status() == 200) {
                $list_invoices_id = [];
                foreach ($response->json()['Invoices'] as $key => $value) {
                    $list_invoices_id[] = $value['InvoiceID'];

                }
                //1. looping id invoices
                foreach ($list_invoices_id as $key2 => $value2) {
                    //2. cek detail invoices
                    $cleanId = trim($value2, '"');
                    $resp2 = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                        'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");//$cleanId
                    //5a3e4380-5bec-4cba-bebe-19a796475ca0

                    $tiap_item = $resp2->json()['Invoices'][0]['LineItems'];
                    foreach ($tiap_item as $key3 => $value3) {
                        $qty_detail_item = $value3['Quantity'];
                        $getProduct = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])->get("https://api.xero.com/api.xro/2.0/Items/" . $value3['ItemCode']);

                        //dd($getProduct->json()['Items']);
                        $price_origin_product = $getProduct->json()['Items'][0]['SalesDetails']['UnitPrice'];
                        //dd($getProduct->json()['Items'][0]['SalesDetails']['UnitPrice']);
                        $payload_2 = [
                            "InvoiceID" => $value2,
                            "LineItems" => [
                                [
                                    "LineItemID" => $value3['LineItemID'],
                                    "UnitAmount" => $price_origin_product,
                                    "Quantity" => $qty_detail_item
                                ]
                            ]
                        ];
                        $update_tiap_row_invoices = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])->post('https://api.xero.com/api.xro/2.0/Invoices', $payload_2);
                        $i++;
                    }
                }
            }

            return response()->json(['message' => 'success', 'data' => $i]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }



    public function getDetailInvoice($idInvoice)
    {
        try {
            $response_detail = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$idInvoice");
            return response()->json($response_detail->json() ?: ['message' => 'Xero API Error'], $response_detail->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }

    }

    public function viewDetailInvoice($idInvoice)
    {
        return view('detail_invoices');
    }

    public function updateInvoiceSelected(Request $request)
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        $tot = 0;
        $array = [];
        foreach ($data['items'] as $key => $value) {
            if ($value['status'] != 'PAID') {
                self::updateInvoicePerRows($value['parentId'], $data['price_update'], $value['lineItemId']);
                $tot++;
                $array[] = $value['no_invoice'];
            } else {

            }

        }
        return response()->json($array, 200);
    }

    //untuk yang draft
    public function updateInvoicePerRows($parent_id, $amount_input, $line_item_id)
    {
        // $parent_id = $request->parent_invoice;
        //input invoice_id, amount (harga paket setelah update), line_items

        $cleanId = str_replace('"', '', $parent_id);//trim($parent_id, '"');
        //dd($cleanId);
        $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");
        //  dd($response_detail);

        $all_itemm = [];

        foreach ($response_detail['Invoices'] as $key => $value) {//list per paket
            foreach ($value['LineItems'] as $key2 => $value2) {//item tiap paket
                // $cek_is_update_Qty = $value2['LineItemID'] == $request->line_item_id ? $request->qty_input : $value2['Quantity'];
                $cek_is_update_Amount = $value2['LineItemID'] == $line_item_id ? $amount_input : $value2['UnitAmount'];
                $all_itemm[] = [
                    'LineItemID' => $value2['LineItemID'],
                    'ItemCode' => $value2['ItemCode'],
                    'Description' => 'update harga paket ' . $value2['Item']['Name'],
                    'UnitAmount' => $cek_is_update_Amount,
                    'Quantity' => $value2['Quantity'],
                ];
            }
        }
        $parent_wrap = ['InvoiceID' => $parent_id, 'LineItems' => $all_itemm];

        $update_tiap_row_invoices = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Invoices', $parent_wrap);
        // dd($update_tiap_row_invoices->status());//200
        //return response()->json($update_tiap_row_invoices->json() ?: ['message' => 'Xero API Error'], $update_tiap_row_invoices->status());
    }

    public function getInvoiceByIdPaket($itemCode = 0)
    {
        //  dd($itemCode);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices');


            $items_inv = 0;
            $list_invoice = [];
            foreach ($response['Invoices'] as $key => $value) {
                $cleanId = trim($value['InvoiceID'], '"');//invoiceId

                //detail Invoices
                $resp2 = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                    'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");
                //
                // dd($value);
                foreach ($resp2['Invoices'] as $key2 => $value2) {//items

                    foreach ($value2['LineItems'] as $key3 => $value3) {//list
                        // dd($value2);
                        //$cek_detail_row = isset($list_invoice[$cleanId]) && $list_invoice[$cleanId]['line_item_id'] === $value3['LineItemID'];

                        if (isset($value3['ItemCode']) && $value3['ItemCode'] == $itemCode) {
                            //  dd($value3);

                            $list_invoice[] = [
                                'parent_invoice_id' => $cleanId,
                                'nama_jamaah' => $value2['Contact']['Name'],
                                'no_invoice' => $value['InvoiceNumber'],
                                'line_item_id' => $value3['LineItemID'],
                                'detail_amount_tiap_item' => $value3['LineAmount'],
                                'qty_tiap_item' => $value3['Quantity'],
                                'tanggal' => $value2['DateString'],
                                'tanggal_due_date' => $value2['DueDateString'] ?? null,
                                'paket_name' => $value3['Item']['Name'],
                                'amount_paid' => $value2['AmountPaid'],
                                'total' => $value2['Total'],
                                'status' => $value['Status'],
                            ];
                        }

                    }

                }
            }
            // dd($items_inv);
            return response()->json($list_invoice ?: ['message' => 'Xero API Error'], count($list_invoice) > 0 ? $response->status() : 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }
    }

    //status draft
    public function getAllInvoices(Request $request)
    {
        $updatedCount = 0;
        $tenantId = '90a3a97b-3d70-41d3-aa77-586bb1524beb';
        $headers = [
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => $tenantId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            // ---------------------------------------------------------
            // LANGKAH 1: Ambil Master Data Items (Supaya tidak request berulang)
            // ---------------------------------------------------------
            $itemsResponse = Http::withHeaders($headers)->get('https://api.xero.com/api.xro/2.0/Items');
            if ($itemsResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil data items'], 500);
            }

            $itemPriceMap = [];
            foreach ($itemsResponse->json()['Items'] as $item) {
                // Kita pakai SalesDetails (Harga Jual)
                if (isset($item['SalesDetails']['UnitPrice'])) {
                    $itemPriceMap[$item['Code']] = $item['SalesDetails']['UnitPrice'];
                }
            }

            // ---------------------------------------------------------
            // LANGKAH 2: Ambil Invoice tapi HANYA YANG DRAFT
            // ---------------------------------------------------------
            // Filter Statuses=DRAFT sangat penting agar tidak merusak data keuangan valid
            $invoicesResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Invoices');//?Statuses=DRAFT

            if ($invoicesResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil invoices'], 500);
            }

            $invoices = $invoicesResponse->json()['Invoices'];

            // ---------------------------------------------------------
            // LANGKAH 3: Loop Invoice & Cek Apakah Perlu Update
            // ---------------------------------------------------------
            foreach ($invoices as $invoice) {

                $detailResp = Http::withHeaders($headers)
                    ->get("https://api.xero.com/api.xro/2.0/Invoices/" . $invoice['InvoiceID']);

                // Hindari rate limit dengan pause sejenak (opsional, tapi aman)
                // usleep(100000); // 0.1 detik

                if ($detailResp->status() != 200)
                    continue;

                $fullInvoice = $detailResp->json()['Invoices'][0];
                $itemsToUpdate = [];

                foreach ($fullInvoice['LineItems'] as $lineItem) {
                    // Cek apakah baris ini punya ItemCode dan apakah ItemCode ada di Master Data kita
                    if (isset($lineItem['ItemCode']) && isset($itemPriceMap[$lineItem['ItemCode']])) {

                        $currentMasterPrice = $itemPriceMap[$lineItem['ItemCode']];


                        $itemsToUpdate[] = [
                            "LineItemID" => $lineItem['LineItemID'],
                            "ItemCode" => $lineItem['ItemCode'],
                            "Description" => $lineItem['Description'],
                            "UnitAmount" => $currentMasterPrice, // Update ke harga baru
                            "Quantity" => $lineItem['Quantity']  // Qty tetap
                            // Description update opsional
                        ];

                    }
                }

                // ---------------------------------------------------------
                // LANGKAH 4: Eksekusi Update (Jika ada item yang harganya beda)
                // ---------------------------------------------------------
                if (count($itemsToUpdate) > 0) {
                    $payload = [
                        "InvoiceID" => $fullInvoice['InvoiceID'],
                        "LineItems" => $itemsToUpdate
                    ];

                    Http::withHeaders($headers)
                        ->post('https://api.xero.com/api.xro/2.0/Invoices', $payload);

                    $updatedCount++;
                }
            }

            return response()->json([
                'message' => 'Proses selesai',
                'invoices_checked' => count($invoices),
                'invoices_updated' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    //status all
    public function getAllInvoicesAll(Request $request)
    {
        $updatedCount = 0;
        $tenantId = '90a3a97b-3d70-41d3-aa77-586bb1524beb';
        $headers = [
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => $tenantId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            // ---------------------------------------------------------
            // LANGKAH 1: Ambil Master Data Items (Supaya tidak request berulang)
            // ---------------------------------------------------------
            $itemsResponse = Http::withHeaders($headers)->get('https://api.xero.com/api.xro/2.0/Items');
            if ($itemsResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil data items'], 500);
            }

            $itemPriceMap = [];
            foreach ($itemsResponse->json()['Items'] as $item) {
                // Kita pakai SalesDetails (Harga Jual)
                if (isset($item['SalesDetails']['UnitPrice'])) {
                    $itemPriceMap[$item['Code']] = $item['SalesDetails']['UnitPrice'];
                }
            }

            // ---------------------------------------------------------
            // LANGKAH 2: Ambil Invoice tapi HANYA YANG DRAFT
            // ---------------------------------------------------------
            // Filter Statuses=DRAFT sangat penting agar tidak merusak data keuangan valid
            $invoicesResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Invoices');//?Statuses=DRAFT

            if ($invoicesResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil invoices'], 500);
            }

            $invoices = $invoicesResponse->json()['Invoices'];

            // ---------------------------------------------------------
            // LANGKAH 3: Loop Invoice & Cek Apakah Perlu Update
            // ---------------------------------------------------------
            foreach ($invoices as $invoice) {

                $detailResp = Http::withHeaders($headers)
                    ->get("https://api.xero.com/api.xro/2.0/Invoices/" . $invoice['InvoiceID']);

                // Hindari rate limit dengan pause sejenak (opsional, tapi aman)
                // usleep(100000); // 0.1 detik

                if ($detailResp->status() != 200)
                    continue;

                $fullInvoice = $detailResp->json()['Invoices'][0];

                $itemsToUpdate = [];

                foreach ($fullInvoice['LineItems'] as $lineItem) {
                    // Cek apakah baris ini punya ItemCode dan apakah ItemCode ada di Master Data kita
                    if (isset($lineItem['ItemCode']) && isset($itemPriceMap[$lineItem['ItemCode']])) {

                        $currentMasterPrice = $itemPriceMap[$lineItem['ItemCode']];


                        $itemsToUpdate[] = [
                            "LineItemID" => $lineItem['LineItemID'],
                            "ItemCode" => $lineItem['ItemCode'],
                            "Description" => $lineItem['Description'],
                            "UnitAmount" => $currentMasterPrice, // Update ke harga baru
                            "Quantity" => $lineItem['Quantity']  // Qty tetap
                            // Description update opsional
                        ];

                    }
                }

                // ---------------------------------------------------------
                // LANGKAH 4: Eksekusi Update (Jika ada item yang harganya beda)
                // ---------------------------------------------------------
                if (count($itemsToUpdate) > 0) {
                    $payload = [
                        "InvoiceID" => $fullInvoice['InvoiceID'],
                        "LineItems" => $itemsToUpdate
                    ];

                    Http::withHeaders($headers)
                        ->post('https://api.xero.com/api.xro/2.0/Invoices', $payload);

                    $updatedCount++;
                }
            }

            return response()->json([
                'message' => 'Proses selesai',
                'invoices_checked' => count($invoices),
                'invoices_updated' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

}
