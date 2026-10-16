<?php

namespace App\Services\Credito;

use App\Models\Configuracion\Ciudad;
use App\Models\Credito\Profesion;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoReferencia;
use App\Models\Credito\SolicitudTemporal;
use App\Models\Credito\TipoProfesion;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maneja los 3 pasos del wizard mientras siguen siendo borrador
 * (solicitud_temporal). Equivalente de CheckoutEntregaService +
 * CheckoutFacturacionService, pero para los tres pasos de datos en vez
 * de uno solo cada uno, porque acá los tres comparten la misma fila.
 *
 * Cada paso enriquece lo validado por su Request con los textos que los
 * Resources necesitan (ciudad, profesión, antigüedad) ANTES de guardarlo
 * en el jsonb. Así el borrador ya sale completo al leerlo de vuelta, sin
 * que el controlador tenga que resolver relaciones cada vez que pinta
 * el Paso 1/3 (Editar) desde la pantalla de Confirmación.
 */
class SolicitudBorradorService
{
    // ---------- Configuración inicial del wizard ----------

    /**
     * Catálogos + datos de perfil para precargar el Paso 1. El cliente
     * puede modificar estos valores en el formulario (así lo decidiste):
     * lo que se precarga es solo el punto de partida, nunca se escribe
     * de vuelta en persona/direccion/contacto.
     */
    public function datosIniciales(Usuario $usuario): array
    {
        $persona = $usuario->persona;

        return [
            'tipos_profesion' => TipoProfesion::activos()
                ->orderBy('tpr_id')
                ->get(),
            'profesiones' => Profesion::activas()
                ->orderBy('pro_descri')
                ->get(),
            'estados_civiles' => SolicitudCredito::ESTADOS_CIVILES,
            'tipos_vivienda'  => SolicitudCredito::TIPOS_VIVIENDA,
            'relaciones'      => SolicitudCreditoReferencia::RELACIONES,
            'perfil'          => $this->perfilParaPrecarga($usuario, $persona),
        ];
    }

    private function perfilParaPrecarga(Usuario $usuario, Persona $persona): array
    {
        $celular = $persona->contactos()
            ->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])
            ->where('con_estado', 1)
            ->orderByRaw("con_tipo = 'CELULAR' DESC")
            ->value('con_valor');

        // Mismo campo que ya usa CheckoutFacturacionService para el correo
        // del cliente: la tabla usuario no tiene usu_email, el login ES
        // el correo (así se registró la cuenta).
        return [
            'scr_cedula' => $persona->per_cedula,
            'scr_nombre' => trim("{$persona->per_nombr1} {$persona->per_nombr2}"),
            'scr_apelli' => trim("{$persona->per_apell1} {$persona->per_apell2}"),
            'scr_fechna' => $persona->per_fechna?->format('Y-m-d'),
            'scr_celula' => $celular,
            'scr_email'  => $usuario->usu_login,
        ];
    }

    // ---------- Paso 1: Datos personales ----------

    public function guardarPersonal(Usuario $usuario, array $datos): array
    {
        $ciudad    = Ciudad::with('departamento')->findOrFail($datos['scr_ciuid']);
        $profesion = Profesion::findOrFail($datos['scr_proid']);
        $tipo      = TipoProfesion::findOrFail($datos['scr_tprid']);

        // Mismo criterio del CHECK ck_scr_vivienda: si no es casa propia,
        // los datos de servicio se descartan aunque hayan llegado en el
        // payload (un reintento con datos viejos no debe colarlos).
        $esPropia = $datos['scr_tipviv'] === SolicitudCredito::VIVIENDA_PROPIA;

        $bloque = array_merge($datos, [
            'scr_sertit' => $esPropia ? ($datos['scr_sertit'] ?? null) : null,
            'scr_relsti' => $esPropia ? ($datos['scr_relsti'] ?? null) : null,

            // Enriquecido para que el Resource no dependa de relaciones.
            'dep_id'         => $ciudad->departamento?->dep_id,
            'ciudad'         => $ciudad->ciu_descri,
            'departamento'   => $ciudad->departamento?->dep_descri,
            'profesion'      => $profesion->pro_descri,
            'tipo_profesion' => $tipo->tpr_descri,

            'resumen' => trim("{$datos['scr_nombre']} {$datos['scr_apelli']}")
                . ' - C.I. ' . $datos['scr_cedula'],
        ]);

        $temporal = SolicitudTemporal::updateOrCreate(
            ['stm_usuid' => $usuario->usu_id],
            ['stm_person' => $bloque, 'stm_paso' => SolicitudTemporal::PASO_PERSONAL]
        );

        return $temporal->stm_person;
    }

    // ---------- Paso 2: Datos laborales ----------

    public function guardarLaboral(Usuario $usuario, array $datos): array
    {
        $antiguedad = $this->calcularAntiguedad($datos['scr_fecing']);

        $bloque = array_merge($datos, [
            'antiguedad' => $antiguedad,
            'resumen'    => $datos['scr_empres']
                . ' - Gs. ' . number_format((float) $datos['scr_ingres'], 0, ',', '.'),
        ]);

        $temporal = SolicitudTemporal::firstOrNew(['stm_usuid' => $usuario->usu_id]);

        // El Paso 2 exige que el Paso 1 ya esté guardado: no tiene sentido
        // un wizard donde se pueda llegar al Paso 2 saltando el 1 (la UI
        // no lo permite, pero el backend es quien de verdad lo garantiza).
        if (!$temporal->exists || !$temporal->tienePersonal()) {
            throw ValidationException::withMessages([
                'scr_empres' => 'Completá primero los datos personales.',
            ]);
        }

        $temporal->stm_labora = $bloque;
        $temporal->stm_paso   = SolicitudTemporal::PASO_LABORAL;
        $temporal->save();

        return $temporal->stm_labora;
    }

    private function calcularAntiguedad(string $fechaIngreso): array
    {
        $desde = \Carbon\Carbon::parse($fechaIngreso);
        $hoy   = \Carbon\Carbon::now();

        $diferencia = $desde->diff($hoy);

        return [
            'anios' => $diferencia->y,
            'meses' => $diferencia->m,
            'texto' => SolicitudCredito::textoAntiguedad($diferencia->y, $diferencia->m),
        ];
    }

    // ---------- Paso 3: Referencias ----------

    public function guardarReferencias(Usuario $usuario, array $referencias): array
    {
        $temporal = SolicitudTemporal::firstOrNew(['stm_usuid' => $usuario->usu_id]);

        if (!$temporal->exists || !$temporal->tieneLaboral()) {
            throw ValidationException::withMessages([
                'referencias' => 'Completá primero los datos laborales.',
            ]);
        }

        $bloque = [];
        foreach (array_values($referencias) as $i => $referencia) {
            $bloque[] = [
                'scf_orden'  => $i + 1,
                'scf_nombre' => $referencia['scf_nombre'],
                'scf_celula' => $referencia['scf_celula'],
                'scf_relaci' => $referencia['scf_relaci'],
            ];
        }

        $temporal->stm_refere = $bloque;
        $temporal->stm_paso   = SolicitudTemporal::PASO_REFERENCIAS;
        $temporal->save();

        return $temporal->stm_refere;
    }

    // ---------- Lectura y descarte del borrador ----------

    public function borrador(Usuario $usuario): ?SolicitudTemporal
    {
        return SolicitudTemporal::where('stm_usuid', $usuario->usu_id)->first();
    }

    public function borradorCompletoOFallar(Usuario $usuario): SolicitudTemporal
    {
        $temporal = $this->borrador($usuario);

        if (!$temporal || !$temporal->completo()) {
            throw ValidationException::withMessages([
                'solicitud' => 'Todavía hay pasos sin completar en tu solicitud.',
            ]);
        }

        return $temporal;
    }

    public function descartar(Usuario $usuario): void
    {
        SolicitudTemporal::where('stm_usuid', $usuario->usu_id)->delete();
    }
}