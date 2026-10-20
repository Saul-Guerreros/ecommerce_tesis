<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud de credito ya generada, con todo lo que el cliente declaro.
 *
 * Requiere eager loading de 'estado', 'ciudad.departamento',
 * 'profesion', 'tipoProfesion', 'detalles' y 'referencias' (ver la
 * constante CON del controlador): recorrer esto sin precargar seria un
 * N+1 garantizado.
 *
 * Los datos van agrupados en bloques (personal / laboral /
 * referencias / credito) y no como una lista plana de 40 campos,
 * porque es exactamente la forma en que los consume el acordeon de la
 * pantalla de Confirmacion y, mas adelante, la ficha del panel
 * administrativo.
 */
class SolicitudCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $antiguedad = $this->antiguedadLaboral();

        return [
            'scr_id'     => $this->scr_id,
            'numero'     => $this->scr_numero,
            'fecha'      => $this->scr_creado?->format('d/m/Y H:i'),
            'scr_declar' => (bool) $this->scr_declar,

            'estado' => [
                'codigo'      => $this->estado?->esc_codigo,
                'descripcion' => $this->estado?->esc_descri,
            ],

            'personal' => [
                'scr_cedula'   => $this->scr_cedula,
                'scr_nombre'   => $this->scr_nombre,
                'scr_apelli'   => $this->scr_apelli,
                'nombre_completo' => $this->nombreCompleto(),
                'scr_fechna'   => $this->scr_fechna?->format('Y-m-d'),
                'scr_celula'   => $this->scr_celula,
                'scr_email'    => $this->scr_email,

                'scr_ciuid'    => $this->scr_ciuid,
                'ciudad'       => $this->ciudad?->ciu_descri,
                'departamento' => $this->ciudad?->departamento?->dep_descri,
                'scr_barrio'   => $this->scr_barrio,
                'scr_calle'    => $this->scr_calle,
                'scr_nrocasa'  => $this->scr_nrocasa,
                'scr_referen'  => $this->scr_referen,

                'scr_estciv'     => $this->scr_estciv,
                'scr_tprid'      => $this->scr_tprid,
                'tipo_profesion' => $this->tipoProfesion?->tpr_descri,
                'scr_proid'      => $this->scr_proid,
                'profesion'      => $this->profesion?->pro_descri,
                'scr_tipviv'     => $this->scr_tipviv,

                // Solo tienen valor con vivienda propia; con alquiler la
                // base garantiza que esten en NULL.
                'scr_sertit' => $this->scr_sertit,
                'scr_relsti' => $this->scr_relsti,
            ],

            'laboral' => [
                'scr_empres' => $this->scr_empres,
                'scr_dirlab' => $this->scr_dirlab,
                'scr_tellab' => $this->scr_tellab,
                'scr_cargo'  => $this->scr_cargo,
                'scr_ingres' => (float) $this->scr_ingres,
                'scr_fecing' => $this->scr_fecing?->format('Y-m-d'),
                'antiguedad' => $antiguedad['texto'],
            ],

            'referencias' => SolicitudReferenciaResource::collection(
                $this->whenLoaded('referencias')
            ),

            'detalles' => SolicitudDetalleResource::collection(
                $this->whenLoaded('detalles')
            ),

            // Totales congelados al generar. NO se recalculan contra el
            // catalogo actual: es lo que el cliente declaro y lo que el
            // administrador evalua.
            'credito' => [
                'total_contado' => (float) $this->scr_toncon,
                'recargo'       => (float) $this->scr_recfin,
                'total'         => (float) $this->scr_total,
                'impuestos'     => (float) $this->scr_impues,
                'plazo'         => (int) $this->scr_plazo,
                'cuota_mensual' => (float) $this->scr_cuomen,
            ],
        ];
    }
}