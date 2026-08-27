import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { environment } from '../../../../environments/environment';

// AJUSTA esta constante si en tu proyecto ya tienes la URL base definida
// en otro lado (por ejemplo un archivo src/app/config/config.ts como en
// el modulo de sliders original, con URL_SERVICIOS).
const URL_SERVICIOS = environment.apiUrl;

@Injectable({
  providedIn: 'root',
})
export class CuponesService {
  isLoading$: Observable<boolean>;
  isLoadingSubject: BehaviorSubject<boolean>;

  constructor(private http: HttpClient) {
    this.isLoadingSubject = new BehaviorSubject<boolean>(false);
    this.isLoading$ = this.isLoadingSubject.asObservable();
  }

  // AJUSTA: reemplaza esto por como obtienes el token en tu proyecto
  // (localStorage, un AuthService propio con this.authservice.token, etc.)
  private get headers(): HttpHeaders {
    const token = localStorage.getItem('token') || '';
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }

  listCupones(page: number = 1, search: string = ''): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones?page=${page}&search=${search}`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  configCupones(): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones/config`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  createCupones(data: any): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones`;
    return this.http.post(URL, data, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  showCupone(cupone_id: string | number): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones/${cupone_id}`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  updateCupones(cupone_id: string | number, data: any): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones/${cupone_id}`;
    return this.http.put(URL, data, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  deleteCupone(cupone_id: string | number): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones/${cupone_id}`;
    return this.http.delete(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }
}
