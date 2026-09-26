<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_cuota', function (Blueprint $table) {
            $table->id('pcu_id');
            $table->unsignedSmallInteger('pcu_cantidad');      // 2, 3, 4, 5...
            $table->decimal('pcu_recargo_pct', 5, 2);          // 7.50, 12.50...
            $table->unsignedSmallInteger('pcu_orden')->default(0);
            $table->boolean('pcu_estado')->default(true);
            $table->timestamp('pcu_creado')->useCurrent();

            // Un solo plan por cantidad de cuotas: evita tener 3x al 12% y
            // 3x al 15% activos al mismo tiempo.
            $table->unique('pcu_cantidad');
        });

        DB::statement('ALTER TABLE plan_cuota ADD CONSTRAINT ck_pcu_recargo CHECK (pcu_recargo_pct >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cuota');
    }
};