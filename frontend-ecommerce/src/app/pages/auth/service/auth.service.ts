import { HttpClient } from '@angular/common/http';
import { Injectable, afterNextRender, signal } from '@angular/core';
import { Router } from '@angular/router';
import { URL_SERVICIOS } from '../../../config/config';
import { catchError, map, of } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  token: string = '';
  user: any;

  // Signal de estado de sesión: Angular lo integra a la detección de cambios,
  // así el header se refresca solo (sin detectChanges() manual ni NG0100).
  readonly sesion = signal(false);

  constructor(
    public http: HttpClient,
    public router: Router
  ) {
    afterNextRender(() => {
      this.initAuth();
    });
  }

  initAuth() {
    if (localStorage.getItem("token")) {
      this.user = localStorage.getItem("user") ? JSON.parse(localStorage.getItem("user") ?? '') : null;
      this.token = localStorage.getItem("token") + "";
    }
    this.sesion.set(!!this.token);
  }

  login(email: string, password: string, remember: boolean = false) {
    const URL = URL_SERVICIOS + '/auth/login_ecommerce';
    return this.http.post(URL, { email, password, remember }).pipe(
      map((resp: any) => this.saveLocalStorage(resp)),
      catchError((err: any) => of(err))
    );
  }

  loginGoogle(credential: string, remember: boolean = false) {
    const URL = URL_SERVICIOS + '/auth/login_google';
    return this.http.post(URL, { credential, remember }).pipe(
      map((resp: any) => this.saveLocalStorage(resp)),
      catchError((err: any) => of(err))
    );
  }

  saveLocalStorage(resp: any) {
    if (resp && resp.access_token) {
      localStorage.setItem("token", resp.access_token);
      localStorage.setItem("user", JSON.stringify(resp.user));
      this.token = resp.access_token;
      this.user = resp.user;
      this.sesion.set(true);
      return true;
    }
    return false;
  }

  isLoggedIn(): boolean {
    return !!this.token;
  }

  register(data: any) {
    let URL = URL_SERVICIOS + "/auth/register";
    return this.http.post(URL, data);
  }

  verifiedAuth(data: any) {
    let URL = URL_SERVICIOS + "/auth/verified_auth";
    return this.http.post(URL, data);
  }

  verifiedMail(data: any) {
    let URL = URL_SERVICIOS + "/auth/verified_email";
    return this.http.post(URL, data);
  }

  verifiedCode(data: any) {
    let URL = URL_SERVICIOS + "/auth/verified_code";
    return this.http.post(URL, data);
  }

  verifiedNewPassword(data: any) {
    let URL = URL_SERVICIOS + "/auth/new_password";
    return this.http.post(URL, data);
  }

  logout(returnUrl?: string) {
    let URL = URL_SERVICIOS + "/auth/logout";
    this.http.post(URL, {}).subscribe({
      next: () => this.sesionExpirada(returnUrl),
      error: () => this.sesionExpirada(returnUrl)
    });
  }

  sesionExpirada(returnUrl?: string) {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    this.user = null;
    this.token = '';
    this.sesion.set(false);
    setTimeout(() => {
      this.router.navigateByUrl(returnUrl ?? "/login");
    }, 500);
  }

  // Refresca localStorage sin volver a golpear el backend (ej. tras editar
  // el perfil, para que el saludo del header quede al día).
  actualizarUsuarioLocal(cambios: Partial<any>): void {
    this.user = { ...this.user, ...cambios };
    localStorage.setItem('user', JSON.stringify(this.user));
  }
}