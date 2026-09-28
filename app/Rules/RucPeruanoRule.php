<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RucPeruanoRule implements ValidationRule
{
    /**
     * Permite validar RUC de 11 dígitos con algoritmo SUNAT Módulo 11,
     * o DNI de 8 dígitos numéricos.
     */
    public function __construct(
        protected bool $permitirDni = true
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $doc = trim((string) $value);

        if (!ctype_digit($doc)) {
            $fail('El documento debe contener únicamente dígitos numéricos.');
            return;
        }

        $length = strlen($doc);

        // Si es DNI (8 dígitos)
        if ($length === 8) {
            if (!$this->permitirDni) {
                $fail('El documento debe ser un RUC válido de 11 dígitos.');
            }
            return; // DNI de 8 dígitos válido
        }

        // Si es RUC (11 dígitos)
        if ($length === 11) {
            // Permitir comodín para clientes o proveedores sin RUC
            if ($doc === '10000000000' || $doc === '00000000000') {
                return;
            }

            $prefijo = substr($doc, 0, 2);
            $prefijosValidos = ['10', '15', '17', '20'];

            if (!in_array($prefijo, $prefijosValidos, true)) {
                $fail('El RUC debe comenzar con 10, 15, 17 o 20.');
                return;
            }

            // Algoritmo Módulo 11 de SUNAT
            $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            $suma = 0;

            for ($i = 0; $i < 10; $i++) {
                $suma += ((int) $doc[$i]) * $factores[$i];
            }

            $resto = $suma % 11;
            $verificadorCalculado = 11 - $resto;

            if ($verificadorCalculado === 10) {
                $verificadorCalculado = 0;
            } elseif ($verificadorCalculado === 11) {
                $verificadorCalculado = 1;
            }

            $digitoVerificadorReal = (int) $doc[10];

            if ($digitoVerificadorReal !== $verificadorCalculado) {
                $fail('El número de RUC ingresado no es válido según el algoritmo de verificación de SUNAT.');
            }
            return;
        }

        $fail('El documento debe ser un RUC de 11 dígitos o un DNI de 8 dígitos.');
    }
}
