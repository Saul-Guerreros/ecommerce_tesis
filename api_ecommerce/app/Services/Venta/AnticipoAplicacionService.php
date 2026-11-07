<?php

namespace App\Services\Venta;

use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Venta\NotaCreditoVenta;
use App\Models\Venta\VentaCab;
use App\Services\Servicio\ServicioEstadoService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conciliación del anticipo cuando la factura final que lo aplicó deja de
 * existir como venta (guía 0.54.1, cap. 30).
 *
 *   alEmitirNota()   NC que acredita la final por COMPLETO -> se libera lo
 *                    aplicado (automático, en la transacción de la NC).
 *   liberarManual()  final de neto cero (no admite NC): acción explícita.
 *   alAnularNota()   se anula la NC que liberó -> el anticipo vuelve a
 *                    quedar aplicado, si todavía está disponible.
 *
 * Una NC PARCIAL sobre la final no libera nada: el servicio se pagó igual
 * con el anticipo; lo cobrado de más en la final se devuelve contra esa NC.
 */
class AnticipoAplicacionService
{
    private const TOLERANCIA = 0.01;

    public function __construct(private ServicioEstadoService $estados) {}

    /** Llamar dentro de la transacción de NotaCreditoVentaService::store(). */
    public function alEmitirNota(NotaCreditoVenta $nota, Usuario $usuario): void
    {
        $final = VentaCab::lockForUpdate()->find($nota->ncv_venid);

        if (!$this->aplicaAnticipo($final) || $final->anticipoLiberado()) {
            return;
        }

        if ($final->totalAcreditado() + self::TOLERANCIA < (float) $final->ven_total) {
            return;   // NC parcial: el anticipo sigue aplicado
        }

        $this->liberar($final, $nota, $usuario,
            "la NC {$nota->ncv_nrodoc} acreditó por completo la factura {$final->ven_nrodoc}");
    }

    /** Final de neto cero, o ya acreditada por completo sin liberación. */
    public function liberarManual(SolicitudServicio $s, string $motivo, Usuario $usuario): VentaCab
    {
        return DB::transaction(function () use ($s, $motivo, $usuario) {
            SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->first();

            $final = $s->factura()->lockForUpdate()->first();

            if (!$this->aplicaAnticipo($final)) {
                throw ValidationException::withMessages(['anticipo' => 'La factura final no aplica un anticipo.']);
            }

            if ($final->anticipoLiberado()) {
                throw ValidationException::withMessages(['anticipo' => 'El anticipo de esta factura ya fue liberado.']);
            }

            $netoCero   = (float) $final->ven_total <= self::TOLERANCIA;
            $acreditada = $final->totalAcreditado() + self::TOLERANCIA >= (float) $final->ven_total;

            if (!$netoCero && !$acreditada) {
                throw ValidationException::withMessages([
                    'anticipo' => "Primero emití una nota de crédito por el total de la factura {$final->ven_nrodoc}: "
                        . 'el anticipo se libera automáticamente.',
                ]);
            }

            $this->liberar($final, null, $usuario, $motivo);

            return $final->refresh();
        });
    }

    /**
     * Llamar dentro de la transacción de NotaCreditoVentaService::anular(),
     * ANTES de anular. Solo revierte si esta NC fue la que liberó.
     *
     * @throws DomainException si el anticipo liberado ya se acreditó o devolvió
     */
    public function alAnularNota(NotaCreditoVenta $nota, Usuario $usuario): void
    {
        $final = VentaCab::lockForUpdate()->find($nota->ncv_venid);

        if (!$this->aplicaAnticipo($final) || $final->ven_ncvlib !== $nota->ncv_id) {
            return;
        }

        $anticipo = VentaCab::lockForUpdate()->find($final->ven_antvenid);

        if ($anticipo->disponibleAnticipo() + self::TOLERANCIA < (float) $final->ven_antlib) {
            throw new DomainException(
                "No se puede anular la NC {$nota->ncv_nrodoc}: el anticipo que liberó ya se acreditó "
                . 'o devolvió. Anulá primero esas operaciones.'
            );
        }

        $monto = (float) $final->ven_antlib;

        $final->update([
            'ven_antlib'  => 0,
            'ven_fechlib' => null,
            'ven_usulib'  => null,
            'ven_ncvlib'  => null,
        ]);

        $this->historial($final, $usuario, sprintf(
            'Anticipo de Gs. %s vuelve a quedar aplicado en la factura %s (se anuló la NC %s)',
            $this->gs($monto), $final->ven_nrodoc, $nota->ncv_nrodoc,
        ));
    }

    // ---------- Helpers ----------

    private function liberar(VentaCab $final, ?NotaCreditoVenta $nota, Usuario $usuario, string $motivo): void
    {
        $final->update([
            'ven_antlib'  => $final->ven_antapl,
            'ven_fechlib' => now(),
            'ven_usulib'  => $usuario->usu_id,
            'ven_ncvlib'  => $nota?->ncv_id,
        ]);

        $this->historial($final, $usuario, sprintf(
            'Anticipo de Gs. %s liberado de la factura %s (%s). Queda disponible para acreditar y devolver.',
            $this->gs((float) $final->ven_antapl), $final->ven_nrodoc, $motivo,
        ));
    }

    private function aplicaAnticipo(?VentaCab $v): bool
    {
        return $v !== null
            && $v->ven_tipo === VentaCab::TIPO_VENTA
            && $v->ven_antvenid !== null
            && $v->ven_sseid !== null;
    }

    private function historial(VentaCab $final, Usuario $usuario, string $texto): void
    {
        $s = SolicitudServicio::find($final->ven_sseid);

        if ($s) {
            $this->estados->registrarNota($s, $usuario->usu_id, $texto);
        }
    }

    private function gs(float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}