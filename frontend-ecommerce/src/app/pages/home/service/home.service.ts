import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { AuthService } from '../../auth/service/auth.service';
import { URL_SERVICIOS } from '../../../config/config';
import { Observable, throwError } from 'rxjs';
import { catchError, shareReplay } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class HomeService {

  constructor(
    public http: HttpClient,
    public authService: AuthService,
  ) { }

  home(){
    let URL = URL_SERVICIOS+"/ecommerce/home";
    return this.http.get(URL);
  }

    private menus$?: Observable<any>;

  menus(): Observable<any> {
    if (!this.menus$) {
      const URL = URL_SERVICIOS + '/tienda/categorias/menu';
      this.menus$ = this.http.get(URL).pipe(
        shareReplay(1),
        catchError((err) => {
          this.menus$ = undefined; // si falla, el próximo llamado reintenta
          return throwError(() => err);
        })
      );
    }
    return this.menus$;
  }

  showProduct(slug:string,code_discount:string){
    let URL = URL_SERVICIOS+"/ecommerce/product/"+slug+"?campaing_discount="+code_discount;
    return this.http.get(URL);
  }

  getConfigFilter() {
    let URL = URL_SERVICIOS+"/ecommerce/config-filter-advance";
    return this.http.get(URL);
  }

  filterAdvanceProduct(data:any){
    let URL = URL_SERVICIOS+"/ecommerce/filter-advance-product";
    return this.http.post(URL,data);
  }

  campaingDiscountLink(data:any){
    let URL = URL_SERVICIOS+"/ecommerce/campaing-discount-link";
    return this.http.post(URL,data);
  }
}
