<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable a propósito: los funcionarios que ya existen no tienen sucursal.
        // La obligatoriedad se exige en StoreFuncionarioRequest (alta y edición).
        Schema::table('funcionario', function (Blueprint $table) {
            $table->unsignedInteger('fun_sucid')->nullable();

            $table->foreign('fun_sucid', 'fk_fun_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('fun_sucid', 'ix_fun_sucid');
        });

        // Los funcionarios que ya tienen cuenta de acceso heredan su sucursal
        // principal activa (o, en su defecto, la primera activa que tengan asignada).
        DB::statement('UPDATE funcionario
                       SET fun_sucid = s.uss_sucid
                       FROM (
                           SELECT DISTINCT ON (u.usu_perid) u.usu_perid, us.uss_sucid
                           FROM usuario u
                           JOIN usuario_sucursal us ON us.uss_usuid = u.usu_id AND us.uss_estado = 1
                           ORDER BY u.usu_perid, us.uss_princi DESC, us.uss_id
                       ) s
                       WHERE s.usu_perid = funcionario.fun_perid');
    }

    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->dropForeign('fk_fun_suc');
            $table->dropIndex('ix_fun_sucid');
            $table->dropColumn('fun_sucid');
        });
    }
};
