<?php

namespace App\Services\Cliente;

use App\Mail\verifiedMail;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\TokenVerificacion;
use App\Models\Usuario;
use App\Models\VinculacionCuenta;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vinculación PRESENCIAL: el cliente de mostrador (persona + cliente, sin usuario) recibe un código
 * en la constancia y con él crea su cuenta web sobre la MISMA persona, aunque no haya correo
 * coincidente. Complementa a VinculacionCuentaService (que vincula automáticamente por correo).
 *
 * Seguridad: la cédula sola no basta (no es secreta); hace falta el código, que solo tiene quien
 * estuvo en el mostrador. Todas las fallas devuelven el mismo mensaje (no revela qué cédulas existen).
 */
class VinculacionPresencialService
{
    // Sin I, L, O, 0 ni 1 para evitar confusiones al leer el papel.
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LARGO    = 8;

    private const MENSAJE_GENERICO = 'Cédula o código incorrectos, o el código ya no está vigente. '
        . 'Si el problema continúa, acercate a la sucursal.';

    public function __construct(private ClienteRapidoService $clientes) {}

    // ---------- Lado mostrador ----------

    /**
     * Código vigente de la persona (lo crea si no existe). Idempotente: reimprimir la constancia
     * muestra el mismo código. Devuelve null si la persona ya tiene cuenta web o no es cliente activo.
     *
     * @return array{codigo:string, vence:\Illuminate\Support\Carbon, enlace:string, pagina:string}|null
     */
    public function codigoVigente(Persona $persona): ?array
    {
        if (!$this->esVinculable($persona)) {
            return null;
        }

        return DB::transaction(function () use ($persona) {
            Persona::whereKey($persona->per_id)->lockForUpdate()->first();

            return $this->presentar($this->vigenteDe($persona->per_id) ?? $this->crear($persona));
        });
    }

    /** Anula el código anterior y emite uno nuevo (por pérdida o sospecha de filtración). */
    public function reemitir(Persona $persona): ?array
    {
        if (!$this->esVinculable($persona)) {
            return null;
        }

        return DB::transaction(function () use ($persona) {
            Persona::whereKey($persona->per_id)->lockForUpdate()->first();

            return $this->presentar($this->crear($persona));
        });
    }

    /** PNG en base64 (data URI) para incrustar en el PDF, o null si falta el paquete o la extensión GD. */
    public function qrDataUri(string $enlace): ?string
    {
        try {
            return (new PngWriter())->write(new QrCode(data: $enlace, size: 220, margin: 6))->getDataUri();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ---------- Lado tienda ----------

    /** Crea el usuario sobre la persona vinculada. Lanza ValidationException con mensaje genérico si algo falla. */
    public function vincular(string $tpdCodigo, string $documento, string $codigo, string $email, string $password): Usuario
    {
        $email   = mb_strtolower(trim($email));
        $tipoDoc = TipoDocumento::where('tpd_codigo', $tpdCodigo)->where('tpd_estado', 1)->first();

        $persona = $tipoDoc
            ? $this->clientes->personaConDocumento(
                $tipoDoc->tpd_id,
                $this->clientes->normalizarDocumento($tipoDoc->tpd_codigo, $documento)
            )
            : null;

        $vinculo = ($persona && $this->esVinculable($persona)) ? $this->vigenteDe($persona->per_id) : null;

        if (!$vinculo) {
            $this->rechazar();
        }

        if (!hash_equals((string) $vinculo->vin_codigo, $this->normalizarCodigo($codigo))) {
            $this->registrarIntentoFallido($vinculo);
            $this->rechazar();
        }

        // Recién con el código correcto se revela si el correo ya está en uso.
        if (!$this->correoDisponible($email, $persona->per_id)) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo ya está en uso. Iniciá sesión o usá otro correo.',
            ]);
        }

        return DB::transaction(function () use ($persona, $vinculo, $email, $password) {
            // Consumo atómico: si dos pedidos llegan a la vez, solo uno lo logra.
            $consumido = VinculacionCuenta::whereKey($vinculo->vin_id)
                ->where('vin_estado', VinculacionCuenta::VIGENTE)
                ->update(['vin_estado' => VinculacionCuenta::USADO, 'vin_fecuso' => now()]);

            if ($consumido !== 1) {
                $this->rechazar();
            }

            $rolCliente = Rol::where('rol_descri', 'Cliente')->firstOrFail();

            $persona->contactos()->updateOrCreate(
                ['con_tipo' => 'EMAIL', 'con_valor' => $email],
                ['con_estado' => 1]
            );

            $usuario = Usuario::create([
                'usu_perid'  => $persona->per_id,
                'usu_rolid'  => $rolCliente->rol_id,
                'usu_login'  => $email,
                'usu_passwd' => bcrypt($password),
                'usu_estado' => Usuario::ESTADO_INACTIVO,   // se activa al verificar el correo
            ]);

            // Mismo mecanismo que AuthController::register (token de 48 h, solo se guarda el hash).
            $token = Str::random(40);
            TokenVerificacion::create([
                'tkv_usuid'  => $usuario->usu_id,
                'tkv_tipo'   => TokenVerificacion::TIPO_VERIFICACION_EMAIL,
                'tkv_tokhas' => hash('sha256', $token),
                'tkv_fecexp' => now()->addMinutes(60 * 48),
                'tkv_usado'  => 0,
            ]);

            // Dentro de la transacción: si el envío falla, el código NO queda consumido.
            Mail::to($usuario->usu_login)->send(new verifiedMail($usuario, $token));

            return $usuario;
        });
    }

    // ---------- Auxiliares ----------

    private function esVinculable(Persona $persona): bool
    {
        return $persona->cliente()->where('cli_estado', 1)->exists()
            && !$persona->usuario()->exists();
    }

    private function vigenteDe(int $perId): ?VinculacionCuenta
    {
        return VinculacionCuenta::where('vin_perid', $perId)
            ->where('vin_estado', VinculacionCuenta::VIGENTE)
            ->where('vin_fecexp', '>', now())
            ->latest('vin_id')
            ->first();
    }

    private function crear(Persona $persona): VinculacionCuenta
    {
        VinculacionCuenta::where('vin_perid', $persona->per_id)
            ->where('vin_estado', VinculacionCuenta::VIGENTE)
            ->update(['vin_estado' => VinculacionCuenta::ANULADO]);

        return VinculacionCuenta::create([
            'vin_perid'    => $persona->per_id,
            'vin_codigo'   => $this->generarCodigo(),
            'vin_fecexp'   => now()->addDays((int) config('servicio.vinculacion_dias')),
            'vin_intentos' => 0,
            'vin_estado'   => VinculacionCuenta::VIGENTE,
        ]);
    }

    private function generarCodigo(): string
    {
        $codigo = '';
        for ($i = 0; $i < self::LARGO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $codigo;
    }

    private function normalizarCodigo(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }

    private function presentar(VinculacionCuenta $v): array
    {
        $codigo = substr($v->vin_codigo, 0, 4) . '-' . substr($v->vin_codigo, 4);
        $pagina = rtrim(config('servicio.url_tienda'), '/') . '/vincular-cuenta';

        return [
            'codigo' => $codigo,
            'vence'  => $v->vin_fecexp,
            'pagina' => $pagina,
            'enlace' => $pagina . '?codigo=' . rawurlencode($codigo),
        ];
    }

    private function registrarIntentoFallido(VinculacionCuenta $v): void
    {
        VinculacionCuenta::whereKey($v->vin_id)->increment('vin_intentos');

        VinculacionCuenta::whereKey($v->vin_id)
            ->where('vin_intentos', '>=', (int) config('servicio.vinculacion_max_intentos'))
            ->update(['vin_estado' => VinculacionCuenta::ANULADO]);
    }

    /** El correo no puede ser login de otro usuario ni contacto de otra persona. */
    private function correoDisponible(string $correo, int $perId): bool
    {
        $enUsuario = Usuario::whereRaw('LOWER(usu_login) = ?', [$correo])->exists();

        $enContacto = DB::table('contacto')
            ->where('con_tipo', 'EMAIL')
            ->whereRaw('LOWER(con_valor) = ?', [$correo])
            ->where('con_perid', '<>', $perId)
            ->exists();

        return !$enUsuario && !$enContacto;
    }

    private function rechazar(): never
    {
        throw ValidationException::withMessages(['codigo' => [self::MENSAJE_GENERICO]]);
    }
}