<?php

namespace App\Services\Cliente;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta y búsqueda de clientes, compartida por:
 *  - el CRUD de Clientes del panel (ClienteController), y
 *  - el ingreso de servicio técnico en el mostrador (alta rápida sin credenciales).
 *
 * Un cliente de mostrador es persona + cliente + contactos, SIN usuario. Si después se
 * registra en la tienda con el mismo correo, VinculacionCuentaService le crea el usuario
 * sobre esta misma persona (así conserva su historial).
 */
class ClienteRapidoService
{
    /** Documentos admitidos en el mostrador (decisión A.3: solo personas físicas). */
    public const DOCUMENTOS_MOSTRADOR = ['CI', 'PAS'];

    private const TIPO_CLIENTE_DEFECTO = 'PERSONA FISICA';

    // ---------- Búsqueda ----------

    /** Persona por tipo + número de documento (flujo híbrido del CRUD). */
    public function personaPorDocumento(int $tpdId, string $documento): ?Persona
    {
        return Persona::with(['cliente', 'contactos:con_id,con_perid,con_tipo,con_valor'])
            ->where('per_tpdid', $tpdId)
            ->where('per_cedula', $documento)
            ->first();
    }

       /**
     * Búsqueda del mostrador (mínimo 4 caracteres, máximo 10 resultados).
     *
     * 1.º las coincidencias EXACTAS: documento, correo o celular completos.
     * 2.º si sobra lugar, las PARCIALES:
     *    - nombre y apellido: cada palabra puede estar en cualquiera de los 4 campos y no
     *      importan mayúsculas, tildes ni la ñ ("jose perez" encuentra a "José Luis Pérez");
     *    - correo: alcanza con un pedazo ("marco.barr", "@gmail");
     *    - documento y celular: por dígitos, aunque estén escritos con puntos o espacios.
     */
    public function buscar(string $termino): Collection
    {
        $termino = trim($termino);
        $limite  = 10;

        $exactos = $this->clientesConPersona()
            ->whereHas('persona', fn ($p) => $this->coincidenciaExacta($p, $termino))
            ->orderByDesc('cli_id')
            ->limit($limite)
            ->get();

        $faltan = $limite - $exactos->count();

        $parciales = $faltan > 0
            ? $this->clientesConPersona()
                ->whereNotIn('cli_id', $exactos->pluck('cli_id')->all())
                ->whereHas('persona', fn ($p) => $this->coincidenciaParcial($p, $termino))
                ->orderByDesc('cli_id')
                ->limit($faltan)
                ->get()
            : collect();

        return $exactos->concat($parciales)->map(fn (Cliente $c) => $this->resumen($c))->values();
    }

    private function clientesConPersona()
    {
        return Cliente::query()
            ->with(['persona.tipoDocumento:tpd_id,tpd_codigo', 'persona.contactos', 'persona.usuario:usu_id,usu_perid']);
    }

    /** Documento, correo o celular COMPLETOS (la regla original del mostrador). */
    private function coincidenciaExacta($persona, string $termino): void
    {
        $digitos = preg_replace('/\D/', '', $termino);
        $correo  = mb_strtolower($termino);

        $persona->where(function ($w) use ($termino, $digitos, $correo) {
            $w->where('per_cedula', $termino);

            if (strlen($digitos) >= 5) {
                $w->orWhereRaw("regexp_replace(coalesce(per_cedula, ''), '\\D', '', 'g') = ?", [$digitos]);
                $w->orWhereHas('contactos', fn ($c) => $c
                    ->whereIn('con_tipo', ['CELULAR', 'WHATSAPP', 'TELEFONO'])
                    ->whereRaw("regexp_replace(con_valor, '\\D', '', 'g') = ?", [$digitos]));
            }

            if (str_contains($termino, '@')) {
                $w->orWhereHas('contactos', fn ($c) => $c
                    ->where('con_tipo', 'EMAIL')->whereRaw('LOWER(con_valor) = ?', [$correo]));
                $w->orWhereHas('usuario', fn ($u) => $u->whereRaw('LOWER(usu_login) = ?', [$correo]));
            }
        });
    }

    /** Coincidencias PARCIALES por nombre, correo, documento o celular. */
    private function coincidenciaParcial($persona, string $termino): void
    {
        $texto       = $this->sinTildes(mb_strtolower($termino));
        $palabras    = preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $soloDigitos = (bool) preg_match('/^[\d\s.\-+()]+$/', $termino);   // "0981 123", "1.234.5"
        $digitos     = preg_replace('/\D/', '', $termino);

        $hayCondicion = false;

        $persona->where(function ($w) use ($texto, $palabras, $soloDigitos, $digitos, &$hayCondicion) {
            // Número: documento o celular por dígitos, sin importar puntos, espacios ni guiones.
            if ($soloDigitos) {
                if (strlen($digitos) >= 4) {
                    $hayCondicion = true;
                    $like = '%' . $digitos . '%';
                    $w->orWhereRaw("regexp_replace(coalesce(per_cedula, ''), '\\D', '', 'g') LIKE ?", [$like]);
                    $w->orWhereHas('contactos', fn ($c) => $c
                        ->whereIn('con_tipo', ['CELULAR', 'WHATSAPP', 'TELEFONO'])
                        ->whereRaw("regexp_replace(con_valor, '\\D', '', 'g') LIKE ?", [$like]));
                }
                return;
            }

            // Una sola palabra sin espacios: puede ser un pedazo de correo o un documento alfanumérico.
            if (count($palabras) === 1) {
                $hayCondicion = true;
                $like = '%' . $this->escaparLike($texto) . '%';
                $w->orWhereRaw("LOWER(coalesce(per_cedula, '')) LIKE ?", [$like]);
                $w->orWhereHas('contactos', fn ($c) => $c
                    ->where('con_tipo', 'EMAIL')->whereRaw('LOWER(con_valor) LIKE ?', [$like]));
                $w->orWhereHas('usuario', fn ($u) => $u->whereRaw('LOWER(usu_login) LIKE ?', [$like]));
            }

            // Nombre y apellido: todas las palabras deben aparecer, en cualquier orden.
            if (!str_contains($texto, '@') && $palabras !== [] && max(array_map('mb_strlen', $palabras)) >= 3) {
                $hayCondicion = true;
                $w->orWhere(function ($n) use ($palabras) {
                    foreach (array_slice($palabras, 0, 4) as $palabra) {
                        $like = '%' . $this->escaparLike($palabra) . '%';
                        $n->where(function ($c) use ($like) {
                            foreach (['per_nombr1', 'per_nombr2', 'per_apell1', 'per_apell2'] as $col) {
                                $c->orWhereRaw(
                                    "lower(translate(coalesce({$col}, ''), 'áéíóúüÁÉÍÓÚÜñÑ', 'aeiouuAEIOUUnn')) LIKE ?",
                                    [$like]
                                );
                            }
                        });
                    }
                });
            }
        });

        // Sin ninguna condición (p. ej. "a b c") Laravel descartaría el grupo vacío y devolvería
        // a TODOS los clientes: en ese caso no debe encontrarse nada.
        if (!$hayCondicion) {
            $persona->whereRaw('1 = 0');
        }
    }

    /** Minúsculas ya aplicadas: quita tildes y unifica ñ con n (igual que el translate() del SQL). */
    private function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Para que % y _ escritos por el usuario se busquen como texto y no como comodines de LIKE. */
    private function escaparLike(string $texto): string
    {
        return addcslashes($texto, '%_\\');
    }
    /** Forma única con la que el mostrador muestra a un cliente. */
    public function resumen(Cliente $cliente): array
    {
        $cliente->loadMissing(['persona.tipoDocumento:tpd_id,tpd_codigo', 'persona.contactos', 'persona.usuario:usu_id,usu_perid']);
        $p         = $cliente->persona;
        $contactos = $p?->contactos?->where('con_estado', 1) ?? collect();

        return [
            'cli_id'       => $cliente->cli_id,
            'cli_estado'   => (int) $cliente->cli_estado,
            'nombre'       => $p?->nombre_completo,
            'tpd_codigo'   => $p?->tipoDocumento?->tpd_codigo,
            'documento'    => $p?->per_cedula,
            'celular'      => $contactos->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])->first()?->con_valor,
            'whatsapp'     => $contactos->where('con_tipo', 'WHATSAPP')->first()?->con_valor,
            'correo'       => $contactos->where('con_tipo', 'EMAIL')->first()?->con_valor,
            'tiene_cuenta' => $p?->usuario !== null,
        ];
    }

    // ---------- Alta del CRUD de clientes (comportamiento sin cambios) ----------

    public function registrar(array $data): Cliente
    {
        return DB::transaction(function () use ($data) {
            $persona = Persona::updateOrCreate(
                ['per_tpdid' => $data['per_tpdid'], 'per_cedula' => $data['per_cedula']],
                [
                    'per_nombr1' => $data['per_nombr1'],
                    'per_nombr2' => $data['per_nombr2'] ?? null,
                    'per_apell1' => $data['per_apell1'],
                    'per_apell2' => $data['per_apell2'] ?? null,
                    'per_estciv' => $data['per_estciv'] ?? null,
                    'per_fechna' => $data['per_fechna'] ?? null,
                    'per_estado' => 1,
                    'per_cread'  => now(),
                ]
            );

            if ($persona->cliente()->exists()) {
                throw ValidationException::withMessages([
                    'per_cedula' => ['Esta persona ya está registrada como cliente.'],
                ]);
            }

            $cliente = Cliente::create([
                'cli_perid'  => $persona->per_id,
                'cli_tpcid'  => $data['cli_tpcid'],
                'cli_ruc'    => $data['cli_ruc'] ?? null,
                'cli_estado' => $data['cli_estado'],
                'cli_creado' => now(),
            ]);

            $this->sincronizarContactos($persona, $data['contactos']);

            return $cliente;
        });
    }

    /** Reemplaza el set completo de contactos de la persona (CRUD de clientes). */
    public function sincronizarContactos(Persona $persona, array $contactos): void
    {
        $persona->contactos()->delete();

        foreach ($contactos as $c) {
            $persona->contactos()->create([
                'con_tipo'    => $c['con_tipo'],
                'con_valor'   => trim($c['con_valor']),
                'con_estado'  => 1,
                'con_creado'  => now(),
                'con_modific' => now(),
            ]);
        }
    }

    // ---------- Alta rápida del mostrador ----------

    /**
     * @param array{per_tpdid:int, per_cedula:string, per_nombr1:string, per_nombr2?:?string,
     *              per_apell1:string, per_apell2?:?string, celular:string, es_whatsapp?:bool,
     *              correo?:?string} $d
     */
    public function altaRapida(array $d): Cliente
    {
        $tipoDoc   = TipoDocumento::findOrFail($d['per_tpdid']);
        $documento = $this->normalizarDocumento($tipoDoc->tpd_codigo, $d['per_cedula']);
        $correo    = isset($d['correo']) && trim((string) $d['correo']) !== '' ? mb_strtolower(trim($d['correo'])) : null;

        $existente = $this->personaConDocumento($tipoDoc->tpd_id, $documento);

        if ($existente?->cliente) {
            throw ValidationException::withMessages([
                'per_cedula' => 'Ya existe un cliente con ese documento. Búsquelo y selecciónelo.',
            ]);
        }

        if ($correo) {
            $this->exigirCorreoLibre($correo, $existente?->per_id);
        }

        return DB::transaction(function () use ($d, $tipoDoc, $documento, $correo, $existente) {
            // Persona existente sin perfil de cliente (por ejemplo, un funcionario):
            // se reutiliza sin pisar sus datos.
            $persona = $existente ?? Persona::create([
                'per_tpdid'  => $tipoDoc->tpd_id,
                'per_cedula' => $documento,
                'per_nombr1' => trim($d['per_nombr1']),
                'per_nombr2' => $this->nulo($d['per_nombr2'] ?? null),
                'per_apell1' => trim($d['per_apell1']),
                'per_apell2' => $this->nulo($d['per_apell2'] ?? null),
                'per_estado' => 1,
            ]);

            $cliente = Cliente::create([
                'cli_perid'  => $persona->per_id,
                'cli_tpcid'  => $this->tipoClienteDefecto(),
                'cli_estado' => 1,
            ]);

            $celular = trim($d['celular']);
            $this->agregarContacto($persona, 'CELULAR', $celular);
            if (!empty($d['es_whatsapp'])) {
                $this->agregarContacto($persona, 'WHATSAPP', $celular);
            }
            if ($correo) {
                $this->agregarContacto($persona, 'EMAIL', $correo);
            }

            return $cliente;
        });
    }

    /**
     * Completa el documento de un cliente que no lo tiene (típicamente una cuenta web,
     * que se registra sin cédula). Mismo criterio que el checkout: no puede estar en otra persona.
     */
    public function completarDocumento(Cliente $cliente, int $tpdId, string $documento): Cliente
    {
        $persona = $cliente->persona;

        if (filled($persona->per_cedula)) {
            throw ValidationException::withMessages([
                'per_cedula' => 'El cliente ya tiene un documento registrado.',
            ]);
        }

        $tipoDoc   = TipoDocumento::findOrFail($tpdId);
        $documento = $this->normalizarDocumento($tipoDoc->tpd_codigo, $documento);

        $ocupado = $this->personaConDocumento($tipoDoc->tpd_id, $documento);
        if ($ocupado && $ocupado->per_id !== $persona->per_id) {
            throw ValidationException::withMessages([
                'per_cedula' => 'Ese documento ya está registrado en otra persona.',
            ]);
        }

        $persona->update(['per_tpdid' => $tipoDoc->tpd_id, 'per_cedula' => $documento]);

        return $cliente->fresh();
    }

    // ---------- Auxiliares ----------

    /** C.I.: solo dígitos ("1.234.567" y "1234567" son la misma cédula). Pasaporte: en mayúsculas. */
    private function normalizarDocumento(string $tpdCodigo, string $documento): string
    {
        $documento = trim($documento);

        return $tpdCodigo === 'CI' ? preg_replace('/\D/', '', $documento) : mb_strtoupper($documento);
    }

    private function personaConDocumento(int $tpdId, string $documento): ?Persona
    {
        return Persona::with('cliente')
            ->where('per_tpdid', $tpdId)
            ->whereRaw("regexp_replace(coalesce(per_cedula, ''), '[\\.\\s-]', '', 'g') = ?", [$documento])
            ->first();
    }

    /**
     * El correo identifica la futura cuenta web (vinculación por correo verificado).
     * No puede pertenecer a otra persona ni ser el login de otro usuario.
     */
    private function exigirCorreoLibre(string $correo, ?int $perIdPropio): void
    {
        $enUsuario = Usuario::whereRaw('LOWER(usu_login) = ?', [$correo])
            ->when($perIdPropio, fn ($q) => $q->where('usu_perid', '<>', $perIdPropio))
            ->exists();

        $enContacto = DB::table('contacto')
            ->where('con_tipo', 'EMAIL')
            ->whereRaw('LOWER(con_valor) = ?', [$correo])
            ->when($perIdPropio, fn ($q) => $q->where('con_perid', '<>', $perIdPropio))
            ->exists();

        if ($enUsuario || $enContacto) {
            throw ValidationException::withMessages([
                'correo' => 'Ese correo ya está registrado para otra persona. Busque al cliente por su correo.',
            ]);
        }
    }

    private function agregarContacto(Persona $persona, string $tipo, string $valor): void
    {
        $persona->contactos()->firstOrCreate(
            ['con_tipo' => $tipo, 'con_valor' => $valor],
            ['con_estado' => 1]
        );
    }

    private function tipoClienteDefecto(): int
    {
        return TipoCliente::where('tpc_descri', self::TIPO_CLIENTE_DEFECTO)->value('tpc_id')
            ?? TipoCliente::query()->value('tpc_id');
    }

    private function nulo(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
