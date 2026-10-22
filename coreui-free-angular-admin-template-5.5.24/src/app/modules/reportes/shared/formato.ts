/** Formatos de presentación del módulo (es-PY, Guaraníes sin decimales). */

const GS = new Intl.NumberFormat('es-PY', {
  style: 'currency',
  currency: 'PYG',
  maximumFractionDigits: 0,
});

const COMPACTO = new Intl.NumberFormat('es-PY', {
  notation: 'compact',
  maximumFractionDigits: 1,
});

const NUMERO = new Intl.NumberFormat('es-PY', { maximumFractionDigits: 2 });

export const formatoGs = (valor: number | null | undefined): string => GS.format(valor ?? 0);

/** Para ejes de gráficos: "Gs. 4,8 M". */
export const formatoGsCompacto = (valor: number | null | undefined): string =>
  `Gs. ${COMPACTO.format(valor ?? 0)}`;

export const formatoNumero = (valor: number | null | undefined): string => NUMERO.format(valor ?? 0);

/** "+24,4 %", "-3 %" o "—" si no hay período anterior para comparar. */
export const formatoVariacion = (valor: number | null | undefined): string => {
  if (valor === null || valor === undefined) {
    return '—';
  }
  return `${valor > 0 ? '+' : ''}${NUMERO.format(valor)} %`;
};