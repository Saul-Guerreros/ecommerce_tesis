import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { NotaCredito, NotaCreditoConfig } from '../models/nota-credito.model';
import { environment } from '../../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class NotasCreditoService {
  private apiUrl = `${environment.apiUrl}/admin/compras`;

  constructor(private http: HttpClient) {}

  getConfig(comId: number): Observable<NotaCreditoConfig> {
    return this.http.get<NotaCreditoConfig>(`${this.apiUrl}/${comId}/notas-credito/config`);
  }

  getAll(comId: number): Observable<NotaCredito[]> {
    return this.http.get<{ data: NotaCredito[] }>(`${this.apiUrl}/${comId}/notas-credito`).pipe(map((res) => res.data));
  }

  create(comId: number, nota: Partial<NotaCredito>): Observable<any> {
    return this.http.post(`${this.apiUrl}/${comId}/notas-credito`, nota);
  }
}