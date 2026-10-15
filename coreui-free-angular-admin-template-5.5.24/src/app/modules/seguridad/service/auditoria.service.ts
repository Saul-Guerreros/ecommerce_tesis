import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import {
  HistorialPasswordItem, IntentoSesionItem, ListaAuditoria, SesionItem, TokenItem,
} from '../models/auditoria.model';

type Filtros = Record<string, string | number | null | undefined>;

/**
 * Solo lectura: las 4 pantallas de Auditoría únicamente consultan (GET).
 * Authorization y Accept los agrega authInterceptor.
 */
@Injectable({ providedIn: 'root' })
export class AuditoriaService {
  private readonly base = `${environment.apiUrl}/admin/seguridad`;

  constructor(private http: HttpClient) {}

  /** Arma los query params omitiendo los filtros vacíos. */
  private params(filtros: Filtros, page: number): HttpParams {
    let params = new HttpParams().set('page', String(page));
    for (const [clave, valor] of Object.entries(filtros)) {
      if (valor !== null && valor !== undefined && valor !== '') {
        params = params.set(clave, String(valor));
      }
    }
    return params;
  }

  historialPassword(
    f: { search?: string; desde?: string; hasta?: string }, page = 1,
  ): Observable<ListaAuditoria<HistorialPasswordItem>> {
    return this.http.get<ListaAuditoria<HistorialPasswordItem>>(
      `${this.base}/historial-password`, { params: this.params(f, page) });
  }

  sesiones(
    f: { search?: string; estado?: string }, page = 1,
  ): Observable<ListaAuditoria<SesionItem, { activas_ahora: number }>> {
    return this.http.get<ListaAuditoria<SesionItem, { activas_ahora: number }>>(
      `${this.base}/sesiones`, { params: this.params(f, page) });
  }

  intentosSesion(
    f: { search?: string; resultado?: string; desde?: string; hasta?: string }, page = 1,
  ): Observable<ListaAuditoria<IntentoSesionItem, { fallidos: number }>> {
    return this.http.get<ListaAuditoria<IntentoSesionItem, { fallidos: number }>>(
      `${this.base}/intentos-sesion`, { params: this.params(f, page) });
  }

  tokens(
    f: { search?: string; tipo?: string; estado?: string }, page = 1,
  ): Observable<ListaAuditoria<TokenItem, { vigentes: number }>> {
    return this.http.get<ListaAuditoria<TokenItem, { vigentes: number }>>(
      `${this.base}/tokens`, { params: this.params(f, page) });
  }
}
