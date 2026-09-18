import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Proveedor, ProveedorConfig, Contacto, TimbradoProveedor, TipoComprobanteOption } from '../models/proveedor.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ProveedoresService {
  private apiUrl = `${environment.apiUrl}/admin/proveedores`;
  private contactosUrl = `${environment.apiUrl}/admin/contactos-proveedor`;
  private timbradosUrl = `${environment.apiUrl}/admin/timbrados-proveedor`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; tipo?: number | null; ciudad?: number | null; estado?: string } = {}): Observable<Proveedor[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.tipo)   params = params.set('tipo', String(filtros.tipo));
    if (filtros.ciudad) params = params.set('ciudad', String(filtros.ciudad));
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: Proveedor[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<ProveedorConfig> {
    return this.http.get<ProveedorConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Proveedor> {
    return this.http.get<{ data: Proveedor }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  create(proveedor: Partial<Proveedor>): Observable<any> {
    return this.http.post(this.apiUrl, proveedor);
  }

  update(id: number, proveedor: Partial<Proveedor>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, proveedor);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }

  // --- Contactos (sub-recurso, se gestiona desde el propio form-proveedor) ---

  getContactos(proveedorId: number): Observable<Contacto[]> {
    const params = new HttpParams().set('proveedor', String(proveedorId));
    return this.http.get<{ data: Contacto[] }>(this.contactosUrl, { params }).pipe(map((res) => res.data));
  }

  createContacto(contacto: Partial<Contacto>): Observable<any> {
    return this.http.post(this.contactosUrl, contacto);
  }

  updateContacto(id: number, contacto: Partial<Contacto>): Observable<any> {
    return this.http.put(`${this.contactosUrl}/${id}`, contacto);
  }

  deleteContacto(id: number): Observable<any> {
    return this.http.delete(`${this.contactosUrl}/${id}`);
  }

  toggleEstadoContacto(id: number): Observable<any> {
    return this.http.patch(`${this.contactosUrl}/${id}/estado`, {});
  }

    // --- Timbrados (sub-recurso, se gestiona desde el propio form-proveedor) ---

  getConfigTimbrados(): Observable<TipoComprobanteOption[]> {
    return this.http
      .get<{ tipos_comprobante: TipoComprobanteOption[] }>(`${this.timbradosUrl}/config`)
      .pipe(map((res) => res.tipos_comprobante));
  }

  getTimbrados(proveedorId: number): Observable<TimbradoProveedor[]> {
    const params = new HttpParams().set('proveedor', String(proveedorId));
    return this.http.get<{ data: TimbradoProveedor[] }>(this.timbradosUrl, { params }).pipe(map((res) => res.data));
  }

  createTimbrado(timbrado: Partial<TimbradoProveedor>): Observable<any> {
    return this.http.post(this.timbradosUrl, timbrado);
  }

  updateTimbrado(id: number, timbrado: Partial<TimbradoProveedor>): Observable<any> {
    return this.http.put(`${this.timbradosUrl}/${id}`, timbrado);
  }

  deleteTimbrado(id: number): Observable<any> {
    return this.http.delete(`${this.timbradosUrl}/${id}`);
  }

  toggleEstadoTimbrado(id: number): Observable<any> {
    return this.http.patch(`${this.timbradosUrl}/${id}/estado`, {});
  }

}