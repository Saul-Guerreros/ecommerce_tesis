<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bloque de datos personales del BORRADOR (solicitud_temporal.stm_person).
 *
 * Trabaja sobre un arreglo y no sobre un modelo, igual que
 * EntregaResource con pedido_temporal.ptm_entrega.
 *
 * Devuelve tanto los ids como las etiquetas (ciudad, departamento,
 * profesion, tipo de profesion) porque sirve a dos pantallas distintas
 * con necesidades distintas: el formulario del Paso 1 necesita los ids
 * para repoblar los desplegables al volver con [Editar], y la pantalla
 * de Confirmacion necesita los textos para mostrarlos sin volver a
 * buscar el catalogo.
 *
 * scr_tprid (relacion laboral) y scr_proid (profesion) son dos campos
 * independientes: ninguno filtra al otro.
 */
class SolicitudPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            // 1/3 Identificacion
            'scr_cedula' => $p['scr_cedula'] ?? null,
            'scr_nombre' => $p['scr_nombre'] ?? null,
            'scr_apelli' => $p['scr_apelli'] ?? null,
            'scr_fechna' => $p['scr_fechna'] ?? null,
            'scr_celula' => $p['scr_celula'] ?? null,
            'scr_email'  => $p['scr_email']  ?? null,

            // 2/3 Domicilio
            'scr_ciuid'    => $p['scr_ciuid']    ?? null,
            'dep_id'       => $p['dep_id']       ?? null,
            'ciudad'       => $p['ciudad']       ?? null,
            'departamento' => $p['departamento'] ?? null,
            'scr_barrio'   => $p['scr_barrio']   ?? null,
            'scr_calle'    => $p['scr_calle']    ?? null,
            'scr_nrocasa'  => $p['scr_nrocasa']  ?? null,
            'scr_referen'  => $p['scr_referen']  ?? null,

            // 3/3 Situacion personal
            'scr_estciv' => $p['scr_estciv'] ?? null,
            'scr_tprid'  => $p['scr_tprid']  ?? null,
            'tipo_profesion' => $p['tipo_profesion'] ?? null,
            'scr_proid'  => $p['scr_proid']  ?? null,
            'profesion'  => $p['profesion']  ?? null,
            'scr_tipviv' => $p['scr_tipviv'] ?? null,
            'scr_sertit' => $p['scr_sertit'] ?? null,
            'scr_relsti' => $p['scr_relsti'] ?? null,

            // Resumen de una linea para el acordeon de Confirmacion.
            'resumen' => $p['resumen'] ?? null,
        ];
    }
}