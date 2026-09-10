<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('det_rol_permiso', function (Blueprint $table) {
            $table->increments('drp_id');
            $table->unsignedInteger('drp_rolid');
            $table->unsignedInteger('drp_prmid');
            $table->timestamp('drp_creado')->useCurrent();

            $table->foreign('drp_rolid')->references('rol_id')->on('rol');
            $table->foreign('drp_prmid')->references('prm_id')->on('permiso');
            $table->unique(['drp_rolid', 'drp_prmid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('det_rol_permiso');
    }
};
