<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dato_fiscal', function (Blueprint $table) {
            $table->id('dfi_id');
            $table->unsignedBigInteger('dfi_perid');
            $table->smallInteger('dfi_tdocid');
            $table->string('dfi_alias', 50);
            $table->string('dfi_numero', 20);
            $table->string('dfi_razon', 150);
            $table->string('dfi_telefono', 20);
            $table->string('dfi_correo', 100);
            $table->boolean('dfi_principal')->default(false);
            $table->timestamp('dfi_creado')->nullable();

            $table->foreign('dfi_perid')->references('per_id')->on('persona')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dfi_tdocid')->references('tpd_id')->on('tipo_documento');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_dato_fiscal_principal
            ON dato_fiscal (dfi_perid)
            WHERE dfi_principal = true
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('dato_fiscal');
    }
};