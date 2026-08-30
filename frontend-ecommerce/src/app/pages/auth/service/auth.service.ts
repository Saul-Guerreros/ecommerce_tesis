import { HttpClient } from '@angular/common/http';
import { Injectable, afterNextRender } from '@angular/core';
import { Router } from '@angular/router';
import { URL_SERVICIOS } from '../../../config/config';
import { catchError, map, of } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

 token:string= '';
  user:any;
  constructor(
    public http: HttpClient,
    public router: Router
  ) {
    afterNextRender(()=>{
      this.initAuth();
   })
   
  }

  initAuth() {
   if (localStorage.getItem("token")) {
     this.user = localStorage.getItem("user") ? JSON.parse(localStorage.getItem("user") ?? ''): null;
     this.token = localStorage.getItem("token")+"";
   }
  }



  login(email: string , password:string){
    let URL= URL_SERVICIOS +"/auth/login_ecommerce";
    return this.http.post(URL,{email,password}).pipe(
      map((resp:any) => {
        console.log(resp);
        const result = this.saveLocalStorage(resp);
        return result;
      }),
      catchError((err:any) => {
        console.log(err);
        return of(err);
      }) 
    )
  }
  
  saveLocalStorage(resp: any) {
    if (resp && resp.access_token) {
      localStorage.setItem("token",resp.access_token);
      localStorage.setItem("user",JSON.stringify(resp.user));
      this.token = resp.access_token;
      this.user = resp.user;
      return true;
    }
    return false;
  }

  isLoggedIn(): boolean {
    return !!this.token;
  }
  register(data:any){
    let URL= URL_SERVICIOS +"/auth/register";
    return this.http.post(URL,data);
  }
  verifiedAuth(data:any){
    let URL= URL_SERVICIOS +"/auth/verified_auth";
    return this.http.post(URL,data);
  }

  verifiedMail(data:any){
    let URL= URL_SERVICIOS +"/auth/verified_email";
    return this.http.post(URL,data);
  }
   verifiedCode(data:any){
    let URL= URL_SERVICIOS +"/auth/verified_code";
    return this.http.post(URL,data);
  }
   verifiedNewPassword(data:any){
    let URL= URL_SERVICIOS +"/auth/new_password";
    return this.http.post(URL,data);
  }
  logout(){
    let URL = URL_SERVICIOS + "/auth/logout";
    this.http.post(URL, {}).subscribe({
      next: () => this.clearSession(),
      error: () => this.clearSession() // limpiamos igual aunque falle la peticion
    });
  }

  private clearSession(){
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    this.user = null;
    this.token = '';
    setTimeout(() => {
      this.router.navigateByUrl("/login");
    },500)
  }
}
