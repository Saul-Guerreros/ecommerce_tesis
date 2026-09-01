<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor', function (Blueprint $table) {
            $table->increments('pro_id');
            $table->unsignedInteger('pro_tprid');
            $table->unsignedInteger('pro_ciuid');

            $table->string('pro_ruc', 15);
            $table->string('pro_razon', 150);
            $table->string('pro_nomfan', 80)->nullable();
            $table->string('pro_direccion', 100)->nullable();
            $table->string('pro_telef', 20)->nullable();
            $table->string('pro_email', 60)->nullable();

            $table->smallInteger('pro_estado')->default(1);
            $table->timestamp('pro_creado')->useCurrent();

            $table->foreign('pro_tprid', 'fk_pro_tpr')
                  ->references('tpr_id')->on('tipo_proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('pro_ciuid', 'fk_pro_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            // El RUC identifica al contribuyente: no puede repetirse.
            $table->unique('pro_ruc', 'uq_pro_ruc');

            $table->index('pro_tprid', 'ix_pro_tprid');
            $table->index('pro_ciuid', 'ix_pro_ciuid');
            // Las busquedas del listado van por razon social.
            $table->index('pro_razon', 'ix_pro_razon');
        });

        DB::statement("ALTER TABLE proveedor
            ADD CONSTRAINT ck_pro_estado CHECK (pro_estado IN (0, 1))");

        // Formato de RUC paraguayo: numero y digito verificador separados por
        // guion (ej: 80012345-6). La validez del DV se calcula en el
        // controlador; aca solo se garantiza la forma.
        DB::statement("ALTER TABLE proveedor
            ADD CONSTRAINT ck_pro_ruc CHECK (pro_ruc ~ '^[0-9]{1,13}-[0-9]$')");

        // Un email vacio se guarda como NULL, no como cadena vacia.
        DB::statement("ALTER TABLE proveedor
            ADD CONSTRAINT ck_pro_email CHECK (pro_email IS NULL OR pro_email <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor');
    }
};