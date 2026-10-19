<?php

namespace App\Services\Pedido;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\Pedido;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Plazo para retirar un pedido en sucursal, contado en días hábiles.
 *
 * Días hábiles y no corridos por un motivo concreto: un pedido que queda
 * listo un viernes con 2 días corridos de plazo vencería el domingo, con
 * la sucursal cerrada — el cliente nunca tuvo chance real de retirarlo.
 *
 * Qué día es hábil lo define cada sucursal (suc_diaslab), no la tienda:
 * dos locales pueden atender días distintos. Cuántos días son lo define
 * la tienda (configuracion_tienda.ctn_retdias), porque es una política
 * comercial y no una característica del local.
 */
class PlazoRetiroService
{
    /**
     * Tope de días calendario que se recorren buscando días hábiles.
     *
     * Es una red de seguridad, no una regla de negocio: si una sucursal
     * quedara con una máscara de días imposible, el bucle terminaría
     * igual en vez de colgar el request. Sucursal::esDiaLaborable() ya
     * cae al default ante una máscara inválida, así que esto sólo actúa
     * si algo se nos escapó.
     */
    private const TOPE_DIAS_CALENDARIO = 120;

    /**
     * Fecha límite de retiro para un pedido que acaba de pasar a LISTO.
     *
     * Devuelve null en dos casos, y los dos dejan al pedido exactamente
     * igual que si la funcionalidad nunca hubiera existido:
     *
     *   - El pedido no es de retiro en sucursal: en un envío a domicilio
     *     no hay nada que el cliente tenga que ir a buscar.
     *   - La funcionalidad está apagada (ctn_retactivo = false): es el
     *     único punto donde hace falta chequear el interruptor, porque
     *     todo lo demás (bandeja, barrido, avisos al cliente) ya está
     *     condicionado a que ped_vencret tenga valor. Si acá nunca se
     *     setea, el resto del sistema queda dormido sin tocar nada más.
     */
    public function calcularParaPedido(Pedido $pedido, ?CarbonInterface $desde = null): ?Carbon
    {
        $pedido->loadMissing('tipoEntrega', 'sucursal');

        if (!$pedido->esRetiro() || !ConfiguracionTienda::actual()->ctn_retactivo) {
            return null;
        }

        return $this->calcular($pedido->sucursal, $desde);
    }

    /**
     * Suma N días hábiles de la sucursal a la fecha de partida.
     *
     * El día en que el pedido queda listo NO cuenta como día de plazo:
     * puede quedar listo a las 17:55 de un día que cierra a las 18:00.
     * Por eso el conteo arranca al día siguiente.
     *
     * El resultado se fija al final del día (23:59:59): el plazo vence
     * cuando termina ese día hábil, no a la hora exacta en que el pedido
     * quedó listo cinco días antes.
     */
    public function calcular(?Sucursal $sucursal, ?CarbonInterface $desde = null): Carbon
    {
        $dias   = $this->diasConfigurados();
        $cursor = Carbon::parse($desde ?? now());

        $habilesContados = 0;
        $recorridos      = 0;

        while ($habilesContados < $dias && $recorridos < self::TOPE_DIAS_CALENDARIO) {
            $cursor->addDay();
            $recorridos++;

            // Sin sucursal cargada se cuenta como si todos los días
            // fueran hábiles. Es el caso degradado y no debería ocurrir
            // (un pedido de retiro siempre tiene ped_sucid), pero es
            // preferible un plazo algo más corto que un plazo infinito.
            if (!$sucursal || $sucursal->esDiaLaborable($cursor)) {
                $habilesContados++;
            }
        }

        return $cursor->endOfDay();
    }

    /**
     * Cuántos días hábiles de plazo. Se lee de la configuración en cada
     * cálculo (no se cachea en una constante) para que un cambio del
     * admin aplique a los pedidos nuevos sin reiniciar nada.
     *
     * El max(1, ...) evita que un 0 cargado por error deje a los clientes
     * sin plazo real para retirar.
     */
    private function diasConfigurados(): int
    {
        return max(1, (int) ConfiguracionTienda::actual()->ctn_retdias);
    }
}