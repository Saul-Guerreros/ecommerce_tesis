<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marca', function (Blueprint $table) {
            $table->increments('mar_id');
            $table->string('mar_descri', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marca');
    }
};
