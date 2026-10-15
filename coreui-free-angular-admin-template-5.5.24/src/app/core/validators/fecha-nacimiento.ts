import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Validación de fecha de nacimiento. Debe coincidir con la regla del
 * backend App\Rules\FechaNacimiento (desde 1940-01-01 hasta ayer).
 */
export const FECHA_NACIMIENTO_MIN = '1940-01-01';

/** Fecha local en formato AAAA-MM-DD (toISOString usa UTC y de noche daría "mañana"). */
function formatoIso(fecha: Date): string {
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');
  return `${fecha.getFullYear()}-${mes}-${dia}`;
}

export function hoyIso(): string {
  return formatoIso(new Date());
}

/** Último día permitido (ayer): para el atributo max del input. */
export function fechaNacimientoMax(): string {
  const ayer = new Date();
  ayer.setDate(ayer.getDate() - 1);
  return formatoIso(ayer);
}

/** Mensaje de error, o null si la fecha es válida o está vacía (el campo es opcional). */
export function errorFechaNacimiento(valor: string | null | undefined): string | null {
  if (!valor) return null;

  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return 'La fecha de nacimiento no es válida.';

  const [anio, mes, dia] = valor.split('-').map(Number);
  const fecha = new Date(anio, mes - 1, dia);
  if (fecha.getFullYear() !== anio || fecha.getMonth() !== mes - 1 || fecha.getDate() !== dia) {
    return 'La fecha de nacimiento no es válida.';
  }

  if (valor < FECHA_NACIMIENTO_MIN) return 'La fecha de nacimiento no puede ser anterior al 01/01/1940.';
  if (valor >= hoyIso()) return 'La fecha de nacimiento debe ser anterior a hoy.';
  return null;
}

/** Para formularios reactivos: error { fechaNacimiento: 'mensaje' }. */
export function fechaNacimientoValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const error = errorFechaNacimiento(control.value);
    return error ? { fechaNacimiento: error } : null;
  };
}