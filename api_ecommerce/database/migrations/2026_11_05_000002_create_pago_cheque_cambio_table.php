<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de los cambios que el administrador hace sobre los datos de un
 * cheque: [Descartar cheque] y [Editar].
 *
 * Por qué existe: los dos botones pisan lo que declaró el cliente. Sin
 * este registro no quedaría forma de reconstruir qué dijo el cliente en el
 * checkout frente a qué papel llegó de verdad a la sucursal, que es
 * exactamente el dato que justifica un rechazo posterior.
 *
 * Por qué no se versiona pago_cheque con varias filas: la UNIQUE sobre
 * pch_ppaid es lo que hace que la relación sea HasOne, igual que el
 * comprobante. Romperla obligaría a filtrar "la fila vigente" en cada
 * consulta.
 *
 * Guarda los valores ANTERIORES al cambio: la fila de pago_cheque siempre
 * tiene el estado actual, y acá se apila lo que se fue reemplazando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_cheque_cambio', function (Blueprint $table) {
            $table->id('pcc_id');
            $table->unsignedBigInteger('pcc_pchid');

            $table->string('pcc_tipo', 8);   // DESCARTE | EDICION

            // Valores anteriores al cambio.
            $table->string('pcc_tipche', 10)->nullable();
            $table->string('pcc_banco', 80)->nullable();
            $table->string('pcc_nrodoc', 20)->nullable();
            $table->string('pcc_nombre', 150)->nullable();
            $table->string('pcc_nro', 20)->nullable();
            $table->date('pcc_fecobr')->nullable();

            $table->string('pcc_motivo', 300)->nullable();
            $table->unsignedInteger('pcc_usuid')->nullable();
            $table->timestamp('pcc_fecha')->useCurrent();

            $table->foreign('pcc_pchid')->references('pch_id')->on('pago_cheque')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pcc_usuid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index('pcc_pchid', 'ix_pcc_cheque');
        });

        DB::statement("ALTER TABLE pago_cheque_cambio
            ADD CONSTRAINT ck_pcc_tipo CHECK (pcc_tipo IN ('DESCARTE','EDICION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_cheque_cambio');
    }
};