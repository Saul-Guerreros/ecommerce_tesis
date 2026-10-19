<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Entrega del equipo al cliente (A5). Espejo de pedido_entrega:
 * congela la dirección como TEXTO (si el cliente la edita o borra después,
 * la constancia sigue diciendo a dónde se entregó) + datos del receptor.
 *
 * ese_titula: true = retiró el titular; false = un tercero (con su C.I.).
 * ese_obs:    agregada al brief. Obligatoria cuando algún accesorio
 *             recibido NO se devuelve (queda constancia del motivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_servicio', function (Blueprint $table) {
            $table->id('ese_id');
            $table->unsignedBigInteger('ese_sseid');
            $table->unsignedSmallInteger('ese_tenid');
            $table->unsignedInteger('ese_sucid')->nullable();
            $table->unsignedInteger('ese_dirid')->nullable();

            // Receptor
            $table->string('ese_nrodoc', 20);
            $table->string('ese_nombre', 100);
            $table->string('ese_apelli', 100);
            $table->string('ese_telef', 45)->nullable();
            $table->boolean('ese_titula')->default(true);

            // Snapshot de la dirección (solo envío)
            $table->string('ese_departa', 60)->nullable();
            $table->string('ese_ciudad', 60)->nullable();
            $table->string('ese_barrio', 60)->nullable();
            $table->string('ese_calle', 100)->nullable();
            $table->string('ese_nrocasa', 20)->nullable();
            $table->string('ese_referencia', 250)->nullable();

            $table->decimal('ese_cosenv', 14, 2)->default(0);
            $table->string('ese_obs', 255)->nullable();
            $table->timestamp('ese_fecha')->useCurrent();
            $table->unsignedInteger('ese_usuid');

            $table->unique('ese_sseid', 'uq_ese_sseid');

            $table->foreign('ese_sseid', 'fk_ese_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ese_tenid', 'fk_ese_ten')->references('ten_id')->on('tipo_entrega')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ese_sucid', 'fk_ese_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ese_dirid', 'fk_ese_dir')->references('dir_id')->on('direccion')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ese_usuid', 'fk_ese_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_servicio');
    }
};
