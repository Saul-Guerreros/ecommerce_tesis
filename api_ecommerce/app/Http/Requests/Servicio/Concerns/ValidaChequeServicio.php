<?php

namespace App\Http\Requests\Servicio\Concerns;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Reglas del cheque compartidas por los requests de pago del servicio
 * (saldo web, anticipo web y mostrador). Mismas que PagoPedidoRequest.
 * Los campos van 'nullable' en rules(): la obligatoriedad depende del
 * método de cada pago y se resuelve en verificarCheque(), campo por campo.
 */
trait ValidaChequeServicio
{
    /** @param string $prefijo  'cheque.' o 'pagos.*.cheque.' */
    protected function reglasCheque(string $prefijo): array
    {
        return [
            rtrim($prefijo, '.')    => ['nullable', 'array'],
            "{$prefijo}tipo"        => ['nullable', Rule::in($this->tiposChequeValidos())],
            "{$prefijo}banco"       => ['nullable', 'string', 'max:80'],
            "{$prefijo}nrodoc"      => ['nullable', 'string', 'max:20'],
            "{$prefijo}nombre"      => ['nullable', 'string', 'max:150'],
            "{$prefijo}nro"         => ['nullable', 'string', 'max:20'],
            "{$prefijo}fecha_cobro" => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    protected function verificarCheque(Validator $v, array $cheque, string $clave, string $sufijo = ''): void
    {
        $campos = [
            'tipo'   => "Elegí el tipo de cheque{$sufijo}.",
            'banco'  => "Ingresá el nombre del banco{$sufijo}.",
            'nrodoc' => "Ingresá la CI del titular del cheque{$sufijo}.",
            'nombre' => "Ingresá el nombre del titular del cheque{$sufijo}.",
            'nro'    => "Ingresá el número del cheque{$sufijo}.",
        ];

        foreach ($campos as $campo => $mensaje) {
            if (trim((string) ($cheque[$campo] ?? '')) === '') {
                $v->errors()->add("{$clave}.{$campo}", $mensaje);
            }
        }

        if (($cheque['tipo'] ?? null) === PagoCheque::TIPO_DIFERIDO
            && trim((string) ($cheque['fecha_cobro'] ?? '')) === '') {
            $v->errors()->add("{$clave}.fecha_cobro", "Ingresá la fecha de cobro del cheque diferido{$sufijo}.");
        }
    }

    protected function tiposChequeValidos(): array
    {
        return ConfiguracionTienda::actual()->tiposChequeHabilitados();
    }

    protected function chequeHabilitado(): bool
    {
        return $this->tiposChequeValidos() !== [];
    }

    protected function esCodigoCheque(?string $codigo): bool
    {
        return $codigo === MetodoPago::CHEQUE;
    }

    protected function mensajesCheque(string $prefijo): array
    {
        return [
            "{$prefijo}tipo.in"                    => 'El tipo de cheque seleccionado no está habilitado.',
            "{$prefijo}banco.max"                  => 'El nombre del banco no puede superar los 80 caracteres.',
            "{$prefijo}nrodoc.max"                 => 'La CI del titular no puede superar los 20 caracteres.',
            "{$prefijo}nombre.max"                 => 'El nombre del titular no puede superar los 150 caracteres.',
            "{$prefijo}nro.max"                    => 'El número del cheque no puede superar los 20 caracteres.',
            "{$prefijo}fecha_cobro.date"           => 'La fecha de cobro no es válida.',
            "{$prefijo}fecha_cobro.after_or_equal" => 'La fecha de cobro no puede ser anterior a hoy.',
        ];
    }
}