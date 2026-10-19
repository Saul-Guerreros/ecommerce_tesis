<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->unsignedInteger('fun_rolid')->nullable();
            $table->foreign('fun_rolid')->references('rol_id')->on('rol');
        });

        // Los funcionarios que ya tienen cuenta de acceso heredan su rol actual.
        DB::statement('UPDATE funcionario
                       SET fun_rolid = u.usu_rolid
                       FROM usuario u
                       WHERE u.usu_perid = funcionario.fun_perid');
    }

    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->dropForeign(['fun_rolid']);
            $table->dropColumn('fun_rolid');
        });
    }
};