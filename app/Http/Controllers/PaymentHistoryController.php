<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;//
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentsHistoryFix;
use Illuminate\Support\Facades\DB;
use App\ConfigRefreshXero;
class PaymentHistoryController extends Controller
{
    //

    use ConfigRefreshXero;


    private function parseXeroDate($xeroDate) {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');
    }

    public function getHistoryInvoice($invoice_id)
    {
        $getData = PaymentsHistoryFix::select('invoice_number','invoice_uuid','date','amount')->where('invoice_uuid',$invoice_id)->orderBy('date','asc')->get();
        return response()->json([
            'data'=>$getData
        ], 200);
    }

    public function insertToHistory()
    {

        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);

        $invoicesResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId, //env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Invoices');

        if ($invoicesResponse->failed()) {
            return response()->json(['error' => 'Gagal ambil invoices'], 500);
        }

        foreach ($invoicesResponse->json()['Invoices'] as $key => $value) {
            //
            $invoice_uid = $value["InvoiceID"];
            $invNumber = $value["InvoiceNumber"];

            $response_detail = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$invoice_uid");

            $contact_name = $response_detail->json()['Invoices'][0]['Contact'] ? $response_detail->json()['Invoices'][0]['Contact']['Name'] : "-";
            $invoice_number = $response_detail->json()['Invoices'][0]['InvoiceNumber'];

            //dd($response_detail->json()['Invoices'][0]["Status"] == "PAID");
            if($response_detail->json()['Invoices'][0]["Status"] == "PAID")
            {
                if(isset($response_detail->json()['Invoices'][0]["Payments"]))
                {
                    if(count($response_detail->json()['Invoices'][0]["Payments"])>0){
                        $listPayment = $response_detail->json()['Invoices'][0]["Payments"];
                        foreach ($listPayment as $key_1 => $value_2) {
                            if(isset($value_2["Reference"]))
                            {
                                if($value_2["Reference"] == "-man"){
                                    DB::beginTransaction();
                                    try {
                                        PaymentsHistoryFix::updateOrCreate(
                                            [
                                                'payment_uuid' => $value_2["PaymentID"],
                                                //'invoice_uuid' => $value["InvoiceID"]
                                            ],
                                            [
                                                'invoice_uuid' => $value["InvoiceID"],
                                                'contact_name' => $contact_name,
                                                'invoice_number' => $invoice_number,
                                                'date' => $this->parseXeroDate($value_2["Date"]),
                                                'amount' =>   $value_2["Amount"],
                                                'reference' => $value_2["Reference"]
                                            ]
                                        );
                                        Log::info("sukses save payment history inv ".$invoice_number);
                                        DB::commit();
                                    } catch (\Exception $e) {
                                        Log::error("gagal insert payment history inv ".$invoice_number ." ". $e->getMessage() );
                                        DB::rollBack();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
