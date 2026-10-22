/**
 * Fechas en formato AAAA-MM-DD usando la hora LOCAL.
 * No se usa toISOString(): convierte a UTC y, en Paraguay (UTC-3/-4),
 * después de las 20/21 h devolvería el día siguiente.
 */
export function aIso(fecha: Date): string {
  const mm = String(fecha.getMonth() + 1).padStart(2, '0');
  const dd = String(fecha.getDate()).padStart(2, '0');
  return `${fecha.getFullYear()}-${mm}-${dd}`;
}

export type PresetFecha = 'hoy' | 'mes' | 'mes_anterior' | 'ultimos_30' | 'anio';

export const PRESETS_FECHA: { clave: PresetFecha; etiqueta: string }[] = [
  { clave: 'hoy', etiqueta: 'Hoy' },
  { clave: 'mes', etiqueta: 'Este mes' },
  { clave: 'mes_anterior', etiqueta: 'Mes anterior' },
  { clave: 'ultimos_30', etiqueta: 'Últimos 30 días' },
  { clave: 'anio', etiqueta: 'Este año' },
];

export function rangoPreset(clave: PresetFecha, hoy = new Date()): { desde: string; hasta: string } {
  const a = hoy.getFullYear();
  const m = hoy.getMonth();

  switch (clave) {
    case 'hoy':
      return { desde: aIso(hoy), hasta: aIso(hoy) };
    case 'mes_anterior':
      return { desde: aIso(new Date(a, m - 1, 1)), hasta: aIso(new Date(a, m, 0)) };
    case 'ultimos_30':
      return { desde: aIso(new Date(a, m, hoy.getDate() - 29)), hasta: aIso(hoy) };
    case 'anio':
      return { desde: aIso(new Date(a, 0, 1)), hasta: aIso(hoy) };
    default:
      return { desde: aIso(new Date(a, m, 1)), hasta: aIso(hoy) };
  }
}