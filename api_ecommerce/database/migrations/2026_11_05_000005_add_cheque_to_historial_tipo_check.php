<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita peh_tipo = 'CHEQUE' en pedido_historial_estado.
 *
 * La gestión del cheque muestra tres ejes simultáneos (Pedido / Cheque /
 * Pago) y el historial hoy solo admite PAGO, PEDIDO, ENVIO y NOTA. Sin
 * este valor, "cheque recibido" tendría que registrarse como un evento de
 * PAGO, lo cual es falso: al recibir el papel todavía no entró dinero.
 *
 * Nota de implementación: peh_estid es NOT NULL y apunta a un catálogo de
 * estados. Para el eje CHEQUE se registra el estado de PAGO vigente en ese
 * momento y el detalle del cheque va en peh_coment, evitando una tabla
 * catálogo nueva para cinco valores cerrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->reemplazarCheck("('PAGO','PEDIDO','ENVIO','NOTA','CHEQUE')");
    }

    public function down(): void
    {
        DB::table('pedido_historial_estado')->where('peh_tipo', 'CHEQUE')->update([
            'peh_tipo' => 'PAGO',
        ]);

        $this->reemplazarCheck("('PAGO','PEDIDO','ENVIO','NOTA')");
    }

    private function reemplazarCheck(string $valores): void
    {
        $pg = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($pg) {
            DB::statement('ALTER TABLE pedido_historial_estado DROP CONSTRAINT IF EXISTS ck_peh_tipo');
        } else {
            try {
                DB::statement('ALTER TABLE pedido_historial_estado DROP CHECK ck_peh_tipo');
            } catch (\Throwable $e) {
            }
        }

        DB::statement("ALTER TABLE pedido_historial_estado
            ADD CONSTRAINT ck_peh_tipo CHECK (peh_tipo IN {$valores})");
    }
};