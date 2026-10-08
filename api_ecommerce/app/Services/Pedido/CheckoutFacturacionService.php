<?php

namespace App\Services\Pedido;

use App\Models\Pedido\PedidoTemporal;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutFacturacionService
{
    public function __construct(
        private CheckoutResumenService $resumen,
    ) {}

    public function datosIniciales(Usuario $usuario): array
    {
        $persona  = $usuario->persona;
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();
        $entrega  = $temporal?->ptm_entrega;

        // El resumen ahora sale de CheckoutResumenService, el mismo que usan
        // el pago dividido y la confirmacion. Antes se calculaba aca sin
        // filtrar la modalidad: los items a credito inflaban el total que
        // veia el cliente en el Paso 2.
        $resumen = $this->resumen->resumen($usuario, $entrega);

        return [
            'titular' => [
                'numero'   => $persona->per_cedula,
                'nombre'   => $persona->per_nombr1,
                'apellido' => $persona->per_apell1,
                'telefono' => $this->telefonoDe($persona),
                'correo'   => $usuario->usu_login,
            ],
            'datos_fiscales' => $persona->datosFiscales()
                ->orderByDesc('dfi_principal')
                ->orderByDesc('dfi_id')
                ->get(),
            'entrega' => $entrega,
            'resumen' => [
                'subtotal'    => $resumen['subtotal'],
                'impuestos'   => $resumen['impuestos'],
                'costo_envio' => $resumen['costo_envio'],
                'total'       => $resumen['total'],
            ],
        ];
    }

    public function guardar(Usuario $usuario, array $datos): array
    {
        $persona = $usuario->persona;
        $this->verificarEntregaPrevia($usuario);

        return DB::transaction(function () use ($usuario, $persona, $datos) {
            $facturacion = $datos['tipo_documento'] === 'CI'
                ? $this->resolverCi($persona, $usuario)
                : $this->resolverRuc($persona, $datos);

            $facturacion['tipo_documento'] = $datos['tipo_documento'];
            $facturacion['pfa_recibe'] = $datos['pfa_recibe'];

            PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->update([
                'ptm_factura' => $facturacion,
                'ptm_paso'    => PedidoTemporal::PASO_FACTURACION,
            ]);

            return $facturacion;
        });
    }

    public function borrador(Usuario $usuario): ?array
    {
        return PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first()?->ptm_factura;
    }

    private function resolverCi(Persona $persona, Usuario $usuario): array
    {
        return [
            'dfi_id'       => null,
            'pfa_nrodoc'   => $persona->per_cedula,
            'pfa_raznom'   => trim("{$persona->per_nombr1} {$persona->per_apell1}"),
            'pfa_telefono' => $this->telefonoDe($persona),
            'pfa_correo'   => $usuario->usu_login,
        ];
    }

    private function resolverRuc(Persona $persona, array $datos): array
    {
        if (!empty($datos['dfi_id'])) {
            $datoFiscal = $persona->datosFiscales()->findOrFail($datos['dfi_id']);

            return [
                'dfi_id'       => $datoFiscal->dfi_id,
                'pfa_nrodoc'   => $datoFiscal->dfi_numero,
                'pfa_raznom'   => $datoFiscal->dfi_razon,
                'pfa_telefono' => $datoFiscal->dfi_telefono,
                'pfa_correo'   => $datoFiscal->dfi_correo,
            ];
        }

        // RUC de uso único: no se persiste en dato_fiscal
        return [
            'dfi_id'       => null,
            'pfa_nrodoc'   => $datos['pfa_nrodoc'],
            'pfa_raznom'   => $datos['pfa_raznom'],
            'pfa_telefono' => $datos['pfa_telefono'],
            'pfa_correo'   => $datos['pfa_correo'],
        ];
    }

    private function telefonoDe(Persona $persona): ?string
    {
        $persona->loadMissing('contactos');

        return $persona->contactos
            ->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])
            ->where('con_estado', 1)
            ->sortBy(fn ($c) => $c->con_tipo === 'CELULAR' ? 0 : 1)
            ->first()?->con_valor;
    }

    private function verificarEntregaPrevia(Usuario $usuario): void
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();

        if (!$temporal || empty($temporal->ptm_entrega)) {
            throw ValidationException::withMessages([
                'tipo_documento' => 'Completá primero los datos de entrega.',
            ]);
        }
    }
}