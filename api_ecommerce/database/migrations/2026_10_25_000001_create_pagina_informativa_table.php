<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Páginas informativas de la tienda (Atención al Cliente), editables
 * desde el admin: Cómo comprar, Garantía, Términos y Condiciones,
 * Política de Privacidad. Catálogo fijo por slug (sin store/destroy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagina_informativa', function (Blueprint $table) {
            $table->increments('pgi_id');
            $table->string('pgi_slug', 60)->unique();
            $table->string('pgi_titulo', 150);
            $table->longText('pgi_contenido'); // HTML, con tokens {{EMPRESA}}, {{RUC}}, etc.
            $table->timestamp('pgi_actualizado')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_informativa');
    }
};