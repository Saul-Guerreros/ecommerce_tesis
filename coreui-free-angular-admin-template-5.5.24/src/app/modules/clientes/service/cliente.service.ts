/*
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { URL_SERVICIOS } from 'src/app/config/config';
import { AuthService } from '../../auth';
import {
  BusquedaPersona, ClienteConfig, ClientePayload, ListaClientes
} from '../models/cliente.model';

@Injectable({ providedIn: 'root' })
export class ClienteService {

  private base = `${URL_SERVICIOS}/admin/clientes`;

  isLoading$: Observable<boolean>;
  isLoadingSubject: BehaviorSubject<boolean>;

  constructor(
    private http: HttpClient,
    public authservice: AuthService,
  ) {
    this.isLoadingSubject = new BehaviorSubject<boolean>(false);
    this.isLoading$ = this.isLoadingSubject.asObservable();
  }

  private headers(): HttpHeaders {
    return new HttpHeaders({
      'Authorization': 'Bearer ' + this.authservice.token,
      'Accept': 'application/json'
    });
  }

  config(): Observable<ClienteConfig> {
    return this.http.get<ClienteConfig>(`${this.base}/config`, { headers: this.headers() });
  }

  buscarPersona(tipoDocumento: number, documento: string): Observable<BusquedaPersona> {
    const params = `?tipo_documento=${tipoDocumento}&documento=${encodeURIComponent(documento)}`;
    return this.http.get<BusquedaPersona>(
      `${this.base}/buscar-persona${params}`, { headers: this.headers() }
    );
  }

  list(page: number = 1, search: string = '', estado: string = ''): Observable<ListaClientes> {
    this.isLoadingSubject.next(true);
    const url = `${this.base}?page=${page}&search=${encodeURIComponent(search)}&estado=${estado}`;
    return this.http.get<ListaClientes>(url, { headers: this.headers() })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  store(data: ClientePayload): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http.post(this.base, data, { headers: this.headers() })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  update(id: number, data: ClientePayload): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http.put(`${this.base}/${id}`, data, { headers: this.headers() })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  cambiarEstado(id: number): Observable<any> {
    return this.http.patch(`${this.base}/${id}/estado`, {}, { headers: this.headers() });
  }

  destroy(id: number): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http.delete(`${this.base}/${id}`, { headers: this.headers() })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }
}
  */