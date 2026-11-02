import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { PaginaInformativa } from '../models/pagina-informativa.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class PaginasInformativasService {
  private apiUrl = `${environment.apiUrl}/admin/paginas-informativas`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<PaginaInformativa[]> {
    return this.http.get<{ data: PaginaInformativa[] }>(this.apiUrl).pipe(map((res) => res.data));
  }

  getPorSlug(slug: string): Observable<PaginaInformativa> {
    return this.getAll().pipe(
      map((paginas) => {
        const pagina = paginas.find((p) => p.pgi_slug === slug);
        if (!pagina) throw new Error('Página no encontrada');
        return pagina;
      }),
    );
  }

  update(id: number, data: Partial<PaginaInformativa>): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}`, data);
  }
}