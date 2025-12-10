<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ConfigSettingXero;

class ConfigSettingXeroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = \Carbon\Carbon::now();

        ConfigSettingXero::updateOrCreate([
            'xero_tenant_id'=> env('XERO_TENANT_ID'),
        ],[
             'xero_tenant_id'=> env('XERO_TENANT_ID'),
             'client_id' => env('XERO_CLIENT_ID'),
             'client_secret'=> env('XERO_CLIENT_SECRET'),
             'redirect_url'=> 'https://localhost',
             'expires_at'=> $now->format('Y-m-d H:i:s')
        ]);
    }
}
