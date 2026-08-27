import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { environment } from '../../../../environments/environment';

// AJUSTA si tu URL base o tu manejo de token es distinto
// (ver el mismo comentario en cupones.service.ts).
const URL_SERVICIOS = environment.apiUrl;

@Injectable({
  providedIn: 'root',
})
export class DiscountsService {
  isLoading$: Observable<boolean>;
  isLoadingSubject: BehaviorSubject<boolean>;

  constructor(private http: HttpClient) {
    this.isLoadingSubject = new BehaviorSubject<boolean>(false);
    this.isLoading$ = this.isLoadingSubject.asObservable();
  }

  private get headers(): HttpHeaders {
    const token = localStorage.getItem('token') || '';
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }

  listDiscounts(page: number = 1, search: string = ''): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/discounts?page=${page}&search=${search}`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  // Reutiliza el mismo endpoint de configuracion que el modulo de cupones
  // (productos/categorias/marcas disponibles para armar la campaña).
  configDiscounts(): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/cupones/config`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  createDiscounts(data: any): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/discounts`;
    return this.http.post(URL, data, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  showDiscount(discount_id: string | number): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/discounts/${discount_id}`;
    return this.http.get(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  updateDiscounts(discount_id: string | number, data: any): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/discounts/${discount_id}`;
    return this.http.put(URL, data, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }

  deleteDiscount(discount_id: string | number): Observable<any> {
    this.isLoadingSubject.next(true);
    const URL = `${URL_SERVICIOS}/admin/discounts/${discount_id}`;
    return this.http.delete(URL, { headers: this.headers }).pipe(
      finalize(() => this.isLoadingSubject.next(false))
    );
  }
}
