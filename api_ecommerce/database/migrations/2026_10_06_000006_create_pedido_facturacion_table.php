<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Snapshot del Paso 2 - Seccion 1 (ptm_factura). Distinta de una futura
// factura_venta (comprobante fiscal ya emitido, con timbrado e IVA
// desglosado), tal como quedo decidido para la tesis.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_facturacion', function (Blueprint $table) {
            $table->id('pfa_id');
            $table->unsignedBigInteger('pfa_pedid');
            $table->string('pfa_tipodoc', 5); // 'CI' | 'RUC'
            $table->unsignedInteger('pfa_dfiid')->nullable();
            $table->string('pfa_nrodoc', 20);
            $table->string('pfa_raznom', 150);
            $table->string('pfa_telefono', 45)->nullable();
            $table->string('pfa_correo', 100)->nullable();
            $table->string('pfa_recibe', 10); // 'CORREO' | 'WHATSAPP'

            $table->foreign('pfa_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pfa_dfiid')->references('dfi_id')->on('dato_fiscal')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement("ALTER TABLE pedido_facturacion ADD CONSTRAINT ck_pfa_tipodoc CHECK (pfa_tipodoc IN ('CI','RUC'))");
        DB::statement("ALTER TABLE pedido_facturacion ADD CONSTRAINT ck_pfa_recibe CHECK (pfa_recibe IN ('CORREO','WHATSAPP'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_facturacion');
    }
};