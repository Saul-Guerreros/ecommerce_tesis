<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cobro_venta -> recibo.
 *
 * La tabla ya centralizaba los cobros de factura (contado y, desde el
 * credito, tambien las cuotas). El nombre pasa a ser el del documento
 * que representa: un recibo.
 *
 * Tratamiento documental, que NO es el registro:
 *   - Contado: el cliente recibe la factura. El recibo se registra pero
 *     no se numera ni se imprime (rec_numero queda NULL).
 *   - Credito: cada pago de cuotas se numera y se entrega al cliente
 *     como constancia del abono.
 *
 * Este paso SOLO renombra. Ni recibo_detalle ni la numeracion entran
 * aca: si algo cambia de comportamiento tras esta migracion, es un bug.
 */
return new class extends Migration
{
    /** Columnas viejas => nuevas. */
    private const COLUMNAS = [
        'cob_id'     => 'rec_id',
        'cob_venid'  => 'rec_venid',
        'cob_mpaid'  => 'rec_mpaid',
        'cob_monto'  => 'rec_monto',
        'cob_nrotrx' => 'rec_nrotrx',
        'cob_banco'  => 'rec_banco',
        'cob_fecha'  => 'rec_fecha',
        'cob_observ' => 'rec_observ',
        'cob_ppaid'  => 'rec_ppaid',
        'cob_usuid'  => 'rec_usuid',
        'cob_estado' => 'rec_estado',
        'cob_fecanu' => 'rec_fecanu',
        'cob_anusid' => 'rec_anusid',
        'cob_motanu' => 'rec_motanu',
        'cob_creado' => 'rec_creado',
    ];

    /** Constraints e indices: viejo => nuevo. */
    private const OBJETOS = [
        'ck_cob_monto'       => 'ck_rec_monto',
        'ck_cob_estado'      => 'ck_rec_estado',
        'ck_cob_anulado'     => 'ck_rec_anulado',
        'fk_cob_ven'         => 'fk_rec_ven',
        'fk_cob_mpa'         => 'fk_rec_mpa',
        'fk_cob_ppa'         => 'fk_rec_ppa',
        'fk_cob_usu'         => 'fk_rec_usu',
        'fk_cob_anusid'      => 'fk_rec_anusid',
        'ix_cob_venta'       => 'ix_rec_venta',
        'ix_cob_fecha'       => 'ix_rec_fecha',
        'uq_cob_ppa_activo'  => 'uq_rec_ppa_activo',
        'cobro_venta_pkey'   => 'recibo_pkey',
    ];

    public function up(): void
    {
        Schema::rename('cobro_venta', 'recibo');

        foreach (self::COLUMNAS as $viejo => $nuevo) {
            DB::statement("ALTER TABLE recibo RENAME COLUMN {$viejo} TO {$nuevo}");
        }

        foreach (self::OBJETOS as $viejo => $nuevo) {
            $this->renombrarObjeto($viejo, $nuevo);
        }

        // La secuencia del serial no se renombra sola.
        DB::statement('ALTER SEQUENCE IF EXISTS cobro_venta_cob_id_seq RENAME TO recibo_rec_id_seq');

        // Numeracion propia del recibo. NULL en los cobros al contado:
        // ahi el documento del cliente es la factura.
        Schema::table('recibo', function (Blueprint $table) {
            $table->string('rec_numero', 20)->nullable()->after('rec_id');
        });

        DB::statement('CREATE UNIQUE INDEX uq_rec_numero
            ON recibo (rec_numero) WHERE rec_numero IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_rec_numero');

        Schema::table('recibo', function (Blueprint $table) {
            $table->dropColumn('rec_numero');
        });

        DB::statement('ALTER SEQUENCE IF EXISTS recibo_rec_id_seq RENAME TO cobro_venta_cob_id_seq');

        foreach (array_reverse(self::OBJETOS, true) as $viejo => $nuevo) {
            $this->renombrarObjeto($nuevo, $viejo);
        }

        foreach (self::COLUMNAS as $viejo => $nuevo) {
            DB::statement("ALTER TABLE recibo RENAME COLUMN {$nuevo} TO {$viejo}");
        }

        Schema::rename('recibo', 'cobro_venta');
    }

    /**
     * Renombra constraint o indice segun lo que sea. Un CHECK y una FK son
     * constraints; un indice (incluido el unique parcial) no, y PostgreSQL
     * los renombra con sentencias distintas. Se consulta el catalogo en vez
     * de adivinar, y se ignora lo que no exista.
     */
    private function renombrarObjeto(string $viejo, string $nuevo): void
    {
        $esConstraint = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?', [$viejo]
        );

        if ($esConstraint) {
            DB::statement("ALTER TABLE recibo RENAME CONSTRAINT {$viejo} TO {$nuevo}");

            return;
        }

        $esIndice = DB::selectOne(
            'SELECT 1 FROM pg_class WHERE relname = ? AND relkind = ?', [$viejo, 'i']
        );

        if ($esIndice) {
            DB::statement("ALTER INDEX {$viejo} RENAME TO {$nuevo}");
        }
    }
};