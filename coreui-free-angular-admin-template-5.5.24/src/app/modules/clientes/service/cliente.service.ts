import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import {
  BusquedaPersona, Cliente, ClienteConfig, ClientePayload, ListaClientes,
} from '../models/cliente.model';

/**
 * Servicio del módulo Clientes (admin).
 * Los headers Authorization y Accept los agrega authInterceptor
 * (app.config.ts), por eso aquí no se arman a mano.
 */
@Injectable({ providedIn: 'root' })
export class ClienteService {
  private readonly base = `${URL_SERVICIOS}/admin/clientes`;

  private readonly isLoadingSubject = new BehaviorSubject<boolean>(false);
  readonly isLoading$: Observable<boolean> = this.isLoadingSubject.asObservable();

  constructor(private http: HttpClient) {}

  config(): Observable<ClienteConfig> {
    return this.http.get<ClienteConfig>(`${this.base}/config`);
  }

  buscarPersona(tipoDocumento: number, documento: string): Observable<BusquedaPersona> {
    return this.http.get<BusquedaPersona>(`${this.base}/buscar-persona`, {
      params: { tipo_documento: tipoDocumento, documento },
    });
  }

  list(page: number = 1, search: string = '', estado: string = ''): Observable<ListaClientes> {
    this.isLoadingSubject.next(true);
    return this.http
      .get<ListaClientes>(this.base, { params: { page, search, estado } })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  store(data: ClientePayload): Observable<{ data: Cliente; message?: string }> {
    this.isLoadingSubject.next(true);
    return this.http
      .post<{ data: Cliente; message?: string }>(this.base, data)
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  update(id: number, data: ClientePayload): Observable<{ data: Cliente; message?: string }> {
    this.isLoadingSubject.next(true);
    return this.http
      .put<{ data: Cliente; message?: string }>(`${this.base}/${id}`, data)
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  cambiarEstado(id: number): Observable<any> {
    return this.http.patch(`${this.base}/${id}/estado`, {});
  }

  destroy(id: number): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http
      .delete(`${this.base}/${id}`)
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }
}