<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class TrackingController extends Controller
{
   
  
   public function getAgent()
    {
       
        $getData = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/TrackingCategories");

        if ($getData->failed()) {
            return response()->json([
                'error' => true,
                'message' => 'Gagal get data',
                'details' => $getData->json()
            ], $getData->status());
        }
      //  if($getData["TrackingCategories"]["Name"]);
      $list_data=[];
      $aa = 0;
       foreach ($getData["TrackingCategories"] as $key => $value) {
         if($value["Name"] == "Agent"){
            $list_data[$aa] = $value["Options"];
            $aa++;
         }
       }
        return response()->json($list_data);
    }


    public function getKategory()
    {
       
        $getData = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/TrackingCategories");

        if ($getData->failed()) {
            return response()->json([
                'error' => true,
                'message' => 'Gagal get data',
                'details' => $getData->json()
            ], $getData->status());
        }
      //  if($getData["TrackingCategories"]["Name"]);
      $list_data=[];
      $aa = 0;
       foreach ($getData["TrackingCategories"] as $key => $value) {
         if($value["Name"] == "Divisi"){
            $list_data[$aa] = $value["Options"];
            $aa++;
         }
       }
        return response()->json($list_data);
    }
   

}
