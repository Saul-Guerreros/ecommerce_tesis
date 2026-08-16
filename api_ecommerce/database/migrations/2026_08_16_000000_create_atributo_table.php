<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas originales de tu script BD_ECOMMERCE:
 *   atb_id, atb_nombre, atb_estado
 *
 * Columnas agregadas para que el CRUD funcione (tu diseño original no las tenía):
 *   atb_tipo      -> 1 Texto, 2 Número, 3 Seleccionable, 4 Seleccionable Múltiple.
 *                    Define si el atributo maneja "propiedades" (3 y 4) o no.
 *   atb_creado    -> fecha de creación (tu script no tenía columna de timestamp aquí)
 *   atb_eliminado -> soft delete
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributo', function (Blueprint $table) {
            $table->increments('atb_id');
            $table->string('atb_nombre', 50)->unique();
            $table->smallInteger('atb_tipo');      
            $table->tinyInteger('atb_estado');
            $table->timestamp('atb_creado')->nullable();    
            $table->timestamp('atb_eliminado')->nullable(); 
        }); 
    }

    public function down(): void
    {
        Schema::dropIfExists('atributo');
    }
};
