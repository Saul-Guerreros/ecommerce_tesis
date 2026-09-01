<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->id('cli_id');

            $table->foreignId('cli_perid')
                  ->unique('cliente_persona_unique')
                  ->constrained('persona', 'per_id')
                  ->restrictOnDelete();

            $table->foreignId('cli_tpcid')
                  ->constrained('tipo_cliente', 'tpc_id')
                  ->restrictOnDelete();

            $table->string('cli_ruc', 45)->nullable();
            $table->smallInteger('cli_estado')->default(1);
            $table->timestamp('cli_creado')->useCurrent();

            $table->index('cli_estado', 'cliente_estado_idx');
        });

        DB::statement("ALTER TABLE cliente ADD CONSTRAINT cliente_estado_check
                       CHECK (cli_estado IN (0,1))");
        DB::statement("ALTER TABLE cliente ADD CONSTRAINT cliente_ruc_check
                       CHECK (cli_ruc IS NULL OR cli_ruc ~ '^[0-9]{1,15}-[0-9]$')");

        DB::statement("CREATE UNIQUE INDEX cliente_ruc_unique
                       ON cliente (cli_ruc) WHERE cli_ruc IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};