<?php
 
namespace App\Services\Precio;
 
use App\Models\ConfiguracionPrecio;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
 
class PriceService
{
    private const IVA_DEFECTO = 0;
 
    /**
     * Margen efectivo (%) para el tipo indicado: el override individual
     * del producto si existe, o si no, el valor general de la tienda.
     * Nunca devuelve null — siempre hay al menos el general.
     */
    public function margenEfectivo(Producto $producto, string $tipo): float
    {
        $general = ConfiguracionPrecio::actual();
 
        return match ($tipo) {
            'minorista' => (float) ($producto->prd_marmin ?? $general->cpr_marmin),
            'mayorista' => (float) ($producto->prd_marmaj ?? $general->cpr_marmaj),
            default     => throw new \InvalidArgumentException("Tipo de margen inválido: {$tipo}"),
        };
    }
 
    /**
     * Cantidad mínima mayorista efectiva: override del producto si
     * existe, o si no, el umbral general.
     */
    public function umbralEfectivo(Producto $producto): float
    {
        $general = ConfiguracionPrecio::actual();
 
        return (float) ($producto->prd_canminmaj ?? $general->cpr_canminmaj);
    }
 
    /**
     * Calcula ambos precios finales (con IVA) a partir del costo
     * promedio ponderado. No persiste nada — ver aplicarPrecios().
     *
     * precio_sin_iva = costo_promedio * (1 + margen/100)
     * precio_final   = precio_sin_iva * (1 + tasa_iva/100)
     *
     * Requiere que $producto tenga cargada la relación tipoIva
     * (Producto::with('tipoIva')) para no disparar una query N+1.
     */
    public function calcularPrecios(Producto $producto, float $costoPromedio): array
    {
        $tasaIva = (float) ($producto->tipoIva->tiv_tasa ?? self::IVA_DEFECTO);
 
        $margenMin = $this->margenEfectivo($producto, 'minorista');
        $margenMaj = $this->margenEfectivo($producto, 'mayorista');
 
        $sinIvaMin = $costoPromedio * (1 + $margenMin / 100);
        $sinIvaMaj = $costoPromedio * (1 + $margenMaj / 100);
 
        return [
            'prd_pvtmin' => round($sinIvaMin * (1 + $tasaIva / 100), 2),
            'prd_pvtmay' => round($sinIvaMaj * (1 + $tasaIva / 100), 2),
        ];
    }
 
    /**
     * Recalcula y persiste prd_pvtmin/prd_pvtmay a partir del costo
     * promedio. Único punto de entrada usado tanto por CompraService
     * (al confirmar una entrada de stock) como por PrecioController
     * (al editar un override individual o la configuración general).
     *
     * Si costoPromedio es null o 0 (producto sin stock/compra todavía),
     * no toca el precio: no hay base para calcular nada, y forzar un
     * valor inventado violaría la regla de "no inventar datos".
     */
    public function aplicarPrecios(Producto $producto, ?float $costoPromedio): void
    {
        if ($costoPromedio === null || $costoPromedio <= 0) {
            return;
        }
 
        $precios = $this->calcularPrecios($producto, $costoPromedio);
 
        $producto->prd_pvtmin = $precios['prd_pvtmin'];
        $producto->prd_pvtmay = $precios['prd_pvtmay'];
        $producto->save();
    }
 
    /**
     * Recalcula todos los productos que usan el margen/umbral GENERAL
     * (es decir, sin override individual). Se llama cuando se modifica
     * la configuración general, para no dejar precios desactualizados
     * en el resto del catálogo. Los productos con override individual
     * NO se tocan: sus valores fueron una decisión explícita.
     */
    public function recalcularProductosConMargenGeneral(): void
    {
        Producto::with('tipoIva')
            ->where(function ($q) {
                $q->whereNull('prd_marmin')->orWhereNull('prd_marmaj');
            })
            ->chunk(50, function ($productos) {
                foreach ($productos as $producto) {
                    $costoPromedio = DB::table('stock')
                        ->where('sto_prdid', $producto->prd_id)
                        ->selectRaw('SUM(sto_cantid * sto_cosprm) / NULLIF(SUM(sto_cantid), 0) AS cpp')
                        ->value('cpp');
 
                    $this->aplicarPrecios($producto, $costoPromedio !== null ? (float) $costoPromedio : null);
                }
            });
    }
}
 
