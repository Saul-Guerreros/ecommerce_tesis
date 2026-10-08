<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_temporal', function (Blueprint $table) {
            $table->jsonb('ptm_pago')->nullable()->after('ptm_factura');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_temporal', function (Blueprint $table) {
            $table->dropColumn('ptm_pago');
        });
    }
};