<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marca', function (Blueprint $table) {
            $table->increments('mar_id');
            $table->string('mar_descri', 50)->nullable()->unique(); // <- unique() de Blueprint, sin SQL crudo
            $table->smallInteger('mar_estado')->default(1);
            $table->timestamp('mar_creado')->useCurrent();
        });

        // El CHECK sí necesita SQL crudo: Laravel 10 no tiene un método
        // nativo en Blueprint para constraints CHECK.
        DB::statement("ALTER TABLE marca
            ADD CONSTRAINT ck_mar_estado CHECK (mar_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('marca');
    }
};