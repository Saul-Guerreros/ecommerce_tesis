<?php

namespace Database\Seeders;

use App\Models\Compras\CompraCab;
use App\Models\Compras\Proveedor;
use App\Models\Compras\TimbradoProveedor;
use App\Models\Compras\TipoComprobante;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sucursal;
use App\Models\Producto;
use App\Services\Compras\CompraService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use RuntimeException;

/**
 * Aumenta en 30 unidades el stock de TODOS los productos del CatalogoSeeder.
 *
 * Para que el sistema quede consistente (stock, movimiento_stock, libro_compra
 * y costo/precio de venta) NO toca la tabla stock directamente: registra una
 * compra por proveedor a través de CompraService, igual que el CatalogoSeeder.
 *
 *  - Usa la misma lista de productos, proveedores y precios de compra que
 *    CatalogoSeeder (leídos por reflexión, sin modificar ese archivo), así que
 *    el costo no cambia y los precios de venta se mantienen.
 *  - Cada producto se compra al mismo proveedor que en la carga inicial.
 *  - Idempotente: si ya existe la compra de este seeder para un proveedor,
 *    la omite (evita sumar +60, +90... al ejecutarlo varias veces).
 *
 * Ejecutar:  php artisan db:seed --class=AumentarStockSeeder
 */
class AumentarStockSeeder extends Seeder
{
    private const CANTIDAD       = 30;   // unidades a sumar por producto
    private const TIMBRADO_VENCE = '2027-12-31';
    private const OBS_COMPRA     = 'Aumento de stock +30 unidades - Tesis Z Informatica';

    public function run(CompraService $compras): void
    {
        $catalogo = new CatalogoSeeder();
        $ref      = new ReflectionClass($catalogo);
        $productos   = $ref->getProperty('productos')->getValue($catalogo);
        $proveedores = $ref->getProperty('proveedores')->getValue($catalogo);

        DB::transaction(function () use ($compras, $productos, $proveedores) {
            $ivaId     = $this->idOrFail('tipo_iva', 'tiv_id', 'tiv_tasa', 10, 'IVA 10%');
            $unidadId  = DB::table('unidad_medida')->whereIn('umd_codigo', ['UNI', 'UN'])->value('umd_id')
                ?? throw new RuntimeException('Falta la unidad de medida "Unidad".');
            $facturaId = TipoComprobante::idFactura()
                ?? throw new RuntimeException('Falta el tipo de comprobante Factura (código 001).');

            [$sucId, $depId] = $this->sucursalYDeposito();

            // Armar las líneas de compra agrupadas por RUC de proveedor
            $lineas = [];
            foreach ($productos as $i => $p) {
                $producto = Producto::where('prd_codinv', $p['cod'])->first();

                if (! $producto) {
                    $this->command?->warn("Producto no encontrado, se omite: {$p['cod']}");
                    continue;
                }

                $ruc = $proveedores[$i % count($proveedores)]['ruc'];

                $lineas[$ruc][] = [
                    'prd_id' => $producto->prd_id,
                    'umd_id' => $unidadId,
                    'tiv_id' => $ivaId,
                    'descri' => $p['nombre'],
                    'precun' => $p['precio'],
                    'canfac' => self::CANTIDAD,
                    'canrec' => self::CANTIDAD,
                ];
            }

            $total = 0;

            foreach ($lineas as $ruc => $detalle) {
                $prov = Proveedor::where('pro_ruc', $ruc)->first()
                    ?? throw new RuntimeException("Falta el proveedor con RUC $ruc. Ejecutá CatalogoSeeder antes.");

                // Idempotencia: ya se hizo la compra de aumento para este proveedor
                $yaExiste = DB::table('compra_cab')
                    ->where('com_proid', $prov->pro_id)
                    ->where('com_obs', self::OBS_COMPRA)
                    ->exists();

                if ($yaExiste) {
                    $this->command?->warn("{$prov->pro_razon}: ya tiene la compra de aumento de stock, se omite.");
                    continue;
                }

                $timbrado = TimbradoProveedor::where('tpv_proid', $prov->pro_id)
                    ->where('tpv_tcoid', $facturaId)
                    ->where('tpv_estado', 1)
                    ->first()
                    ?? throw new RuntimeException("Falta el timbrado activo de {$prov->pro_razon}.");

                $compras->store([
                    'com_proid'  => $prov->pro_id,
                    'com_proruc' => $ruc,
                    'com_proraz' => $prov->pro_razon,
                    'com_depid'  => $depId,
                    'com_sucid'  => $sucId,
                    'com_tcoid'  => $facturaId,
                    'com_tpvid'  => $timbrado->tpv_id,
                    'com_timbra' => $timbrado->tpv_numero,
                    'com_timbvn' => self::TIMBRADO_VENCE,
                    'com_nrodoc' => $this->siguienteNroDoc($prov->pro_id),
                    'com_fchaem' => now()->toDateString(),
                    'com_condic' => CompraCab::CONDICION_CONTADO,
                    'com_obs'    => self::OBS_COMPRA,
                ], $detalle);

                $total += count($detalle);
                $this->command?->info("Factura {$prov->pro_razon}: +" . self::CANTIDAD . ' unidades en ' . count($detalle) . ' productos');
            }

            $this->command?->info("Stock aumentado en " . self::CANTIDAD . " unidades para $total productos.");
        });
    }

    /** @return array{0:int,1:int} [sucursal principal, depósito principal] */
    private function sucursalYDeposito(): array
    {
        $empresa = Empresa::first()
            ?? throw new RuntimeException('No hay empresa cargada. Ejecutá CatalogoSeeder antes.');

        $sucursal = Sucursal::where('suc_empid', $empresa->emp_id)
            ->where('suc_princi', 1)->where('suc_estado', 1)->firstOrFail();

        $depId = Deposito::where('dpo_sucid', $sucursal->suc_id)
            ->where('dpo_princi', 1)->where('dpo_estado', 1)->value('dpo_id')
            ?? throw new RuntimeException('La sucursal principal no tiene depósito principal.');

        return [$sucursal->suc_id, $depId];
    }

    private function siguienteNroDoc(int $proveedorId): string
    {
        $ultimo = DB::table('compra_cab')->where('com_proid', $proveedorId)->max('com_nrodoc');
        $n = $ultimo ? ((int) substr($ultimo, -7)) + 1 : 1;

        return sprintf('001-001-%07d', $n);
    }

    private function idOrFail(string $tabla, string $pk, string $col, $valor, string $nombre): int
    {
        return DB::table($tabla)->where($col, $valor)->value($pk)
            ?? throw new RuntimeException("Falta \"$nombre\" en $tabla.");
    }
}
