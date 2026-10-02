<?php

namespace App\Services\Auth;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Google\Auth\AccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleAuthService
{
    /**
     * Verifica el ID token emitido por Google: firma, audiencia (client_id),
     * emisor y expiracion. Devuelve los datos de la cuenta o null si no es valido.
     */
    public function verificarToken(string $credential): ?array
    {
        try {
            // Verifica firma (claves públicas de Google), emisor, expiración y audiencia
            $payload = (new AccessToken())->verify($credential, [
                'audience' => config('services.google.client_id'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Google verify error: ' . $e->getMessage());
            return null;
        }

        if (! $payload) {
            \Log::warning('Google verify: token rechazado. client_id usado: ' . config('services.google.client_id'));
        }

        // Solo se aceptan correos verificados por Google
        if (! $payload || empty($payload['email_verified'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Orden de busqueda:
     * 1) Usuario ya vinculado por usu_gooid.
     * 2) Usuario Cliente existente con el mismo email -> se vincula.
     * 3) Ninguno -> se registra un cliente nuevo.
     */
    public function obtenerOCrearCliente(array $payload): Usuario
    {
        $usuario = Usuario::where('usu_gooid', $payload['sub'])->first();

        if ($usuario) {
            return $usuario;
        }

        $usuario = Usuario::where('usu_login', $payload['email'])->first();

        if ($usuario) {
            // No se vinculan cuentas de Administrador/Vendedor desde la tienda;
            // el controlador las rechaza por rol.
            if ($usuario->rol?->rol_descri !== 'Cliente') {
                return $usuario;
            }

            $datos = ['usu_gooid' => $payload['sub']];

            // Google ya verifico el correo: si estaba pendiente, se activa
            if ($usuario->usu_estado === Usuario::ESTADO_INACTIVO) {
                $datos['usu_estado'] = Usuario::ESTADO_ACTIVO;
            }

            $usuario->update($datos);

            return $usuario;
        }

        return $this->registrarCliente($payload);
    }

    /**
     * Misma estructura que AuthController::register (persona + cliente +
     * contacto EMAIL + usuario), pero sin cedula y con el usuario ya ACTIVO.
     */
    private function registrarCliente(array $payload): Usuario
    {
        $rolCliente = Rol::where('rol_descri', 'Cliente')->first();
        $tipoDocumento = TipoDocumento::where('tpd_codigo', 'CI')->first() ?? TipoDocumento::first();
        $tipoCliente = TipoCliente::first();

        if (! $rolCliente || ! $tipoDocumento || ! $tipoCliente) {
            throw new RuntimeException('Faltan catalogos base (rol Cliente, tipo_documento o tipo_cliente).');
        }

        [$nombre1, $nombre2] = $this->dividir($payload['given_name'] ?? $payload['name'] ?? 'Cliente', 45);
        [$apellido1, $apellido2] = $this->dividir($payload['family_name'] ?? '', 50);

        return DB::transaction(function () use ($payload, $rolCliente, $tipoDocumento, $tipoCliente, $nombre1, $nombre2, $apellido1, $apellido2) {
            $persona = Persona::create([
                'per_tpdid' => $tipoDocumento->tpd_id,
                'per_nombr1' => $nombre1,
                'per_nombr2' => $nombre2,
                'per_apell1' => $apellido1,
                'per_apell2' => $apellido2,
                'per_cedula' => null, // se completa en el perfil
                'per_estado' => 1,
            ]);

            Cliente::create([
                'cli_perid' => $persona->per_id,
                'cli_tpcid' => $tipoCliente->tpc_id,
                'cli_estado' => 1,
            ]);

            $persona->contactos()->create([
                'con_tipo' => 'EMAIL',
                'con_valor' => $payload['email'],
                'con_estado' => 1,
            ]);

            return Usuario::create([
                'usu_perid' => $persona->per_id,
                'usu_rolid' => $rolCliente->rol_id,
                'usu_login' => $payload['email'],
                // Contrasena aleatoria: nunca se usa. Si luego quiere entrar con
                // email/contrasena, la define desde "Olvide mi contrasena".
                'usu_passwd' => bcrypt(Str::random(40)),
                'usu_estado' => Usuario::ESTADO_ACTIVO,
                'usu_gooid' => $payload['sub'],
            ]);
        });
    }

    /** Divide "Juan Carlos" en ["Juan", "Carlos"] respetando el largo de la columna. */
    private function dividir(string $texto, int $max): array
    {
        $partes = preg_split('/\s+/', trim($texto), 2);

        return [
            Str::limit($partes[0] ?? '', $max, ''),
            isset($partes[1]) ? Str::limit($partes[1], $max, '') : null,
        ];
    }
}