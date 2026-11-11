<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte pago_cheque de "datos declarados por el cliente" a
 * "cheque gestionado por la sucursal".
 *
 * Los cuatro campos originales (tipo, banco, CI, nombre) son la
 * declaración del checkout. Lo que se agrega acá es el ciclo de vida del
 * papel físico, que la transferencia no tiene:
 *
 *   - pch_nro / pch_fecobr: completan la declaración. Sin el número no se
 *     puede identificar el cheque; sin la fecha de cobro no se sabe
 *     cuándo depositar el diferido.
 *   - pch_estado: el eje "Cheque" de los tres que muestra la gestión
 *     (Pedido / Cheque / Pago). Va como columna con CHECK y no como tabla
 *     catálogo porque son cinco valores cerrados que el admin no
 *     administra, igual que cobro_cheque.cbc_estado del módulo de
 *     cobranzas.
 *   - pch_fevcto: el plazo de 48 h vive por PAGO y no por pedido. Con
 *     pago dividido hay dos cheques que pueden llegar en momentos
 *     distintos, así que ped_vence no alcanza.
 *   - pch_frecep / pch_usurec: quién confirmó la recepción y cuándo. Es
 *     el dato que dispara el descuento de stock, así que tiene que ser
 *     auditable.
 *   - pch_mrpid / pch_motdet: rechazo, con el mismo catálogo que ya usa
 *     el comprobante.
 */
return new class extends Migration
{
    /** Plazo de entrega del cheque físico, en horas. */
    private const HORAS_PLAZO = 48;

    public function up(): void
    {
        Schema::table('pago_cheque', function (Blueprint $table) {
            // Declaración (se cargan en el checkout a partir de la Etapa 3;
            // nullable para no romper los cheques ya existentes).
            $table->string('pch_nro', 20)->nullable()->after('pch_nombre');
            $table->date('pch_fecobr')->nullable()->after('pch_nro');

            // Ciclo de vida
            $table->string('pch_estado', 13)->default('EN_ESPERA')->after('pch_fecobr');
            $table->timestamp('pch_fevcto')->nullable()->after('pch_estado');
            $table->timestamp('pch_frecep')->nullable()->after('pch_fevcto');
            $table->unsignedInteger('pch_usurec')->nullable()->after('pch_frecep');

            // Rechazo
            $table->unsignedSmallInteger('pch_mrpid')->nullable()->after('pch_usurec');
            $table->string('pch_motdet', 300)->nullable()->after('pch_mrpid');

            $table->foreign('pch_usurec')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('pch_mrpid')->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            // El barrido de vencimiento busca por estado + fecha.
            $table->index(['pch_estado', 'pch_fevcto'], 'ix_pch_estado_vcto');
        });

        // Cuarta capa de la misma regla que ya aplica a pch_tipo.
        DB::statement("ALTER TABLE pago_cheque
            ADD CONSTRAINT ck_pch_estado CHECK (pch_estado IN
                ('EN_ESPERA','RECIBIDO','NO_ENTREGADO','RECHAZADO','DESCARTADO'))");

        // Coherencia del diferido: si hay fecha de cobro, no puede ser
        // anterior a la carga del cheque.
        DB::statement('ALTER TABLE pago_cheque
            ADD CONSTRAINT ck_pch_fecobr CHECK
                (pch_fecobr IS NULL OR pch_fecobr >= CAST(pch_creado AS DATE))');

        // Los cheques que ya estaban cargados arrancan en espera, con el
        // plazo contado desde su declaración.
        DB::table('pago_cheque')->whereNull('pch_fevcto')->update([
            'pch_estado' => 'EN_ESPERA',
            'pch_fevcto' => DB::raw($this->expresionPlazo()),
        ]);
    }

    public function down(): void
    {
        $this->borrarCheck('pago_cheque', 'ck_pch_fecobr');
        $this->borrarCheck('pago_cheque', 'ck_pch_estado');

        Schema::table('pago_cheque', function (Blueprint $table) {
            $table->dropForeign(['pch_usurec']);
            $table->dropForeign(['pch_mrpid']);
            $table->dropIndex('ix_pch_estado_vcto');
            $table->dropColumn([
                'pch_nro', 'pch_fecobr', 'pch_estado', 'pch_fevcto',
                'pch_frecep', 'pch_usurec', 'pch_mrpid', 'pch_motdet',
            ]);
        });
    }

    /** Suma de horas portable entre PostgreSQL y MySQL. */
    private function expresionPlazo(): string
    {
        return Schema::getConnection()->getDriverName() === 'pgsql'
            ? "pch_creado + INTERVAL '" . self::HORAS_PLAZO . " hours'"
            : 'DATE_ADD(pch_creado, INTERVAL ' . self::HORAS_PLAZO . ' HOUR)';
    }

    /** PostgreSQL y MySQL usan sintaxis distinta para soltar un CHECK. */
    private function borrarCheck(string $tabla, string $constraint): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$constraint}");

            return;
        }

        try {
            DB::statement("ALTER TABLE {$tabla} DROP CHECK {$constraint}");
        } catch (\Throwable $e) {
            // No existía: nada que soltar.
        }
    }
};