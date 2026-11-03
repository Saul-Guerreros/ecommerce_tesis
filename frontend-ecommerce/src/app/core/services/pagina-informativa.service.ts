import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { URL_SERVICIOS } from '../../config/config';
import { DATOS_TIENDA } from '../../config/datos-tienda';

export interface PaginaInformativa {
  pgi_titulo: string;
  pgi_contenido: string;
}

@Injectable({ providedIn: 'root' })
export class PaginaInformativaService {
  constructor(private http: HttpClient) {}

  /** Trae la página por slug, la limpia y resuelve los tokens {{EMPRESA}}, {{RUC}}, etc. */
  obtener(slug: string): Observable<PaginaInformativa> {
    const url = `${URL_SERVICIOS}/tienda/paginas/${slug}`;
    return this.http.get<{ data: PaginaInformativa }>(url).pipe(
      map((res) => ({
        pgi_titulo: res.data.pgi_titulo,
        pgi_contenido: this.resolverTokens(this.limpiarHtml(res.data.pgi_contenido)),
      })),
    );
  }

  /** Quita lo que arrastra el pegado desde Word y rompe el salto de línea. */
  private limpiarHtml(html: string): string {
    return html
      .replaceAll('&nbsp;', ' ')
      .replaceAll('\u00A0', ' ')
      .replace(/white-space:\s*[^;"']+;?/gi, '');
  }

  private resolverTokens(html: string): string {
    const t = DATOS_TIENDA;
    return html
      .replaceAll('{{EMPRESA}}', t.nombreComercial)
      .replaceAll('{{RAZON_SOCIAL}}', t.razonSocial)
      .replaceAll('{{RUC}}', t.ruc)
      .replaceAll('{{DIRECCION}}', t.direccion)
      .replaceAll('{{EMAIL}}', t.email)
      .replaceAll('{{TELEFONO}}', t.telefono)
      .replaceAll('{{HORARIO}}', t.horario);
  }
}