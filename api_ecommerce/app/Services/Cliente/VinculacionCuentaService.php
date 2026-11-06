<?php

namespace App\Services\Cliente;

use App\Models\Persona;

/**
 * Decisión A.2 - Vinculación de una cuenta web con un cliente registrado en el mostrador.
 *
 * Cuando alguien se registra en la tienda (correo + contraseña o Google) y su correo coincide
 * con el contacto EMAIL de UNA persona que ya es cliente y todavía no tiene usuario, el usuario
 * nuevo se crea sobre esa persona: así ve en "Mis servicios" lo que dejó en la sucursal.
 *
 * Por qué es seguro:
 *  - Registro con contraseña: el usuario nace INACTIVO y solo se activa al verificar el correo
 *    (quien no controla ese buzón nunca llega a iniciar sesión).
 *  - Google: Google ya garantiza que el correo es de quien inicia sesión.
 * Nunca se vincula solo por C.I. (cualquiera que conozca una cédula vería datos ajenos).
 */
class VinculacionCuentaService
{
    /** Persona vinculable o null (ninguna, o más de una: en ese caso no se adivina). */
    public function personaVinculable(string $correo): ?Persona
    {
        $correo = mb_strtolower(trim($correo));

        $candidatas = Persona::query()
            ->whereDoesntHave('usuario')
            ->whereHas('cliente', fn ($c) => $c->where('cli_estado', 1))
            ->whereHas('contactos', fn ($c) => $c
                ->where('con_tipo', 'EMAIL')
                ->where('con_estado', 1)
                ->whereRaw('LOWER(con_valor) = ?', [$correo]))
            ->limit(2)
            ->get();

        return $candidatas->count() === 1 ? $candidatas->first() : null;
    }

    /** Agrega un contacto si la persona todavía no lo tiene (no pisa los datos del mostrador). */
    public function completarContacto(Persona $persona, string $tipo, ?string $valor): void
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return;
        }

        $persona->contactos()->firstOrCreate(
            ['con_tipo' => $tipo, 'con_valor' => $valor],
            ['con_estado' => 1]
        );
    }
}
