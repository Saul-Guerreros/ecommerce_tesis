/**
 * Catálogo de ilustraciones SVG para el mega menú de categorías.
 * - Estilo de línea (viewBox 64x64), coherente con la plantilla.
 * - Usan currentColor: toman el color del texto del contenedor.
 * - IMPORTANTE: el admin debe usar exactamente las mismas claves.
 */

export const ICONO_POR_DEFECTO = 'general';

/** Valores viejos (íconos UIkit) guardados en BD → clave nueva. */
export const ALIAS_ICONOS: Record<string, string> = {
  desktop: 'computadora',
  tablet: 'notebook',
  server: 'componentes',
  world: 'redes',
  print: 'impresion',
  database: 'almacenamiento',
  cog: 'perifericos',
  bag: 'accesorios',
  gift: 'promociones',
  grid: 'general',
};

const ICONOS: Record<string, string> = {
  computadora:
    '<rect x="4" y="10" width="38" height="28" rx="2"/><rect x="8" y="14" width="30" height="20" rx="1"/><path d="M26 18l-7 7M30 20l-5 5"/><path d="M19 38l-1.5 7h11L27 38"/><path d="M14 45h18"/><rect x="46" y="8" width="14" height="40" rx="2"/><path d="M49.5 14h7M49.5 18h7"/><circle cx="53" cy="40" r="2.5"/><rect x="4" y="50" width="38" height="7" rx="1.5"/><path d="M8 53.5h30" stroke-dasharray="2 2"/><rect x="47" y="50" width="9" height="10" rx="4.5"/><path d="M51.5 50v3.5"/>',
  notebook:
    '<rect x="8" y="10" width="38" height="25" rx="2"/><rect x="12" y="14" width="30" height="17" rx="1"/><path d="M30 17l-6 6M33 19l-4 4"/><path d="M3 39h48l-3 5H6z"/><path d="M23 39h8"/><rect x="40" y="22" width="20" height="32" rx="3" fill="#fff"/><rect x="43" y="26" width="14" height="22" rx="1"/><circle cx="50" cy="51" r="1.2"/>',
  perifericos:
    '<rect x="8" y="6" width="48" height="31" rx="2"/><rect x="12" y="10" width="40" height="23" rx="1"/><path d="M36 14l-9 9M40 16l-6 6"/><path d="M29 37l-2 7h10l-2-7"/><path d="M22 44h20"/><rect x="4" y="49" width="40" height="9" rx="1.5"/><path d="M8 52.5h32M8 55.5h32" stroke-dasharray="2 2"/><rect x="49" y="47" width="10" height="13" rx="5"/><path d="M54 47v4"/>',
  componentes:
    '<rect x="16" y="16" width="32" height="32" rx="2"/><rect x="23" y="23" width="18" height="18" rx="1"/><circle cx="27" cy="27" r="1.3"/><path d="M22 16V9M29 16V9M35 16V9M42 16V9M22 48v7M29 48v7M35 48v7M42 48v7M16 22H9M16 29H9M16 35H9M16 42H9M48 22h7M48 29h7M48 35h7M48 42h7"/>',
  redes:
    '<rect x="6" y="36" width="52" height="15" rx="3"/><circle cx="14" cy="43.5" r="1.3"/><circle cx="20" cy="43.5" r="1.3"/><circle cx="26" cy="43.5" r="1.3"/><path d="M38 43.5h14"/><path d="M15 36l-4-20M49 36l4-20"/><path d="M23 22a13 13 0 0 1 18 0M27 26.5a7 7 0 0 1 10 0"/><circle cx="32" cy="31" r="1.4" fill="currentColor"/><path d="M13 51v4M51 51v4"/>',
  almacenamiento:
    '<rect x="6" y="10" width="34" height="46" rx="3"/><circle cx="23" cy="28" r="10"/><circle cx="23" cy="28" r="2.5"/><path d="M23 28l9 15"/><circle cx="10" cy="14" r="1"/><circle cx="36" cy="14" r="1"/><circle cx="10" cy="52" r="1"/><circle cx="36" cy="52" r="1"/><rect x="46" y="24" width="13" height="30" rx="2.5"/><rect x="48.5" y="15" width="8" height="9" rx="1"/><path d="M50.5 18.5h1M53.5 18.5h1"/><circle cx="52.5" cy="48" r="1.6"/>',
  impresion:
    '<rect x="17" y="6" width="30" height="16" rx="1"/><rect x="6" y="22" width="52" height="24" rx="3"/><circle cx="50" cy="29" r="1.5"/><path d="M12 29h10"/><rect x="17" y="38" width="30" height="20" rx="1" fill="#fff"/><path d="M22 45h20M22 50h13"/>',
  accesorios:
    '<path d="M17 25a15 15 0 0 1 30 0v29a3 3 0 0 1-3 3H20a3 3 0 0 1-3-3z"/><path d="M27 10.5V8a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2.5"/><path d="M17 31h30"/><path d="M23 40h18v10a2 2 0 0 1-2 2H25a2 2 0 0 1-2-2z"/><path d="M23 44h18"/><path d="M17 32h-4v18h4M47 32h4v18h-4"/>',
  audio:
    '<path d="M12 40v-8a20 20 0 0 1 40 0v8"/><rect x="7" y="38" width="11" height="18" rx="3"/><rect x="46" y="38" width="11" height="18" rx="3"/>',
  gaming:
    '<path d="M20 20h24a12 12 0 0 1 11.6 9l3.6 14a6 6 0 0 1-10.2 5.6L43 42H21l-6 6.6A6 6 0 0 1 4.8 43l3.6-14A12 12 0 0 1 20 20z"/><path d="M18 27v8M14 31h8"/><circle cx="45" cy="27.5" r="1.6"/><circle cx="49.5" cy="31" r="1.6"/><circle cx="45" cy="34.5" r="1.6"/><circle cx="40.5" cy="31" r="1.6"/>',
  celulares:
    '<rect x="10" y="6" width="26" height="48" rx="4"/><rect x="13" y="12" width="20" height="34" rx="1"/><path d="M20 9h6"/><circle cx="23" cy="50" r="1.4"/><path d="M27 18l-8 8M29 21l-5 5"/><path d="M45 22l1-8h8l1 8M45 46l1 8h8l1-8"/><rect x="41" y="22" width="18" height="24" rx="5" fill="#fff"/><path d="M50 29v5l3 2"/>',
  camara:
    '<rect x="5" y="18" width="54" height="36" rx="4"/><path d="M21 18l3-7h16l3 7"/><circle cx="32" cy="36" r="11"/><circle cx="32" cy="36" r="6"/><path d="M29 33a4 4 0 0 1 3-1.5"/><rect x="48" y="23" width="6" height="3" rx="1"/><path d="M10 18v-3h6v3"/>',
  promociones:
    '<path d="M8 10h22l26 26a3 3 0 0 1 0 4.2L40.2 56a3 3 0 0 1-4.2 0L8 30z"/><circle cx="18" cy="20" r="3.5"/><circle cx="29" cy="36" r="2.2"/><circle cx="39" cy="44" r="2.2"/><path d="M42 33L26 47"/>',
  general:
    '<path d="M32 6l24 12v28L32 58 8 46V18z"/><path d="M8 18l24 12 24-12M32 30v28"/><path d="M20 12l24 12v8"/>',
};

/** Devuelve el SVG completo para una clave (nueva o vieja). Nunca vacío. */
export function obtenerSvgCategoria(clave: string | null | undefined): string {
  const normalizada = ALIAS_ICONOS[clave ?? ''] ?? clave ?? ICONO_POR_DEFECTO;
  const contenido = ICONOS[normalizada] ?? ICONOS[ICONO_POR_DEFECTO];

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="100%" height="100%"
    fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
    stroke-linejoin="round" aria-hidden="true">${contenido}</svg>`;
}