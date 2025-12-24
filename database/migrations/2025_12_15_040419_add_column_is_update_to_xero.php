<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

       // \DB::connection('mysql_2')->statement("UPDATE data_jamaah SET estimasi_berangkat = NULL WHERE CAST(estimasi_berangkat AS CHAR) = '0000-00-00'");

    //    Schema::connection("mysql_2")->table('data_jamaah', function (Blueprint $table) {
    //         $table->boolean('is_updated_to_xero')
    //               ->default(false);
    //     });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('data_jamaah', function (Blueprint $table) {
        //   Schema::connection("mysql_2")->table('data_jamaah', function (Blueprint $table) {
        //     $table->dropColumn('is_updated_to_xero');
        // });
        // });
    }
};
