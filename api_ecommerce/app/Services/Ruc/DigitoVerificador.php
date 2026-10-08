<?php

namespace App\Services\Ruc;

/**
 * Calculo del digito verificador del RUC paraguayo para personas fisicas
 * (RUC = cedula + un digito verificador, separados por un guion).
 *
 * Port directo de la funcion oficial de la DNIT "Pa_Calcular_Dv_11_A"
 * (provista por el usuario desde el documento "Digito Verificador.pdf"
 * de dnit.gov.py; la version en C atribuye la autoria a Luis Francou).
 * No es una version propia ni una reconstruccion: es el mismo algoritmo,
 * linea por linea, portado a PHP.
 *
 * Dos detalles del original que una version "simplificada" se pierde
 * facil, y que por eso se portan explicitamente:
 *
 *  1. Los caracteres que no son digito (0-9) NO se descartan: se
 *     reemplazan por su codigo ASCII (ej. una cedula vieja que termina
 *     en letra). Antes esta clase hacia un simple "sacar todo lo que no
 *     sea digito", que es distinto y da otro resultado para esos casos.
 *
 *  2. El peso de cada posicion arranca en 2 y sube de a 1, PERO se
 *     reinicia a 2 apenas supera basemax (11 por defecto). Para
 *     cedulas de hasta 10 digitos esto nunca se dispara -- el peso
 *     llega como maximo a 11 en la decima posicion -- asi que para
 *     cualquier cedula paraguaya real (6 a 9 digitos) el resultado
 *     coincide con "pesos sin tope"; el limite solo cambia algo para
 *     numeros de 11+ digitos, que no existen en este dominio. Se porto
 *     igual para ser fiel al original, no por necesidad practica.
 *
 * Verificado contra 8 RUCs reales (ver RucParaguayoTest): cada uno
 * recalculado a mano con este mismo algoritmo antes de confiar en el.
 */
class DigitoVerificador
{
    public static function digitoVerificador(string $numero, int $baseMax = 11): int
    {
        $numeroAl = '';

        foreach (str_split($numero) as $caracter) {
            $codigo = ord(strtoupper($caracter));

            // fuera del rango ASCII de '0' (48) a '9' (57): se reemplaza
            // por su codigo, no se descarta.
            $numeroAl .= ($codigo < 48 || $codigo > 57) ? (string) $codigo : chr($codigo);
        }

        $k = 2;
        $total = 0;

        for ($i = strlen($numeroAl) - 1; $i >= 0; $i--) {
            if ($k > $baseMax) {
                $k = 2;
            }

            $total += ((int) $numeroAl[$i]) * $k;
            $k++;
        }

        $resto = $total % 11;

        return $resto > 1 ? 11 - $resto : 0;
    }

    /**
     * true si "numero" (formato "1234567-8") es matematicamente el RUC
     * que correspondería a esa cedula. No confirma que el RUC este
     * activo en el SET/DNIT: solo que el digito verificador cierra.
     */
    public static function correspondeACedula(string $numero, string $cedula): bool
    {
        if (!preg_match('/^([0-9]{1,15})-([0-9])$/', trim($numero), $partes)) {
            return false;
        }

        [, $base, $digito] = $partes;

        return $base === preg_replace('/\D/', '', $cedula)
            && (int) $digito === self::digitoVerificador($cedula);
    }
}