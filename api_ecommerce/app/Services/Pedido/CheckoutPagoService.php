<?php

namespace App\Services\Pedido;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Paso 2 del checkout, seccion "Metodo de Pago".
 *
 * Guarda el borrador en pedido_temporal.ptm_pago con esta forma unica,
 * valga un solo metodo o varios:
 *
 *   {
 *     "modo": "unico" | "dividido",
 *     "pagos": [
 *       { "mpa_codigo", "mpa_descri", "monto", "comprobante_temp" }
 *     ]
 *   }
 *
 * Los comprobantes se guardan en comprobantes_temp y recien se convierten
 * en filas de pago_comprobante cuando el Paso 3 confirma el pedido.
 */
class CheckoutPagoService
{
    private const CARPETA_TEMP = 'comprobantes_temp';

    public function __construct(
        private CheckoutResumenService $resumen,
    ) {}

    public function datosIniciales(): array
    {
        $config = ConfiguracionTienda::actual();

        return [
            'metodos' => MetodoPago::activos()->orderBy('mpa_id')->get(),
            'datos_transferencia' => [
                'banco'   => $config->ctn_pago_banco,
                'titular' => $config->ctn_pago_banco_titular,
                'cuenta'  => $config->ctn_pago_banco_cuenta,
            ],
            'datos_billetera' => [
                'titular'    => $config->ctn_pago_tigo_titular,
                'numero'     => $config->ctn_pago_tigo_numero,
                'referencia' => $config->ctn_pago_tigo_referencia,
            ],
            'mensaje_efectivo' => $config->ctn_pago_efectivo_mensaje,
        ];
    }

    public function borrador(Usuario $usuario): ?array
    {
        return PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first()?->pagoNormalizado();
    }

    /**
     * Total que el cliente debe cubrir. Se expone para que el Paso 2 pueda
     * mostrar "Total a pagar" sin recalcularlo por su cuenta.
     */
    public function totalExigible(Usuario $usuario): int
    {
        $entrega = $this->resumen->entregaDelBorrador($usuario);

        return $this->resumen->totalExigible($usuario, $entrega);
    }

    /**
     * @param  array  $datos  $request->validated()
     */
    public function guardar(Usuario $usuario, array $datos): array
    {
        $this->verificarFacturacionPrevia($usuario);

        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();
        $anterior = $temporal->pagoNormalizado();
        $total    = $this->resumen->totalExigible($usuario, $temporal->ptm_entrega);

        $esDividido = ($datos['modo'] ?? PedidoTemporal::MODO_UNICO) === PedidoTemporal::MODO_DIVIDIDO;

        $pago = $esDividido
            ? $this->armarDividido($datos['pagos'] ?? [], $total, $anterior)
            : $this->armarUnico($datos, $total, $anterior);

        return DB::transaction(function () use ($temporal, $anterior, $pago) {
            $this->eliminarComprobantesHuerfanos($anterior, $pago);

            $temporal->update([
                'ptm_pago' => $pago,
                'ptm_paso' => PedidoTemporal::PASO_FACTURACION,
            ]);

            return $pago;
        });
    }

    // ---------- Modo "un solo metodo de pago" ----------

    /**
     * Mantiene exactamente el comportamiento anterior al pago dividido:
     * si el metodo exige comprobante y el cliente no adjunta uno nuevo, se
     * reutiliza el que ya habia cargado en este mismo borrador.
     */
    private function armarUnico(array $datos, int $total, ?array $anterior): array
    {
        $metodo      = MetodoPago::porCodigo($datos['mpa_codigo']);
        $comprobante = $datos['comprobante'] ?? null;

        $rutaAnterior = ($anterior['modo'] ?? null) === PedidoTemporal::MODO_UNICO
            ? ($anterior['pagos'][0]['comprobante_temp'] ?? null)
            : null;

        if ($metodo->mpa_requcom && !$comprobante instanceof UploadedFile && !$this->existe($rutaAnterior)) {
            throw ValidationException::withMessages([
                'comprobante' => 'Este método de pago requiere adjuntar el comprobante.',
            ]);
        }

        $ruta = $comprobante instanceof UploadedFile
            ? $comprobante->store(self::CARPETA_TEMP, 'public')
            : ($metodo->mpa_requcom ? $rutaAnterior : null);

        return [
            'modo'  => PedidoTemporal::MODO_UNICO,
            'pagos' => [[
                'mpa_codigo'       => $metodo->mpa_codigo,
                'mpa_descri'       => $metodo->mpa_descri,
                'monto'            => $total,
                'comprobante_temp' => $ruta,
            ]],
        ];
    }

    // ---------- Modo "dividir el pago" ----------

    private function armarDividido(array $pagos, int $total, ?array $anterior): array
    {
        $this->verificarMontos($pagos, $total);

        $anteriores = ($anterior['modo'] ?? null) === PedidoTemporal::MODO_DIVIDIDO
            ? ($anterior['pagos'] ?? [])
            : [];

        $resultado = [];

        foreach (array_values($pagos) as $i => $p) {
            $metodo = MetodoPago::porCodigo($p['mpa_codigo']);

            $resultado[] = [
                'mpa_codigo'       => $metodo->mpa_codigo,
                'mpa_descri'       => $metodo->mpa_descri,
                'monto'            => (int) $p['monto'],
                'comprobante_temp' => $this->resolverComprobante($p, $metodo, $anteriores[$i] ?? null, $i),
            ];
        }

        return [
            'modo'  => PedidoTemporal::MODO_DIVIDIDO,
            'pagos' => $resultado,
        ];
    }

    /**
     * Reglas 5 y 9 del documento: el total asignado no puede superar el
     * total del pedido y el pendiente debe quedar en 0.
     *
     * Esta validacion no puede vivir en el FormRequest porque depende del
     * estado del sistema (carrito + costo de envio), no de los datos
     * enviados. El monto nunca se toma del cliente: se recalcula aca.
     */
    private function verificarMontos(array $pagos, int $total): void
    {
        $asignado = array_sum(array_map(fn ($p) => (int) ($p['monto'] ?? 0), $pagos));

        if ($asignado > $total) {
            throw ValidationException::withMessages([
                'pagos' => 'El total asignado (Gs. ' . $this->gs($asignado) . ') supera el total del pedido (Gs. '
                    . $this->gs($total) . ').',
            ]);
        }

        if ($asignado < $total) {
            throw ValidationException::withMessages([
                'pagos' => 'Todavía queda un pendiente de Gs. ' . $this->gs($total - $asignado)
                    . '. Asigná el total del pedido para continuar.',
            ]);
        }
    }

    /**
     * conservar = el cliente volvio al Paso 2 y dejo intacto el comprobante
     * que ya habia subido para ese pago. El navegador no puede reenviar el
     * archivo, asi que se recupera por posicion desde el borrador anterior
     * y se verifica que siga existiendo en disco.
     */
    private function resolverComprobante(array $pago, MetodoPago $metodo, ?array $anterior, int $indice): ?string
    {
        // Regla 7: el efectivo contra entrega no lleva comprobante.
        if (!$metodo->mpa_requcom) {
            return null;
        }

        $archivo = $pago['comprobante'] ?? null;

        if ($archivo instanceof UploadedFile) {
            return $archivo->store(self::CARPETA_TEMP, 'public');
        }

        $conservar  = filter_var($pago['conservar'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rutaPrevia = $anterior['comprobante_temp'] ?? null;

        if ($conservar && $this->existe($rutaPrevia)) {
            return $rutaPrevia;
        }

        throw ValidationException::withMessages([
            "pagos.$indice.comprobante" => 'Adjuntá el comprobante del Pago ' . ($indice + 1) . '.',
        ]);
    }

    // ---------- Helpers ----------

    /**
     * Borra del disco los comprobantes del borrador anterior que el nuevo
     * ya no referencia (pago eliminado, archivo reemplazado, cambio a un
     * metodo que no lleva comprobante).
     */
    private function eliminarComprobantesHuerfanos(?array $anterior, array $nuevo): void
    {
        $antes   = $this->rutasDe($anterior);
        $despues = $this->rutasDe($nuevo);

        foreach (array_diff($antes, $despues) as $ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }

    private function rutasDe(?array $pago): array
    {
        return array_values(array_filter(
            array_map(fn ($p) => $p['comprobante_temp'] ?? null, $pago['pagos'] ?? [])
        ));
    }

    private function existe(?string $ruta): bool
    {
        return $ruta && Storage::disk('public')->exists($ruta);
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }

    private function verificarFacturacionPrevia(Usuario $usuario): void
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();

        if (!$temporal || empty($temporal->ptm_factura)) {
            throw ValidationException::withMessages([
                'mpa_codigo' => 'Completá primero los datos de facturación.',
            ]);
        }
    }
}