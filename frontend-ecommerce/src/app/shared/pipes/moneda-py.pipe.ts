import { CurrencyPipe } from '@angular/common';
import { Pipe, PipeTransform } from '@angular/core';

/**
 * Wrapper sobre el CurrencyPipe nativo de Angular, fijo en Guaraníes (Gs.)
 * en vez de $. El Guaraní no usa decimales, por eso '1.0-0'.
 *
 * Uso (reemplaza a `| currency` en los templates):
 *   {{ producto.precio_final | monedaPy }}   ->  Gs. 234.000
 */
@Pipe({
  name: 'monedaPy',
  standalone: true,
})
export class MonedaPyPipe implements PipeTransform {
  private currencyPipe = new CurrencyPipe('es-PY');

  transform(valor: number | string | null | undefined): string | null {
    if (valor === null || valor === undefined || valor === '') {
      return null;
    }
    return this.currencyPipe.transform(valor, 'PYG', 'Gs. ', '1.0-0');
  }
}
