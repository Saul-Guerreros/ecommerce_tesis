import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { URL_SERVICIOS } from '../../config/config';

export interface FaqGrupo {
  tema: string;
  etiqueta: string;
  preguntas: { pregunta: string; respuesta: string }[];
}

@Injectable({ providedIn: 'root' })
export class FaqPublicoService {
  constructor(private http: HttpClient) {}

  obtener(): Observable<FaqGrupo[]> {
    const url = `${URL_SERVICIOS}/tienda/faq`;
    return this.http.get<{ data: FaqGrupo[] }>(url).pipe(map((res) => res.data));
  }
}