<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPasswordMail;
use App\Mail\verifiedMail;
use App\Models\Cliente;
use App\Models\HistorialPassword;
use App\Models\IntentoSesion;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Sesion;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use App\Models\TokenVerificacion;
use App\Models\Usuario;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Validator;


class AuthController extends Controller
{
    /**
     * Cantidad de intentos fallidos consecutivos antes de bloquear la cuenta.
     * El bloqueo actual es indefinido (requiere desbloqueo manual/administrativo).
     *
     * Para agregar un bloqueo temporal en vez de indefinido, ver la nota al
     * final de la clase (metodo intentarLogin y el comentario final).
     */
    private const MAX_INTENTOS_FALLIDOS = 3;

    // Vigencia de los tokens de token_verificacion segun su tipo
    private const MINUTOS_EXPIRACION_VERIFICACION_EMAIL = 60 * 48; // 48 horas
    private const MINUTOS_EXPIRACION_RECUPERACION_PASSWORD = 15;    // 15 minutos

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'register', 'login', 'login_ecommerce', 'login_google',
            'verified_auth', 'verified_email', 'verified_code', 'new_password',
        ]]);
    }

    /**
     * Registro de un nuevo cliente (ecommerce).
     * Crea persona + cliente + usuario (rol Cliente, estado INACTIVO hasta
     * verificar el correo) + contacto de tipo email, y dispara el correo
     * de verificacion.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre1' => 'required|string|max:45',
            'nombre2' => 'nullable|string|max:45',
            'apellido1' => 'required|string|max:50',
            'apellido2' => 'nullable|string|max:50',
            'celular' => 'required|string|max:20',
            'email' => 'required|email|unique:usuario,usu_login',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $rolCliente = Rol::where('rol_descri', 'Cliente')->first();
        $tipoDocumento = TipoDocumento::where('tpd_codigo', 'CI')->first() ?? TipoDocumento::first();
        $tipoCliente = TipoCliente::first();

        if (! $rolCliente || ! $tipoDocumento || ! $tipoCliente) {
            return response()->json([
                'message' => 'Faltan catalogos base (rol Cliente, tipo_documento o tipo_cliente). Ejecute los seeders.',
            ], 500);
        }

        $usuario = DB::transaction(function () use ($request, $rolCliente, $tipoDocumento, $tipoCliente) {
            $persona = Persona::create([
                'per_tpdid' => $tipoDocumento->tpd_id,
                'per_nombr1' => $request->nombre1,
                'per_nombr2' => $request->nombre2,
                'per_apell1' => $request->apellido1,
                'per_apell2' => $request->apellido2,
                'per_estado' => 1,
            ]);

            Cliente::create([
                'cli_perid' => $persona->per_id,
                'cli_tpcid' => $tipoCliente->tpc_id,
                'cli_estado' => 1,
            ]);

            if ($request->filled('celular')) {
				$persona->contactos()->create([
					'con_tipo' => 'CELULAR',
					'con_valor' => $request->celular,
					'con_estado' => 1,
				]);
			}

			$persona->contactos()->create([
					'con_tipo' => 'EMAIL',
					'con_valor' => $request->email,
					'con_estado' => 1,
			]);


            return Usuario::create([
                'usu_perid' => $persona->per_id,
                'usu_rolid' => $rolCliente->rol_id,
                'usu_login' => $request->email,
                'usu_passwd' => bcrypt($request->password),
                'usu_estado' => Usuario::ESTADO_INACTIVO,
            ]);
        });

        $codigo = $this->generarTokenVerificacion($usuario, TokenVerificacion::TIPO_VERIFICACION_EMAIL, self::MINUTOS_EXPIRACION_VERIFICACION_EMAIL);

        Mail::to($usuario->usu_login)->send(new verifiedMail($usuario, $codigo));

        return response()->json($usuario->load('persona'), 201);
    }

    /**
     * Confirma el correo de un usuario recien registrado a partir del
     * token enviado por email (token_verificacion, tipo verificacion_email).
     */
    public function verified_auth(Request $request)
    {
        $tokenRow = $this->buscarTokenValido($request->code_user, TokenVerificacion::TIPO_VERIFICACION_EMAIL);

        if (! $tokenRow) {
            return response()->json(['message' => 403]);
        }

        $tokenRow->usuario->update(['usu_estado' => Usuario::ESTADO_ACTIVO]);
        $tokenRow->update(['tkv_usado' => 1]);

        return response()->json(['message' => 200]);
    }

    /**
     * Paso 1 de recuperacion de contrasena: genera un codigo de un solo uso
     * y lo envia por correo (token_verificacion, tipo recuperacion_password).
     */
    public function verified_email(Request $request)
    {
        $usuario = Usuario::where('usu_login', $request->email)->first();

        if (! $usuario) {
            return response()->json(['message' => 403]);
        }

        $codigo = $this->generarTokenVerificacion($usuario, TokenVerificacion::TIPO_RECUPERACION_PASSWORD, self::MINUTOS_EXPIRACION_RECUPERACION_PASSWORD);

        Mail::to($usuario->usu_login)->send(new ForgotPasswordMail($usuario, $codigo));

        return response()->json(['message' => 200]);
    }

    /**
     * Paso 2: valida el codigo recibido por correo antes de dejar poner una
     * contrasena nueva.
     */
    public function verified_code(Request $request)
    {
        $tokenRow = $this->buscarTokenValido($request->code, TokenVerificacion::TIPO_RECUPERACION_PASSWORD);

        return response()->json(['message' => $tokenRow ? 200 : 403]);
    }

    /**
     * Paso 3: aplica la nueva contrasena. No se valida contra el historial
     * (historial_password se usa solo como registro/auditoria).
     */
    public function new_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'new_password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $tokenRow = $this->buscarTokenValido($request->code, TokenVerificacion::TIPO_RECUPERACION_PASSWORD);

        if (! $tokenRow) {
            return response()->json(['message' => 403]);
        }

        $usuario = $tokenRow->usuario;

        HistorialPassword::create([
            'hpa_usuid' => $usuario->usu_id,
            'hpa_pasant' => $usuario->usu_passwd,
            'hpa_iporig' => $request->ip(),
        ]);

        $usuario->update(['usu_passwd' => bcrypt($request->new_password)]);
        $tokenRow->update(['tkv_usado' => 1]);

        return response()->json(['message' => 200]);
    }

    /**
     * Login del panel administrativo (CoreUI). Solo roles Administrador y
     * Vendedor pueden entrar por aqui.
     */
    public function login(Request $request)
    {
        return $this->intentarLogin($request, ['Administrador', 'Vendedor'], exigirActivo: false);
    }

    /**
     * Login del ecommerce. Solo rol Cliente, y exige que el correo ya haya
     * sido verificado (usu_estado = ACTIVO).
     */
    public function login_ecommerce(Request $request)
    {
        return $this->intentarLogin($request, ['Cliente'], exigirActivo: true);
    }

    
    /**
     * Login/registro del ecommerce con cuenta de Google.
     * Recibe el ID token (credential) que entrega Google Identity Services.
     */
    public function login_google(Request $request, GoogleAuthService $google)
    {
        $validator = Validator::make($request->all(), [
            'credential' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $google->verificarToken($request->credential);

        if (! $payload) {
            $this->registrarIntento(null, 'google', false, 'token de Google invalido', $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $usuario = $google->obtenerOCrearCliente($payload);

        if ($usuario->usu_estado === Usuario::ESTADO_BLOQUEADO) {
            $this->registrarIntento($usuario->usu_id, $usuario->usu_login, false, 'cuenta bloqueada (google)', $request);
            return response()->json(['error' => 'Cuenta bloqueada. Contacte al administrador.'], 423);
        }

        if ($usuario->rol?->rol_descri !== 'Cliente') {
            $this->registrarIntento($usuario->usu_id, $usuario->usu_login, false, 'rol no autorizado (google)', $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $this->registrarIntento($usuario->usu_id, $usuario->usu_login, true, 'login con Google', $request);

        $token = auth('api')->login($usuario);

        return $this->respondWithToken($token, $usuario, $request);
    }

    private function intentarLogin(Request $request, array $rolesPermitidos, bool $exigirActivo)
    {
        $login = (string) $request->email;
        $password = (string) $request->password;

        $usuario = Usuario::where('usu_login', $login)->first();

        if (! $usuario) {
            $this->registrarIntento(null, $login, false, 'usuario no encontrado', $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($usuario->usu_estado === Usuario::ESTADO_BLOQUEADO) {
            $this->registrarIntento($usuario->usu_id, $login, false, 'cuenta bloqueada', $request);
            return response()->json(['error' => 'Cuenta bloqueada. Contacte al administrador.'], 423);
        }

        if (! in_array($usuario->rol?->rol_descri, $rolesPermitidos, true)) {
            $this->registrarIntento($usuario->usu_id, $login, false, 'rol no autorizado para este acceso', $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! Hash::check($password, $usuario->usu_passwd)) {
            $this->registrarIntento($usuario->usu_id, $login, false, 'password incorrecto', $request);

            if ($this->intentosFallidosConsecutivos($usuario->usu_id) >= self::MAX_INTENTOS_FALLIDOS) {
                $usuario->update(['usu_estado' => Usuario::ESTADO_BLOQUEADO]);
                return response()->json(['error' => 'Cuenta bloqueada por demasiados intentos fallidos.'], 423);
            }

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($exigirActivo && $usuario->usu_estado !== Usuario::ESTADO_ACTIVO) {
            $this->registrarIntento($usuario->usu_id, $login, false, 'correo no verificado', $request);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $this->registrarIntento($usuario->usu_id, $login, true, null, $request);

        $token = auth('api')->login($usuario);

        return $this->respondWithToken($token, $usuario, $request);
    }

    public function me()
    {
        $usuario = auth('api')->user()->load(['persona', 'rol']);

        return response()->json([
            'usuario_id' => $usuario->usu_id,
            'email' => $usuario->usu_login,
            'estado' => $usuario->usu_estado,
            'full_name' => $usuario->persona?->nombre_completo,
            'persona' => $usuario->persona,
            'rol' => $usuario->rol?->rol_descri,
            'permisos' => $usuario->rol ? $usuario->rol->permisos()->pluck('prm_descri') : [],
        ]);
    }

    public function logout(Request $request)
    {
        $usuario = auth('api')->user();
        $token = $request->bearerToken();

        if ($usuario && $token) {
            Sesion::where('ses_usuid', $usuario->usu_id)
                ->where('ses_tokhas', hash('sha256', $token))
                ->update(['ses_revoca' => 1]);
        }

        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh(Request $request)
    {
        $oldToken = $request->bearerToken();
        $usuario = auth('api')->user();

        $newToken = auth('api')->refresh();

        if ($usuario && $oldToken) {
            Sesion::where('ses_usuid', $usuario->usu_id)
                ->where('ses_tokhas', hash('sha256', $oldToken))
                ->update(['ses_revoca' => 1]);
        }

        return $this->respondWithToken($newToken, $usuario, $request);
    }

    protected function respondWithToken(string $token, Usuario $usuario, Request $request)
    {
        $ttlSegundos = auth('api')->factory()->getTTL() * 60;

        Sesion::create([
            'ses_usuid' => $usuario->usu_id,
            'ses_tokhas' => hash('sha256', $token),
            'ses_iporig' => $request->ip(),
            'ses_userag' => substr((string) $request->userAgent(), 0, 255),
            'ses_fecexp' => now()->addSeconds($ttlSegundos),
            'ses_revoca' => 0,
        ]);

        $usuario->loadMissing(['persona', 'rol']);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlSegundos,
            'user' => [
                'full_name' => $usuario->persona?->nombre_completo,
                'email' => $usuario->usu_login,
                'rol' => $usuario->rol?->rol_descri,
                'permisos' => $usuario->rol ? $usuario->rol->permisos()->pluck('prm_descri') : [],
            ],
        ]);
    }

    // ----- Helpers privados -----

    private function registrarIntento(?int $usuId, string $loginIntentado, bool $exito, ?string $obs, Request $request): void
    {
        IntentoSesion::create([
            'ise_usuid' => $usuId,
            'ise_logusa' => $loginIntentado,
            'ise_result' => $exito ? 1 : 0,
            'ise_obs' => $obs,
            'ise_iporig' => $request->ip(),
            'ise_userag' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /**
     * Cuenta cuantos intentos fallidos consecutivos tiene el usuario,
     * mirando los ultimos intentos desde el mas reciente hacia atras hasta
     * encontrar un intento exitoso (o quedarse sin intentos).
     */
    private function intentosFallidosConsecutivos(int $usuId): int
    {
        $intentos = IntentoSesion::where('ise_usuid', $usuId)
            ->orderByDesc('ise_id')
            ->limit(self::MAX_INTENTOS_FALLIDOS)
            ->pluck('ise_result');

        $consecutivos = 0;
        foreach ($intentos as $resultado) {
            if ((int) $resultado === 1) {
                break;
            }
            $consecutivos++;
        }

        return $consecutivos;
    }

    /**
     * Genera un token_verificacion, retornando el codigo en claro (el que se
     * envia por correo). En base de datos solo se guarda el hash (sha256).
     */
    private function generarTokenVerificacion(Usuario $usuario, string $tipo, int $minutosExpiracion): string
    {
        $codigo = $tipo === TokenVerificacion::TIPO_RECUPERACION_PASSWORD
            ? (string) random_int(100000, 999999) // codigo corto para tipear a mano
            : Str::random(40); // token largo tipo link, para el correo de verificacion

        TokenVerificacion::create([
            'tkv_usuid' => $usuario->usu_id,
            'tkv_tipo' => $tipo,
            'tkv_tokhas' => hash('sha256', $codigo),
            'tkv_fecexp' => now()->addMinutes($minutosExpiracion),
            'tkv_usado' => 0,
        ]);

        return $codigo;
    }

    private function buscarTokenValido(?string $codigo, string $tipo): ?TokenVerificacion
    {
        if (! $codigo) {
            return null;
        }

        return TokenVerificacion::where('tkv_tokhas', hash('sha256', $codigo))
            ->where('tkv_tipo', $tipo)
            ->where('tkv_usado', 0)
            ->where('tkv_fecexp', '>', now())
            ->first();
    }

    /*
     * NOTA para implementar bloqueo por TIEMPO en vez de indefinido:
     * 1) Agregar una columna nullable, p.ej. usu_bloqueado_hasta (TIMESTAMP),
     *    a la migracion de `usuario`.
     * 2) Al bloquear (arriba, en intentarLogin), en vez de solo cambiar
     *    usu_estado, setear tambien: usu_bloqueado_hasta = now()->addMinutes(X).
     * 3) Antes de validar la contrasena, si usu_estado == BLOQUEADO, revisar
     *    si now() > usu_bloqueado_hasta; si ya paso, reactivar el usuario
     *    (usu_estado = ACTIVO/INACTIVO segun corresponda) y dejar continuar
     *    el login en vez de rechazarlo.
     */
}