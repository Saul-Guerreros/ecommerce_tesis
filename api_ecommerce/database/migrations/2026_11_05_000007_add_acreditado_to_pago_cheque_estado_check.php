<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sexto estado del cheque: ACREDITADO.
 *
 * Cierra el ciclo del papel, que hasta ahora terminaba en RECIBIDO: el
 * cheque llega a la sucursal, y semanas después (en un diferido) el banco
 * acredita el dinero. Ese último tramo se leía sólo en el estado del pago,
 * así que la cabecera mostraba "Cheque: Recibido" cuando ya estaba cobrado.
 *
 * La columna es varchar(13) y ACREDITADO tiene 10 caracteres: entra sin
 * ampliarla.
 */
return new class extends Migration
{
    private const ANTERIORES = "'EN_ESPERA','RECIBIDO','NO_ENTREGADO','RECHAZADO','DESCARTADO'";
    private const NUEVOS     = "'EN_ESPERA','RECIBIDO','ACREDITADO','NO_ENTREGADO','RECHAZADO','DESCARTADO'";

    public function up(): void
    {
        $this->reemplazarCheck(self::NUEVOS);
    }

    public function down(): void
    {
        // Los cheques ya acreditados vuelven a RECIBIDO: es el estado
        // anterior en el ciclo y el que el CHECK viejo acepta.
        DB::table('pago_cheque')
            ->where('pch_estado', 'ACREDITADO')
            ->update(['pch_estado' => 'RECIBIDO']);

        $this->reemplazarCheck(self::ANTERIORES);
    }

    private function reemplazarCheck(string $valores): void
    {
        $this->borrarCheck();

        DB::statement("ALTER TABLE pago_cheque
            ADD CONSTRAINT ck_pch_estado CHECK (pch_estado IN ({$valores}))");
    }

    private function borrarCheck(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pago_cheque DROP CONSTRAINT IF EXISTS ck_pch_estado');

            return;
        }

        try {
            DB::statement('ALTER TABLE pago_cheque DROP CHECK ck_pch_estado');
        } catch (\Throwable $e) {
            // MySQL < 8.0.16 ignora los CHECK: no hay nada que borrar.
        }
    }
};