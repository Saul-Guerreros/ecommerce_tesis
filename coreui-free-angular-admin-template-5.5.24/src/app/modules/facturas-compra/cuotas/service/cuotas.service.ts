import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { CuotaCompra } from '../models/cuota-compra.model';
import { environment } from '../../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class CuotasService {
  private comprasUrl = `${environment.apiUrl}/admin/compras`;
  private cuotasUrl  = `${environment.apiUrl}/admin/cuotas-compra`;

  constructor(private http: HttpClient) {}

  getAll(comId: number): Observable<CuotaCompra[]> {
    return this.http.get<{ data: CuotaCompra[] }>(`${this.comprasUrl}/${comId}/cuotas`)
      .pipe(map((res) => res.data));
  }

}