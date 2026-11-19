<?php

namespace App\Services\Venta;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Usuario;
use App\Models\Venta\CtaCobrar;
use App\Models\Venta\CuotaCheque;
use App\Models\Venta\CuotaComprobante;
use App\Models\Venta\CuotaPago;
use App\Models\Venta\CuotaPagoDetalle;
use App\Models\Venta\CuotaPagoMedio;
use App\Services\Pedido\CheckoutPagoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * El cliente declara el pago de una o varias cuotas desde "Mis Cuotas".
 *
 * Declarar NO salda nada: deja la declaracion a la espera. La cuota pasa
 * a COBRADA cuando el admin registra el cobro desde Cuentas por Cobrar.
 *
 * Dos modos, los mismos del Paso 3 del checkout:
 *   unico    -> un metodo cubre el total de las cuotas elegidas
 *   dividido -> varios metodos, cuya suma tiene que dar ese total
 *
 * El efectivo y el cheque no llevan comprobante adjunto: el primero se
 * abona en la sucursal y el segundo tiene su propio papel, cuyos datos
 * declarados cumplen ese papel (cuota_cheque).
 */
class CuotaPagoService
{
    public const MODO_UNICO    = 'unico';
    public const MODO_DIVIDIDO = 'dividido';

    private const DISCO      = 'local';
    private const DIRECTORIO = 'comprobantes-cuotas';

    /** Lo que el cliente lee al elegir Efectivo en una cuota. */
    public const MENSAJE_EFECTIVO =
        'El monto lo abonás al acercarte a la sucursal y ahí te entregamos el recibo de tu cuota.';

    public function __construct(private CheckoutPagoService $configuracion)
    {
    }

    // ================================================================
    //  Lectura
    // ================================================================

    /**
     * Formas de pago para la pantalla de pago de cuotas.
     *
     * A diferencia del pedido, el cheque no depende de la modalidad de
     * entrega: el pedido ya se entrego. Solo depende de que el admin haya
     * dejado habilitado algun tipo de cheque.
     */
    public function datosDePago(): array
    {
        $datos = $this->configuracion->datosIniciales();

        $habilitado = $datos['datos_cheque']['tipos'] !== [];

        $datos['cheque_habilitado'] = $habilitado;
        $datos['cheque_motivo']     = $habilitado
            ? null
            : 'la tienda no está aceptando cheques en este momento.';

        if (!$habilitado) {
            $datos['metodos'] = $datos['metodos']
                ->reject(fn ($m) => $m->mpa_codigo === MetodoPago::CHEQUE)
                ->values();
        }

        // El texto de efectivo de la tienda habla de "contra entrega", que
        // no aplica a una cuota: la deuda se abona en la sucursal.
        $datos['mensaje_efectivo'] = self::MENSAJE_EFECTIVO;

        return $datos;
    }

    /**
     * Cuotas elegibles para un cobro, validadas como conjunto.
     *
     * Es el guard de la pantalla de pago Y del POST: las dos entran por
     * aca, asi que una URL armada a mano no puede llegar a pagar cuotas
     * salteadas, ajenas o ya cobradas.
     *
     * @param  int[]  $cuotaIds
     */
    public function cuotasPagables(array $cuotaIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $cuotaIds)));

        if (empty($ids)) {
            throw ValidationException::withMessages([
                'cuotas' => 'Seleccioná al menos una cuota para pagar.',
            ]);
        }

        $cuotas = CtaCobrar::whereIn('cco_id', $ids)
            ->orderBy('cco_nrocuo')
            ->get();

        if ($cuotas->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'cuotas' => 'Alguna de las cuotas seleccionadas no existe.',
            ]);
        }

        if ($cuotas->pluck('cco_venid')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'cuotas' => 'Sólo podés pagar juntas cuotas de una misma factura.',
            ]);
        }

        foreach ($cuotas as $cuota) {
            if ($cuota->cco_estado !== CtaCobrar::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'cuotas' => "La cuota N° {$cuota->cco_nrocuo} ya no está pendiente de pago.",
                ]);
            }

            // Una declaracion sin resolver bloquea otra: si no, el cliente
            // podria mandar cinco comprobantes del mismo pago.
            $enCurso = $cuota->pagos()
                ->whereHas('estadoPago', fn ($q) => $q->whereIn('epa_codigo', [
                    EstadoPago::PENDIENTE_PAGO,
                    EstadoPago::PAGO_VERIFICAR,
                    EstadoPago::CONFIRMADO,
                ]))
                ->exists();

            if ($enCurso) {
                throw ValidationException::withMessages([
                    'cuotas' => "Ya informaste un pago para la cuota N° {$cuota->cco_nrocuo}. Esperá a que lo revisemos.",
                ]);
            }
        }

        $this->validarConsecutivas($cuotas);

        return $cuotas;
    }

    // ================================================================
    //  Escritura
    // ================================================================

    /**
     * Registra el cobro declarado por el cliente.
     *
     * @param  int[]  $cuotaIds
     * @param  array  $datos  $request->validated()
     */
    public function declarar(array $cuotaIds, array $datos): CuotaPago
    {
        $esDividido = ($datos['modo'] ?? self::MODO_UNICO) === self::MODO_DIVIDIDO;

        // Se guardan fuera de la transaccion: Storage no participa del
        // rollback. Si algo falla, se borran a mano en el catch.
        $archivos = [];

        try {
            return DB::transaction(function () use ($cuotaIds, $datos, $esDividido, &$archivos) {
                // lockForUpdate dentro de la transaccion: dos envios
                // simultaneos del mismo formulario se serializan y el
                // segundo encuentra las cuotas ya declaradas.
                CtaCobrar::whereIn('cco_id', $cuotaIds)->lockForUpdate()->get();

                $cuotas = $this->cuotasPagables($cuotaIds);
                $total  = (int) round((float) $cuotas->sum('cco_monto'));

                $medios = $esDividido
                    ? $this->armarDividido($datos['pagos'] ?? [], $total, $archivos)
                    : $this->armarUnico($datos, $total, $archivos);

                $this->verificarCheques($medios);

                return $this->persistir($cuotas, $medios, $total, $esDividido);
            });
        } catch (\Throwable $e) {
            foreach ($archivos as $ruta) {
                Storage::disk(self::DISCO)->delete($ruta);
            }

            throw $e;
        }
    }

    /**
     * Reemplaza el comprobante de un medio rechazado. Se actualiza la fila
     * existente (uq_ccm_medio) y el medio vuelve a quedar a la espera.
     */
    public function reemplazarComprobante(CuotaPagoMedio $medio, UploadedFile $archivo): CuotaPagoMedio
    {
        $medio->loadMissing('estadoPago', 'metodoPago', 'comprobante', 'pago');

        if (!$medio->esReemplazable()) {
            throw ValidationException::withMessages([
                'comprobante' => 'Sólo se puede reemplazar el comprobante de un pago rechazado.',
            ]);
        }

        return DB::transaction(function () use ($medio, $archivo) {
            $this->guardarComprobante($medio, $archivo);

            $verificar = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id;

            $medio->update([
                'cpm_epaid'   => $verificar,
                'cpm_mrpid'   => null,
                'cpm_observ'  => null,
                'cpm_usuver'  => null,
                'cpm_fechver' => null,
            ]);

            $this->sincronizarCabecera($medio->pago);

            return $medio->fresh(['metodoPago', 'estadoPago', 'comprobante']);
        });
    }

    /** Rechazo del comprobante de un medio por parte del admin. */
    public function rechazar(CuotaPagoMedio $medio, int $motivoId, ?string $detalle, Usuario $usuario): CuotaPagoMedio
    {
        $medio->loadMissing('estadoPago', 'pago');

        if ($medio->estadoPago?->epa_codigo !== EstadoPago::PAGO_VERIFICAR) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago no está a la espera de verificación.',
            ]);
        }

        return DB::transaction(function () use ($medio, $motivoId, $detalle, $usuario) {
            $medio->update([
                'cpm_epaid'   => EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
                'cpm_mrpid'   => $motivoId,
                'cpm_observ'  => $detalle,
                'cpm_usuver'  => $usuario->usu_id,
                'cpm_fechver' => now(),
            ]);

            $this->sincronizarCabecera($medio->pago);

            return $medio->fresh(['metodoPago', 'estadoPago', 'motivoRechazo']);
        });
    }

    /**
     * Marca la declaracion completa como confirmada. La invoca
     * CtaCobrarService cuando el admin registra el cobro: el cobro es el
     * hecho, esto solo cierra la declaracion que lo origino.
     */
    public function confirmar(CuotaPago $pago, Usuario $usuario): void
    {
        $confirmado = EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id;

        $pago->loadMissing('medios.comprobante');

        foreach ($pago->medios as $medio) {
            $medio->update([
                'cpm_epaid'   => $confirmado,
                'cpm_usuver'  => $usuario->usu_id,
                'cpm_fechver' => now(),
            ]);

            $medio->comprobante?->update(['ccm_verifi' => true]);
        }

        $pago->update([
            'cpa_epaid'   => $confirmado,
            'cpa_usuver'  => $usuario->usu_id,
            'cpa_fechver' => now(),
        ]);
    }

    // ================================================================
    //  Armado de los medios
    // ================================================================

    private function armarUnico(array $datos, int $total, array &$archivos): array
    {
        $metodo = MetodoPago::porCodigo($datos['mpa_codigo']);

        return [[
            'metodo'      => $metodo,
            'monto'       => $total,
            'comprobante' => $metodo->mpa_requcom
                ? $this->guardarArchivo($datos['comprobante'] ?? null, $archivos)
                : null,
            'cheque'      => $this->armarCheque($metodo, $datos['cheque'] ?? null),
        ]];
    }

    private function armarDividido(array $pagos, int $total, array &$archivos): array
    {
        $this->verificarMontos($pagos, $total);

        $medios = [];

        foreach (array_values($pagos) as $p) {
            $metodo = MetodoPago::porCodigo($p['mpa_codigo']);

            $medios[] = [
                'metodo'      => $metodo,
                'monto'       => (int) $p['monto'],
                'comprobante' => $metodo->mpa_requcom
                    ? $this->guardarArchivo($p['comprobante'] ?? null, $archivos)
                    : null,
                'cheque'      => $this->armarCheque($metodo, $p['cheque'] ?? null),
            ];
        }

        return $medios;
    }

    /**
     * Normaliza el cheque declarado. La fecha de cobro solo se conserva en
     * el diferido: en un cheque a la vista no existe, y guardarla haria
     * que la sucursal esperara un deposito que nadie definio.
     */
    private function armarCheque(MetodoPago $metodo, ?array $cheque): ?array
    {
        if ($metodo->mpa_codigo !== MetodoPago::CHEQUE || !$cheque) {
            return null;
        }

        $tipo = $cheque['tipo'];

        return [
            'tipo'        => $tipo,
            'banco'       => trim((string) $cheque['banco']),
            'nrodoc'      => trim((string) $cheque['nrodoc']),
            'nombre'      => trim((string) $cheque['nombre']),
            'nro'         => trim((string) ($cheque['nro'] ?? '')),
            'fecha_cobro' => $tipo === PagoCheque::TIPO_DIFERIDO
                ? ($cheque['fecha_cobro'] ?? null)
                : null,
        ];
    }

    /**
     * El cheque solo se acepta si el admin dejo habilitado algun tipo. Se
     * verifica en el backend y no solo como aviso en pantalla: un POST
     * armado a mano generaria un cobro que la tienda no quiere procesar.
     */
    private function verificarCheques(array $medios): void
    {
        $hayCheque = collect($medios)
            ->contains(fn ($m) => $m['metodo']->mpa_codigo === MetodoPago::CHEQUE);

        if ($hayCheque && ConfiguracionTienda::actual()->tiposChequeHabilitados() === []) {
            throw ValidationException::withMessages([
                'mpa_codigo' => 'La tienda no está aceptando cheques en este momento.',
            ]);
        }
    }

    /**
     * La suma de los medios tiene que cubrir EXACTAMENTE el total de las
     * cuotas elegidas. El monto nunca se toma como valido por venir del
     * cliente: se compara contra cco_monto, que lo escribio el backend.
     */
    private function verificarMontos(array $pagos, int $total): void
    {
        $asignado = array_sum(array_map(fn ($p) => (int) ($p['monto'] ?? 0), $pagos));

        if ($asignado > $total) {
            throw ValidationException::withMessages([
                'pagos' => 'El total asignado (Gs. ' . $this->gs($asignado)
                    . ') supera el total de las cuotas (Gs. ' . $this->gs($total) . ').',
            ]);
        }

        if ($asignado < $total) {
            throw ValidationException::withMessages([
                'pagos' => 'Todavía queda un pendiente de Gs. ' . $this->gs($total - $asignado)
                    . '. Asigná el total de las cuotas para continuar.',
            ]);
        }
    }

    // ================================================================
    //  Persistencia
    // ================================================================

    private function persistir(Collection $cuotas, array $medios, int $total, bool $esDividido): CuotaPago
    {
        $estadoCabecera = $this->estadoSegunMedios($medios);

        $pago = CuotaPago::create([
            // Con pago dividido la pregunta "cual metodo" no tiene
            // respuesta: queda en null y manda cuota_pago_medio.
            'cpa_mpaid' => $esDividido ? null : $medios[0]['metodo']->mpa_id,
            'cpa_monto' => $total,
            'cpa_epaid' => $estadoCabecera,
        ]);

        foreach ($cuotas as $cuota) {
            CuotaPagoDetalle::create([
                'cpd_cpaid' => $pago->cpa_id,
                'cpd_ccoid' => $cuota->cco_id,
                'cpd_monto' => $cuota->cco_monto,
            ]);
        }

        $verificar = EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id;
        $pendiente = EstadoPago::porCodigo(EstadoPago::PENDIENTE_PAGO)->epa_id;

        foreach ($medios as $i => $m) {
            // El efectivo y el cheque no traen evidencia que verificar: el
            // primero se abona en la sucursal y el segundo recien existe
            // cuando el papel llega. Nacen Pendientes de pago.
            $medio = CuotaPagoMedio::create([
                'cpm_cpaid' => $pago->cpa_id,
                'cpm_orden' => $i + 1,
                'cpm_mpaid' => $m['metodo']->mpa_id,
                'cpm_monto' => $m['monto'],
                'cpm_epaid' => $m['metodo']->mpa_requcom ? $verificar : $pendiente,
            ]);

            if ($m['comprobante']) {
                CuotaComprobante::create([
                    'ccm_cpmid'  => $medio->cpm_id,
                    'ccm_archiv' => $m['comprobante'],
                    'ccm_verifi' => false,
                ]);
            }

            if ($m['cheque']) {
                CuotaCheque::create([
                    'cch_cpmid'  => $medio->cpm_id,
                    'cch_tipo'   => $m['cheque']['tipo'],
                    'cch_banco'  => $m['cheque']['banco'],
                    'cch_nrodoc' => $m['cheque']['nrodoc'],
                    'cch_nombre' => $m['cheque']['nombre'],
                    'cch_nro'    => $m['cheque']['nro'] ?: null,
                    'cch_fecobr' => $m['cheque']['fecha_cobro'],
                    'cch_estado' => PagoCheque::EN_ESPERA,

                    // El reloj arranca al declarar: es el tiempo que tiene
                    // el cliente para acercar el papel a la sucursal.
                    'cch_fevcto' => CuotaCheque::calcularVencimiento(),
                ]);
            }
        }

        return $pago->load([
            'detalle.cuota', 'medios.metodoPago', 'medios.estadoPago',
            'medios.comprobante', 'medios.cheque',
        ]);
    }

    /**
     * Estado general de la declaracion: si algun medio lleva comprobante,
     * hay algo que verificar; si no (solo efectivo y/o cheque), queda
     * Pendiente de pago hasta que el cliente se acerque a la sucursal.
     */
    private function estadoSegunMedios(array $medios): int
    {
        $hayComprobante = collect($medios)->contains(fn ($m) => (bool) $m['metodo']->mpa_requcom);

        return EstadoPago::porCodigo(
            $hayComprobante ? EstadoPago::PAGO_VERIFICAR : EstadoPago::PENDIENTE_PAGO
        )->epa_id;
    }

    /**
     * La cabecera refleja a sus medios: queda RECHAZADA si alguno lo esta
     * (el cliente tiene algo que corregir) y vuelve a la espera cuando se
     * reemplaza el comprobante.
     */
    private function sincronizarCabecera(CuotaPago $pago): void
    {
        $codigos = $pago->medios()->with('estadoPago')->get()
            ->map(fn (CuotaPagoMedio $m) => $m->estadoPago?->epa_codigo);

        $estado = match (true) {
            $codigos->contains(EstadoPago::RECHAZADO)      => EstadoPago::RECHAZADO,
            $codigos->contains(EstadoPago::PAGO_VERIFICAR) => EstadoPago::PAGO_VERIFICAR,
            $codigos->every(fn ($c) => $c === EstadoPago::CONFIRMADO) => EstadoPago::CONFIRMADO,
            default => EstadoPago::PENDIENTE_PAGO,
        };

        $pago->update(['cpa_epaid' => EstadoPago::porCodigo($estado)->epa_id]);
    }

    // ================================================================
    //  Auxiliares
    // ================================================================

    /**
     * Las cuotas elegidas tienen que ser las N mas antiguas pendientes de
     * su factura. Pagar la 4 dejando viva la 3 haria que la mora y los
     * vencimientos dejen de significar algo.
     */
    private function validarConsecutivas(Collection $cuotas): void
    {
        $pendientes = CtaCobrar::where('cco_venid', $cuotas->first()->cco_venid)
            ->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)
            ->orderBy('cco_nrocuo')
            ->pluck('cco_nrocuo', 'cco_id');

        $esperadas = $pendientes->take($cuotas->count())->keys()->sort()->values();
        $elegidas  = $cuotas->pluck('cco_id')->sort()->values();

        if ($esperadas->toArray() !== $elegidas->toArray()) {
            throw ValidationException::withMessages([
                'cuotas' => 'Las cuotas se pagan en orden, empezando por la más antigua.',
            ]);
        }
    }

    private function guardarArchivo(?UploadedFile $archivo, array &$archivos): ?string
    {
        if (!$archivo instanceof UploadedFile) {
            return null;
        }

        $ruta = $archivo->store(self::DIRECTORIO, self::DISCO);
        $archivos[] = $ruta;

        return $ruta;
    }

    private function guardarComprobante(CuotaPagoMedio $medio, UploadedFile $archivo): void
    {
        $ruta     = $archivo->store(self::DIRECTORIO, self::DISCO);
        $anterior = $medio->comprobante;

        if ($anterior) {
            // El archivo viejo se borra: un comprobante rechazado ya no
            // tiene valor probatorio y ocupa disco.
            Storage::disk(self::DISCO)->delete($anterior->ccm_archiv);

            $anterior->update([
                'ccm_archiv' => $ruta,
                'ccm_verifi' => false,
                'ccm_subido' => now(),
            ]);

            return;
        }

        CuotaComprobante::create([
            'ccm_cpmid'  => $medio->cpm_id,
            'ccm_archiv' => $ruta,
            'ccm_verifi' => false,
        ]);
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}