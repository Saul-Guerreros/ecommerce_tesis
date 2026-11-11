<?php

namespace App\Http\Resources\Venta;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Venta\CuotaPagoMedio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pantalla final del pago de cuotas.
 *
 * El caso lo clasifica el backend y no la pantalla, igual que en
 * ResultadoPagoResource: depende de que metodos exigen comprobante, que
 * es un dato del catalogo metodo_pago.
 *
 *   REVISION -> todo lleva comprobante  ("Comprobante enviado")
 *   PARCIAL  -> comprobante + efectivo/cheque
 *   CHEQUE   -> cheque, sin comprobantes ("Pago seleccionado")
 *   EFECTIVO -> solo efectivo           ("Pago seleccionado")
 */
class ResultadoPagoCuotaResource extends JsonResource
{
    /** Pedido al que pertenecen las cuotas, para armar los enlaces. */
    public function __construct($resource, private string $numeroPedido, private ?array $sucursal = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $medios = $this->medios;

        $conComprobante = $medios->filter(fn ($m) => (bool) $m->metodoPago?->mpa_requcom);
        $cheques        = $medios->filter(fn ($m) => $m->metodoPago?->mpa_codigo === MetodoPago::CHEQUE);
        $efectivo       = $medios->filter(fn ($m) => $m->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA);

        return [
            'cpa_id'     => (int) $this->cpa_id,
            'ped_numero' => $this->numeroPedido,
            'fecha'      => $this->cpa_creado?->toIso8601String(),
            'caso'       => $this->clasificar($conComprobante->isNotEmpty(), $cheques->isNotEmpty(), $efectivo->isNotEmpty()),

            'total'             => round((float) $this->cpa_monto, 2),
            'monto_en_revision' => round((float) $conComprobante->sum('cpm_monto'), 2),
            'monto_efectivo'    => round((float) $efectivo->sum('cpm_monto'), 2),

            'cuotas' => $this->detalle->map(fn ($d) => [
                'numero'  => (int) ($d->cuota?->cco_nrocuo ?? 0),
                'importe' => round((float) $d->cpd_monto, 2),
            ])->values(),

            'medios' => $medios->map(fn (CuotaPagoMedio $m) => [
                'cpm_id'            => (int) $m->cpm_id,
                'orden'             => (int) $m->cpm_orden,
                'mpa_codigo'        => $m->metodoPago?->mpa_codigo,
                'mpa_descri'        => $this->describirMetodo($m),
                'monto'             => round((float) $m->cpm_monto, 2),
                'tiene_comprobante' => $m->comprobante !== null,
            ])->values(),

            'cheque' => $cheques->isEmpty() ? null : $this->armarCheque($cheques),
        ];
    }

    private function clasificar(bool $comprobante, bool $cheque, bool $efectivo): string
    {
        if ($comprobante && ($cheque || $efectivo)) {
            return 'PARCIAL';
        }

        if ($comprobante) {
            return 'REVISION';
        }

        return $cheque ? 'CHEQUE' : 'EFECTIVO';
    }

    /**
     * "Efectivo, pago en sucursal" en vez de "Contra entrega": la cuota se
     * abona en el local, el pedido ya se entrego hace rato.
     */
    private function describirMetodo(CuotaPagoMedio $medio): string
    {
        return $medio->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA
            ? 'Efectivo, pago en sucursal'
            : (string) $medio->metodoPago?->mpa_descri;
    }

    private function armarCheque($cheques): array
    {
        $primero = $cheques->first()->cheque;

        return [
            'monto'   => round((float) $cheques->sum('cpm_monto'), 2),
            'cheques' => $cheques->map(fn (CuotaPagoMedio $m) => [
                'orden' => (int) $m->cpm_orden,
                'monto' => round((float) $m->cpm_monto, 2),
            ])->values(),

            'titular'     => \App\Models\Configuracion\ConfiguracionTienda::actual()->ctn_pago_cheque_titular,
            'vence'       => $primero?->cch_fevcto?->toIso8601String(),
            'horas_plazo' => PagoCheque::HORAS_PLAZO,
            'sucursal'    => $this->sucursal,
        ];
    }
}