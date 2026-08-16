import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import { AuthService } from '../../../auth/services/auth.service';

@Injectable({
  providedIn: 'root',
})
export class SliderService {
  isLoadingSubject = new BehaviorSubject<boolean>(false);
  isLoading$: Observable<boolean> = this.isLoadingSubject.asObservable();

  private apiUrl = `${URL_SERVICIOS}/admin/sliders`;

  constructor(private http: HttpClient, private authService: AuthService) {}

  private get headers(): HttpHeaders {
    const token = this.authService.getToken();
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }

  list(page: number = 1, search: string = ''): Observable<any> {
    this.isLoadingSubject.next(true);
    const url = `${this.apiUrl}?page=${page}&search=${search}`;
    return this.http
      .get(url, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  create(data: FormData): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http
      .post(this.apiUrl, data, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  show(id: number): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http
      .get(`${this.apiUrl}/${id}`, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  update(id: number, data: FormData): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http
      .post(`${this.apiUrl}/${id}`, data, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  delete(id: number): Observable<any> {
    this.isLoadingSubject.next(true);
    return this.http
      .delete(`${this.apiUrl}/${id}`, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }


  listProductos(search: string = ''): Observable<any> {
    const url = `${URL_SERVICIOS}/admin/sliders-productos?search=${search}`;
    return this.http.get(url, { headers: this.headers });
  }

  showProducto(id: number): Observable<any> {
    const url = `${URL_SERVICIOS}/admin/sliders-productos/${id}`;
    return this.http.get(url, { headers: this.headers });
  }
}

