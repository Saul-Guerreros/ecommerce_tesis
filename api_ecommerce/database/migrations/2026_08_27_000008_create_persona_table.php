<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona', function (Blueprint $table) {
            $table->increments('per_id');
            $table->unsignedInteger('per_tpdid');
            $table->string('per_nombr1', 45);
            $table->string('per_nombr2', 45)->nullable();
            $table->string('per_apell1', 50);
            $table->string('per_apell2', 50)->nullable();
            $table->string('per_cedula', 50)->unique();
            $table->string('per_estciv', 50)->nullable();
            $table->date('per_fechna')->nullable();
            $table->timestamp('per_cread')->useCurrent();
            // NOTA: en el DER figura como TIMESTAMP, pero por semántica de "estado"
            // se implementa como bandera activo/inactivo (1/0), igual que el resto
            // de campos *_estado del modelo. Ver aviso al usuario.
            $table->tinyInteger('per_estado')->default(1);

            $table->foreign('per_tpdid')->references('tpd_id')->on('tipo_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};
