<?php

namespace App\Services\Pedido;

use App\Models\Carrito\Carrito;
use App\Models\Configuracion\ConfiguracionEnvio;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Pedido\TipoEntrega;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutEntregaService
{
    public function datosIniciales(Usuario $usuario): array
    {
        $persona = $usuario->persona;

        $sucursales = Sucursal::paraRetiro()
            ->with('ciudad')
            ->orderByDesc('suc_princi')
            ->orderBy('suc_nombre')
            ->get();

        $direcciones = $persona->direcciones()
            ->with('ciudad.departamento')
            ->where('dir_estado', 1)
            ->orderByDesc('dir_predet')
            ->orderByDesc('dir_id')
            ->get();

        $config   = ConfiguracionEnvio::actual();
        $subtotal = $this->subtotalCarrito($usuario);

                return [
            'sucursales'    => $sucursales,
            'direcciones'   => $direcciones,
            'titular'       => $this->datosTitular($persona),
            
            // Solo cuenta la modalidad contado: este checkout no procesa
            // los items a credito, que tienen su propio flujo.
            'carrito_vacio' => !Carrito::where('car_idusu', $usuario->usu_id)
                ->where(function ($q) {
                    $q->whereNull('car_modpag')->orWhere('car_modpag', 'contado');
                })
                ->exists(),

            'envio'         => [
                'modo'         => $config->cfe_modo,
                'costo'        => $config->costoEnvioPara($subtotal),
                'gratis_desde' => (float) ($config->cfe_monto_minimo ?? 0),
                'subtotal'     => $subtotal,
                'impuestos'    => $this->impuestosCarrito($usuario),
            ],
        ];
    }

    public function guardar(Usuario $usuario, array $datos): array
    {
        $persona = $usuario->persona;

        $this->verificarCarritoNoVacio($usuario);

        return DB::transaction(function () use ($usuario, $persona, $datos) {
            $receptor = $datos['receptor'] === 'titular'
                ? $this->receptorTitular($persona, $datos)
                : $this->receptorTercero($datos);

            $tipoEntrega = TipoEntrega::porCodigo(
                $datos['modalidad'] === 'retiro' ? TipoEntrega::RETIRO : TipoEntrega::ENVIO
            );

            $entrega = $datos['modalidad'] === 'retiro'
                ? $this->resolverRetiro((int) $datos['suc_id'])
                : $this->resolverEnvio($persona, (int) $datos['dir_id'], $usuario);

            $entrega = array_merge($entrega, $receptor, [
                'modalidad'  => $datos['modalidad'],
                'receptor'   => $datos['receptor'],
                'ten_id'     => $tipoEntrega->ten_id,
                'ten_codigo' => $tipoEntrega->ten_codigo,
            ]);

            PedidoTemporal::updateOrCreate(
                ['ptm_usuid' => $usuario->usu_id],
                [
                    'ptm_entrega' => $entrega,
                    'ptm_paso'    => PedidoTemporal::PASO_ENTREGA,
                ]
            );

            return $entrega;
        });
    }

    public function borrador(Usuario $usuario): ?array
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();

        return $temporal?->ptm_entrega;
    }

    // ---------- Receptor ----------

    private function receptorTitular(Persona $persona, array $datos): array
{
    $cedula = $persona->per_cedula;

    if (blank($cedula)) {
        if (blank($datos['rec_nrodoc'] ?? null)) {
            throw ValidationException::withMessages([
                'rec_nrodoc' => 'Necesitamos tu numero de cedula para preparar la entrega.',
            ]);
        }

        $ocupada = Persona::where('per_tpdid', $persona->per_tpdid)
            ->where('per_cedula', $datos['rec_nrodoc'])
            ->where('per_id', '<>', $persona->per_id)
            ->exists();

        if ($ocupada) {
            throw ValidationException::withMessages([
                'rec_nrodoc' => 'Ese numero de cedula ya esta registrado en otra cuenta.',
            ]);
        }

        $cedula = $datos['rec_nrodoc']; // ya no se persiste aquí
    }

    return [
        'rec_nrodoc' => $cedula,
        'rec_nombre' => $persona->per_nombr1,
        'rec_apelli' => $persona->per_apell1,
        'rec_telef'  => $this->telefonoDe($persona),
    ];
}

    private function receptorTercero(array $datos): array
    {
        return [
            'rec_nrodoc' => $datos['rec_nrodoc'],
            'rec_nombre' => $datos['rec_nombre'],
            'rec_apelli' => $datos['rec_apelli'],
            'rec_telef'  => $datos['rec_telef'],
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

    // ---------- Modalidad ----------

    private function resolverRetiro(int $sucursalId): array
    {
        $sucursal = Sucursal::paraRetiro()->with('ciudad')->findOrFail($sucursalId);

        return [
            'suc_id'        => $sucursal->suc_id,
            'suc_nombre'    => $sucursal->suc_nombre,
            'suc_direccion' => $sucursal->suc_direccion,
            'suc_horario'   => $sucursal->suc_horario,
            'suc_ciudad'    => $sucursal->ciudad?->ciu_descri,
            'costo_envio'   => 0.0,
        ];
    }

    // TODO (Parte 2 - Checkout): cuando cfe_modo === 'sucursal', costoEnvioPara()
    // debe delegarse en EnvioService::resolverSucursalYCosto() para elegir la
    // sucursal con stock y menor tarifa según la ciudad de destino. Hoy ese modo
    // sigue devolviendo cfe_costo_fijo (null -> 0) como fallback, así que nada
    // rompe mientras se implementa la Parte 2.
    private function resolverEnvio(Persona $persona, int $direccionId, Usuario $usuario): array
    {
        $direccion = $persona->direcciones()
            ->with('ciudad.departamento')
            ->findOrFail($direccionId);

        $costo = ConfiguracionEnvio::actual()
            ->costoEnvioPara($this->subtotalCarrito($usuario));

        return [
            'dir_id'       => $direccion->dir_id,
            'dir_alias'    => $direccion->dir_alias,
            'departamento' => $direccion->ciudad?->departamento?->dep_descri,
            'ciudad'       => $direccion->ciudad?->ciu_descri,
            'barrio'       => $direccion->dir_barrio,
            'calle'        => $direccion->dir_calle,
            'nrocasa'      => $direccion->dir_nrocasa,
            'referencia'   => $direccion->dir_referencia,
            'costo_envio'  => $costo,
        ];
    }

    // ---------- Carrito ----------

    private function subtotalCarrito(Usuario $usuario): float
    {
        return (float) Carrito::where('car_idusu', $usuario->usu_id)->sum('car_total');
    }

    /**
     * Mismo criterio que CarritoResource::car_iva (punto 16 del carrito):
     * suma del IVA YA CONTENIDO en cada línea, vía Producto::ivaIncluidoEn().
     * Nunca "subtotal / 11": el sistema tiene 3 tasas (0%, 5%, 10%) y esa
     * división solo sería correcta si todo el carrito estuviera al 10%.
     */
    private function impuestosCarrito(Usuario $usuario): float
    {
        return (float) Carrito::where('car_idusu', $usuario->usu_id)
            ->with('producto.tipoIva')
            ->get()
            ->sum(fn ($item) => $item->producto
                ? $item->producto->ivaIncluidoEn((float) $item->car_total)
                : 0.0);
    }

    private function verificarCarritoNoVacio(Usuario $usuario): void
    {
        $hayItems = Carrito::where('car_idusu', $usuario->usu_id)->exists();

        if (!$hayItems) {
            throw ValidationException::withMessages([
                'modalidad' => 'Tu carrito esta vacio. Agrega productos antes de continuar.',
            ]);
        }
    }

    private function datosTitular(Persona $persona): array
    {
        return [
            'rec_nrodoc' => $persona->per_cedula,
            'rec_nombre' => $persona->per_nombr1,
            'rec_apelli' => $persona->per_apell1,
            'rec_telef'  => $this->telefonoDe($persona),
        ];
    }
}