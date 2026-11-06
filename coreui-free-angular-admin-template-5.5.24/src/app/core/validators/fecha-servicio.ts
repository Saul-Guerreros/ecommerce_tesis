import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Fechas de ingreso del servicio técnico (llevar / retirar el equipo).
 * El local no atiende los domingos. Debe coincidir con la regla del
 * backend App\Rules\FechaSinDomingo.
 */

/** Fecha local en formato AAAA-MM-DD (toISOString usa UTC y de noche daría "mañana"). */
function formatoIso(fecha: Date): string {
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');
  return `${fecha.getFullYear()}-${mes}-${dia}`;
}

/**
 * Convierte 'AAAA-MM-DD' a Date en hora LOCAL. No usar new Date('2026-10-25'):
 * se interpreta en UTC y en Paraguay (UTC-3) caería el día anterior.
 */
function aFechaLocal(valor: string): Date | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return null;
  const [anio, mes, dia] = valor.split('-').map(Number);
  const fecha = new Date(anio, mes - 1, dia);
  const real = fecha.getFullYear() === anio && fecha.getMonth() === mes - 1 && fecha.getDate() === dia;
  return real ? fecha : null;
}

export function hoyIso(): string {
  return formatoIso(new Date());
}

/** true si 'AAAA-MM-DD' cae domingo (vacío o inválido → false; eso lo valida required). */
export function esDomingo(valor: string | null | undefined): boolean {
  const fecha = valor ? aFechaLocal(valor) : null;
  return fecha !== null && fecha.getDay() === 0;
}

/** Hoy (o la fecha dada) si no es domingo; si es domingo, el lunes siguiente. */
export function proximoDiaHabilIso(desde?: string): string {
  const fecha = (desde ? aFechaLocal(desde) : null) ?? new Date();
  if (fecha.getDay() === 0) fecha.setDate(fecha.getDate() + 1);
  return formatoIso(fecha);
}

/** Validador de formulario: marca el error { domingo: true }. */
export const sinDomingoValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null =>
  esDomingo(control.value) ? { domingo: true } : null;