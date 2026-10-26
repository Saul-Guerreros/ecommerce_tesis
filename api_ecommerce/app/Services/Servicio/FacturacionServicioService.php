<?php

namespace App\Services\Servicio;

use App\Exceptions\EmisionFacturaException;
use App\Models\Persona;
use App\Models\Servicio\ServicioFacturacion;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Ruc\DigitoVerificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Datos fiscales (CI o RUC) con los que se factura una solicitud de servicio.
 * Equivalente a CheckoutFacturacionService del pedido, que NO se toca.
 *
 * Quién los carga y cuándo:
 *   - WEB: el cliente, junto con el pago del servicio (pantalla de pago).
 *   - MOSTRADOR: el cajero, junto con "Registrar cobro".
 * Se pueden cambiar mientras la solicitud no tenga factura vigente; después
 * no, porque la factura ya guardó su propia copia en venta_cab.
 *
 * Si nunca se cargan, adquirente() devuelve la CI de la persona: la emisión
 * no se bloquea por falta de datos fiscales (el mostrador ya exige per_cedula).
 *
 * Se guardan con su propio endpoint (PUT .../facturacion), que el front
 * llama justo ANTES de registrar el pago, igual que el checkout separa el
 * paso de facturación del de pago. Así no se tocan los payloads del pago
 * web ni del cobro de mostrador. actualizar() bloquea la fila de la
 * solicitud: si en ese instante se está emitiendo la factura (que también
 * la bloquea), espera y después ve que ya no es editable.
 *
 * Los errores de validación usan las mismas claves que el formulario
 * (tipo_documento, dfi_id, nrodoc, raznom, telefono, correo).
 */
class FacturacionServicioService
{
    // ---------- Consulta (para armar la tarjeta CI / RUC en el front) ----------

    /**
     * @return array{titular: array, datos_fiscales: \Illuminate\Support\Collection, actual: ?array, editable: bool}
     */
    public function opciones(SolicitudServicio $s): array
    {
        $persona = $this->persona($s);
        $actual  = $s->facturacion;

        return [
            'titular' => [
                'tipo_documento' => ServicioFacturacion::TIPO_CI,
                'nrodoc'         => $this->cedula($persona),
                'raznom'         => $this->nombre($persona),
                // Por separado: el modal "Agregar RUC" de la tienda los usa tal cual.
                'nombre'         => $persona->per_nombr1,
                'apellido'       => $persona->per_apell1,
                'telefono'       => $this->telefonoDe($persona),
                'correo'         => $persona->usuario?->usu_login,
            ],
            'datos_fiscales' => $persona->datosFiscales()
                ->orderByDesc('dfi_principal')
                ->orderByDesc('dfi_id')
                ->get(['dfi_id', 'dfi_alias', 'dfi_numero', 'dfi_razon', 'dfi_telefono', 'dfi_correo', 'dfi_principal']),
            'actual'   => $actual ? [
                'tipo_documento' => $actual->sfa_tipdoc,
                'dfi_id'         => $actual->sfa_dfiid,
                'nrodoc'         => $actual->sfa_nrodoc,
                'raznom'         => $actual->sfa_raznom,
                'telefono'       => $actual->sfa_telef,
                'correo'         => $actual->sfa_correo,
                'recibe'         => $actual->sfa_recibe,
            ] : null,
            'editable' => $this->editable($s),
        ];
    }

    public function editable(SolicitudServicio $s): bool
    {
        return !$s->factura()->exists();
    }

    // ---------- Alta / cambio ----------

    /** Punto de entrada del endpoint: transacción + bloqueo de la solicitud. */
    public function actualizar(SolicitudServicio $s, array $d): ServicioFacturacion
    {
        return DB::transaction(function () use ($s, $d) {
            $s = SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->firstOrFail();

            return $this->guardar($s, $d);
        });
    }

    /**
     * @param array{tipo_documento: string, recibe: string, dfi_id?: ?int, nrodoc?: ?string,
     *              raznom?: ?string, telefono?: ?string, correo?: ?string} $d
     */
    public function guardar(SolicitudServicio $s, array $d): ServicioFacturacion
    {
        if (!$this->editable($s)) {
            throw ValidationException::withMessages([
                'tipo_documento' => 'Esta solicitud ya tiene factura emitida: sus datos fiscales no se pueden cambiar.',
            ]);
        }

        $persona = $this->persona($s);

        $datos = $d['tipo_documento'] === ServicioFacturacion::TIPO_RUC
            ? $this->datosRuc($persona, $d)
            : $this->datosCi($persona);

        // Lo que se escribe en el formulario manda sobre lo guardado en la persona.
        $datos['sfa_telef']  = $this->texto($d['telefono'] ?? null) ?? $datos['sfa_telef'];
        $datos['sfa_correo'] = $this->texto($d['correo'] ?? null) ?? $datos['sfa_correo'];
        $datos['sfa_recibe'] = $d['recibe'];

        $this->validarCanal($datos);

        return ServicioFacturacion::updateOrCreate(['sfa_sseid' => $s->sse_id], $datos);
    }

    // ---------- Para la emisión (Paso 3) ----------

    /**
     * Datos del adquirente con el formato de venta_cab (snapshot).
     * Sin elección guardada: la CI de la persona.
     *
     * @return array<string, ?string>
     *
     * @throws EmisionFacturaException si ni siquiera hay cédula
     */
    public function adquirente(SolicitudServicio $s): array
    {
        $fac = $s->facturacion;

        if (!$fac) {
            $persona = $this->persona($s);

            if ($this->cedula($persona) === '') {
                throw new EmisionFacturaException(
                    "El cliente de la solicitud {$s->sse_numero} no tiene cédula ni datos fiscales: no se puede emitir la factura."
                );
            }

            $fac = new ServicioFacturacion($this->datosCi($persona));
        }

        return [
            'ven_razonsocial' => mb_substr($fac->sfa_raznom, 0, 150),
            'ven_ruc'         => $fac->esRuc() ? $fac->sfa_nrodoc : null,
            'ven_ci'          => $fac->esRuc() ? null : $fac->sfa_nrodoc,
            // Informativo: la API no declara la dirección sin códigos DNIT (guía 6.4).
            'ven_direccion'   => null,
            'ven_telefono'    => $fac->sfa_telef ? mb_substr($fac->sfa_telef, 0, 30) : null,
            'ven_email'       => $fac->sfa_correo,
        ];
    }

    // ---------- Armado según el tipo ----------

    /** @return array<string, mixed> */
    private function datosCi(Persona $persona): array
    {
        $cedula = $this->cedula($persona);

        if ($cedula === '') {
            throw ValidationException::withMessages([
                'tipo_documento' => 'El cliente no tiene cédula cargada: complétela o facture con RUC.',
            ]);
        }

        return [
            'sfa_tipdoc' => ServicioFacturacion::TIPO_CI,
            'sfa_dfiid'  => null,
            'sfa_nrodoc' => $cedula,
            'sfa_raznom' => $this->nombre($persona),
            'sfa_telef'  => $this->telefonoDe($persona),
            'sfa_correo' => $persona->usuario?->usu_login,
        ];
    }

    /** @return array<string, mixed> */
    private function datosRuc(Persona $persona, array $d): array
    {
        // RUC guardado en "Mis datos fiscales": tiene que ser de esta persona.
        if (!empty($d['dfi_id'])) {
            $dato = $persona->datosFiscales()->where('dfi_id', $d['dfi_id'])->first();

            if (!$dato) {
                throw ValidationException::withMessages([
                    'dfi_id' => 'El RUC elegido no pertenece al cliente.',
                ]);
            }

            return [
                'sfa_tipdoc' => ServicioFacturacion::TIPO_RUC,
                'sfa_dfiid'  => $dato->dfi_id,
                'sfa_nrodoc' => $this->rucValido((string) $dato->dfi_numero, 'dfi_id'),
                'sfa_raznom' => mb_substr($dato->dfi_razon, 0, 150),
                'sfa_telef'  => $this->texto($dato->dfi_telefono),
                'sfa_correo' => $this->texto($dato->dfi_correo),
            ];
        }

        // RUC de uso único: no se guarda en dato_fiscal (igual que el checkout).
        $razon = $this->texto($d['raznom'] ?? null);

        if ($razon === null) {
            throw ValidationException::withMessages([
                'raznom' => 'Ingrese la razón social.',
            ]);
        }

        return [
            'sfa_tipdoc' => ServicioFacturacion::TIPO_RUC,
            'sfa_dfiid'  => null,
            'sfa_nrodoc' => $this->rucValido((string) ($d['nrodoc'] ?? ''), 'nrodoc'),
            'sfa_raznom' => mb_substr($razon, 0, 150),
            'sfa_telef'  => null,
            'sfa_correo' => null,
        ];
    }

    // ---------- Validaciones ----------

    /**
     * Mismo control que hace la API (guía 6.7): base, guion y dígito
     * verificador correcto. Se hace acá para que un RUC mal cargado nunca
     * llegue a una factura, que después no se puede corregir.
     */
    private function rucValido(string $ruc, string $campo): string
    {
        $ruc = preg_replace('/\s+/', '', $ruc);

        if (!preg_match('/^([0-9]{1,15})-([0-9])$/', $ruc, $m)) {
            throw ValidationException::withMessages([
                $campo => 'El RUC debe tener el formato 80069563-1 (número, guion y dígito verificador).',
            ]);
        }

        if (DigitoVerificador::digitoVerificador($m[1]) !== (int) $m[2]) {
            throw ValidationException::withMessages([
                $campo => "El dígito verificador del RUC {$ruc} no es correcto.",
            ]);
        }

        return $ruc;
    }

    /** Por dónde se manda la factura: el canal elegido necesita su dato. */
    private function validarCanal(array $datos): void
    {
        if ($datos['sfa_recibe'] === ServicioFacturacion::RECIBE_CORREO
            && filter_var($datos['sfa_correo'], FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'correo' => 'Ingrese un correo válido para recibir la factura.',
            ]);
        }

        if ($datos['sfa_recibe'] === ServicioFacturacion::RECIBE_WHATSAPP && $datos['sfa_telef'] === null) {
            throw ValidationException::withMessages([
                'telefono' => 'Ingrese un número de teléfono para recibir la factura por WhatsApp.',
            ]);
        }
    }

    // ---------- Utilidades ----------

    private function persona(SolicitudServicio $s): Persona
    {
        $s->loadMissing(['cliente.persona.usuario', 'cliente.persona.contactos', 'facturacion']);

        $persona = $s->cliente?->persona;

        if (!$persona) {
            throw ValidationException::withMessages([
                'tipo_documento' => 'La solicitud no tiene un cliente con datos personales.',
            ]);
        }

        return $persona;
    }

    /** Solo dígitos: la API pide la cédula sin puntos (guía 6.7) y ck_sfa_nrodoc lo exige. */
    private function cedula(Persona $persona): string
    {
        return preg_replace('/\D/', '', (string) $persona->per_cedula);
    }

    private function nombre(Persona $persona): string
    {
        $nombre = preg_replace('/\s+/', ' ', trim($persona->nombre_completo));

        return mb_substr($nombre, 0, 150);
    }

    /** Mismo criterio que el checkout: celular primero, después teléfono fijo. */
    private function telefonoDe(Persona $persona): ?string
    {
        return $persona->contactos
            ->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])
            ->where('con_estado', 1)
            ->sortBy(fn ($c) => $c->con_tipo === 'CELULAR' ? 0 : 1)
            ->first()?->con_valor;
    }

    /** null o texto vacío cuentan como "no mandado" (igual que la API, guía 6.7). */
    private function texto(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : null;

        return $valor === '' ? null : $valor;
    }
}
