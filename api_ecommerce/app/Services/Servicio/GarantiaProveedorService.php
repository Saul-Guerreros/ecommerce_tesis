<?php

namespace App\Services\Servicio;

use App\Models\Compras\Proveedor;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\GarantiaProveedor;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garantía de COMPRA: la cubre la empresa responsable del producto (distribuidora o
 * fabricante), no el taller. El técnico no diagnostica ni presupuesta: recibe el
 * equipo, lo gestiona con la empresa y, cuando vuelve, avisa al cliente que está listo.
 *
 *   RECIBIDA -> GESTION_GARANTIA -> EN_EMPRESA -> LISTA -> ENTREGADA
 *                       \------------------------^   (si la empresa no la acepta sin recibirlo)
 *
 * La entrega al cliente (LISTA -> ENTREGADA) la hace el EntregaServicioService de siempre.
 * El reclamo de una reparación hecha por el propio taller (sse_sseori) NO pasa por acá.
 *
 * Toda operación es transaccional y pasa por ServicioEstadoService (historial + aviso al cliente).
 */
class GarantiaProveedorService
{
    /** Campos editables del reclamo mientras está en curso. */
    private const CAMPOS = ['gpr_proid', 'gpr_nrocaso', 'gpr_contacto', 'gpr_obs'];

    private const EN_CURSO = [EstadoServicio::GESTION_GARANTIA, EstadoServicio::EN_EMPRESA];

    public function __construct(
        private ServicioEstadoService $estados,
        private ServicioTotalesService $totales,
    ) {}

    /** Abre la gestión: la empresa se propone desde el producto comprado (el técnico puede cambiarla). */
    public function iniciar(SolicitudServicio $s, Usuario $usuario): GarantiaProveedor
    {
        $this->exigirGarantiaDeCompra($s);

        if ($s->codigoServicio() !== EstadoServicio::RECIBIDA) {
            throw ValidationException::withMessages(['garantia' => 'Solo se puede iniciar la gestión de un equipo recibido.']);
        }
        if ($s->garantiaProveedor()->exists()) {
            throw ValidationException::withMessages(['garantia' => 'Esta solicitud ya tiene una gestión de garantía.']);
        }

        return DB::transaction(function () use ($s, $usuario) {
            $empresaId = $s->detallePedido?->producto?->prd_proid;

            $gestion = GarantiaProveedor::create([
                'gpr_sseid' => $s->sse_id,
                'gpr_proid' => $empresaId,
                'gpr_usuid' => $usuario->usu_id,
            ]);

            // La garantía no se cobra: ni el retiro. Deja los totales en 0 y sin eje de pago.
            $this->totales->consolidar($s, 0, 0, $usuario->usu_id);

            $empresa = $empresaId ? Proveedor::find($empresaId)?->pro_razon : null;
            $this->estados->cambiarServicio(
                $s, EstadoServicio::GESTION_GARANTIA, $usuario->usu_id,
                'Gestión de garantía con la empresa iniciada' . ($empresa ? " ({$empresa})" : '')
            );

            return $gestion->load('proveedor');
        });
    }

    /** Actualiza los datos del reclamo (empresa, número de caso, contacto, observaciones). */
    public function guardar(SolicitudServicio $s, array $d, Usuario $usuario): GarantiaProveedor
    {
        $gestion = $this->enCurso($s);

        return DB::transaction(function () use ($s, $gestion, $d, $usuario) {
            $gestion->fill(Arr::only($d, self::CAMPOS))->save();

            $this->estados->registrarNota($s, $usuario->usu_id, 'Datos de la gestión de garantía actualizados');

            return $gestion->fresh('proveedor');
        });
    }

    /** El producto sale hacia la empresa. Exige empresa elegida y fecha de envío. */
    public function enviar(SolicitudServicio $s, array $d, Usuario $usuario): GarantiaProveedor
    {
        $gestion = $this->enCurso($s);

        if ($s->codigoServicio() !== EstadoServicio::GESTION_GARANTIA) {
            throw ValidationException::withMessages(['garantia' => 'El producto ya fue enviado a la empresa.']);
        }

        $gestion->fill(Arr::only($d, self::CAMPOS));

        if (!$gestion->gpr_proid) {
            throw ValidationException::withMessages(['gpr_proid' => 'Seleccioná la empresa responsable de la garantía.']);
        }

        return DB::transaction(function () use ($s, $gestion, $d, $usuario) {
            $gestion->gpr_fecenv = $d['gpr_fecenv'];
            $gestion->save();

            $empresa = Proveedor::find($gestion->gpr_proid)?->pro_razon;
            $this->estados->cambiarServicio(
                $s, EstadoServicio::EN_EMPRESA, $usuario->usu_id,
                'Enviado a la empresa responsable de la garantía' . ($empresa ? " ({$empresa})" : '')
            );

            return $gestion->fresh('proveedor');
        });
    }

    /**
     * Cierra la gestión: el producto vuelve al local (reparado, reemplazado o sin cobertura)
     * y la solicitud queda LISTA, lo que avisa al cliente.
     */
    public function resolver(SolicitudServicio $s, array $d, Usuario $usuario): GarantiaProveedor
    {
        $gestion   = $this->enCurso($s);
        $estado    = $s->codigoServicio();
        $resultado = $d['gpr_result'];

        // Sin haberlo enviado, lo único posible es cerrar por falta de cobertura.
        if ($estado === EstadoServicio::GESTION_GARANTIA && $resultado !== GarantiaProveedor::RECHAZADO) {
            throw ValidationException::withMessages([
                'gpr_result' => 'El producto todavía no fue enviado a la empresa: solo puede cerrarse como garantía no aceptada.',
            ]);
        }

        // El motivo del rechazo lo ve el cliente: tiene que indicarse acá, no heredar notas internas.
        if ($resultado === GarantiaProveedor::RECHAZADO && blank($d['gpr_obs'] ?? null)) {
            throw ValidationException::withMessages(['gpr_obs' => 'Indique el motivo por el que no se aceptó la garantía.']);
        }
        $obs = $d['gpr_obs'] ?? $gestion->gpr_obs;

        $fecrec = $estado === EstadoServicio::EN_EMPRESA ? ($d['gpr_fecrec'] ?? today()->toDateString()) : null;
        if ($fecrec && $gestion->gpr_fecenv && $fecrec < $gestion->gpr_fecenv->toDateString()) {
            throw ValidationException::withMessages(['gpr_fecrec' => 'La fecha de recepción no puede ser anterior a la de envío.']);
        }

        return DB::transaction(function () use ($s, $gestion, $d, $usuario, $resultado, $fecrec, $obs) {
            $gestion->fill([
                'gpr_result'  => $resultado,
                'gpr_fecrec'  => $fecrec,
                'gpr_nroserr' => $resultado === GarantiaProveedor::REEMPLAZADO ? ($d['gpr_nroserr'] ?? null) : null,
                'gpr_obs'     => $obs,
            ])->save();

            // El texto viaja al historial y al correo "Tu equipo está listo": redactado para el cliente.
            $this->estados->cambiarServicio($s, EstadoServicio::LISTA, $usuario->usu_id, $this->mensajeParaCliente($resultado, $obs));

            return $gestion->fresh('proveedor');
        });
    }

    // ---------- Auxiliares ----------

    private function mensajeParaCliente(string $resultado, ?string $obs): string
    {
        return match ($resultado) {
            GarantiaProveedor::REPARADO    => 'La empresa reparó tu producto',
            GarantiaProveedor::REEMPLAZADO => 'La empresa te entregó un producto de reemplazo del mismo modelo',
            GarantiaProveedor::RECHAZADO   => 'La empresa no aceptó la garantía' . ($obs ? ": {$obs}" : ''),
        };
    }

    private function exigirGarantiaDeCompra(SolicitudServicio $s): void
    {
        if (!$s->esGarantiaDeCompra()) {
            throw ValidationException::withMessages([
                'garantia' => 'Esta solicitud no es una garantía de compra: se atiende con diagnóstico y presupuesto.',
            ]);
        }
    }

    /** La gestión existe y sigue en curso (no resuelta). */
    private function enCurso(SolicitudServicio $s): GarantiaProveedor
    {
        $this->exigirGarantiaDeCompra($s);

        $gestion = $s->garantiaProveedor;
        if (!$gestion) {
            throw ValidationException::withMessages(['garantia' => 'Primero inicie la gestión de garantía.']);
        }
        if (!in_array($s->codigoServicio(), self::EN_CURSO, true) || $gestion->resuelta()) {
            throw ValidationException::withMessages(['garantia' => 'La gestión de garantía ya fue resuelta.']);
        }

        return $gestion;
    }
}
