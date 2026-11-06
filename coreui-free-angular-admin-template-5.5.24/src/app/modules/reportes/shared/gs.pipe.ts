import { Pipe, PipeTransform } from '@angular/core';
import { formatoGs, formatoGsCompacto } from './formato';

/**
 * Moneda en Guaraníes.
 *   {{ total | gs }}        → Gs. 4.850.000
 *   {{ total | gs:true }}   → Gs. 4,9 M
 */
@Pipe({ name: 'gs', standalone: true })
export class GsPipe implements PipeTransform {
  transform(valor: number | null | undefined, compacto = false): string {
    return compacto ? formatoGsCompacto(valor) : formatoGs(valor);
  }
}