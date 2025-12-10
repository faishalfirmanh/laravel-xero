<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class UserRegiserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $now = \Carbon\Carbon::now();

        User::updateOrCreate([
            'email'=> 'admin@admin.com',
        ],[
             'name'=> 'isal',
             'email'=> 'admin@admin.com',
             'email_verified_at' => $now->format('Y-m-d H:i:s'),
             'password'=>Hash::make('admin123')
        ]);
    }
}
