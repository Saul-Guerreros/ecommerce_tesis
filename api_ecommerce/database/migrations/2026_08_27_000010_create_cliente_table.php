<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->increments('cli_id');
            $table->string('cli_ruc', 45)->nullable();
            $table->smallInteger('cli_estado')->default(1);
            $table->unsignedInteger('cli_perid');
            $table->unsignedInteger('cli_tpcid');

            $table->foreign('cli_perid')->references('per_id')->on('persona');
            $table->foreign('cli_tpcid')->references('tpc_id')->on('tipo_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
