import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Largo mínimo contando solo el texto real, sin los espacios (ni saltos de línea) del
 * inicio y del final. El backend recorta esos espacios antes de validar (middleware
 * TrimStrings de Laravel), así que acá se valida igual: lo que el formulario da por
 * válido, el servidor también.
 *
 * Usa la misma clave de error que Validators.minLength ('minlength'), por lo que los
 * mensajes que ya muestran los templates siguen funcionando. Un campo con solo espacios
 * también queda inválido (Validators.required no lo detecta).
 */
export function minLengthSinEspacios(min: number): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const valor = control.value;
    if (valor === null || valor === undefined || valor === '') return null;   // lo valida required

    // Array.from cuenta caracteres reales (un emoji = 1), igual que mb_strlen de PHP.
    const largo = Array.from(String(valor).trim()).length;
    return largo < min ? { minlength: { requiredLength: min, actualLength: largo } } : null;
  };
}

/**
 * Reemplaza a Validators.required en campos de texto: un valor con solo espacios,
 * tabulaciones o saltos de línea cuenta como vacío. Usa la clave 'required', así que
 * los mensajes que ya muestran los templates siguen funcionando.
 */
export const requeridoSinEspacios: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
  const valor = control.value;
  if (valor === null || valor === undefined) return { required: true };
  return String(valor).trim().length === 0 ? { required: true } : null;
};