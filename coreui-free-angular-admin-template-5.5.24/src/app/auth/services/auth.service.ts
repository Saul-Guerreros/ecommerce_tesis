import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { User } from '../interfaces/user.model';
import { LoginResponse } from '../interfaces/login-response.model';
import { environment } from '../../../environments/environment';
import { Router } from '@angular/router';

const TOKEN_KEY = 'access_token';
const USER_KEY = 'auth_user';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  private apiUrl = URL_SERVICIOS;

  // Señal reactiva con el usuario actual (útil para mostrar nombre en el header, etc.)
  currentUser = signal<User | null>(this.getUserFromStorage());

  constructor(private http: HttpClient, private router: Router) {}

  /**
   * Login del panel administrativo. En el backend solo lo pueden usar
   * usuarios con rol Administrador o Vendedor (ver AuthController::login).
   */
  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/auth/login`, { email, password }).pipe(
      tap((res) => this.guardarSesion(res))
    );
  }

  logout(): void {
    this.http.post(`${this.apiUrl}/auth/logout`, {}).subscribe({
      next: () => this.clearSession(),
      error: () => this.clearSession() // limpiamos igual aunque falle la petición
    });
  }

  /**
   * Paso 1 de "olvidé mi contraseña": pide que se envíe un código al email.
   */
  forgotPassword(email: string): Observable<{ message: number }> {
    return this.http.post<{ message: number }>(`${this.apiUrl}/auth/verified_email`, { email });
  }

  /**
   * Paso 2: valida el código recibido por correo (sin cambiar aún la contraseña).
   */
  verifyResetCode(code: string): Observable<{ message: number }> {
    return this.http.post<{ message: number }>(`${this.apiUrl}/auth/verified_code`, { code });
  }

  /**
   * Paso 3: aplica la nueva contraseña usando el mismo código validado antes.
   */
  resetPassword(code: string, newPassword: string): Observable<{ message: number }> {
    return this.http.post<{ message: number }>(`${this.apiUrl}/auth/new_password`, {
      code,
      new_password: newPassword
    });
  }

  /**
   * Refresca el usuario actual contra /auth/me (útil tras recargar la página
   * si se quiere validar que el token siga vigente y traer permisos frescos).
   */
  fetchCurrentUser(): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/auth/me`, {}).pipe(
      tap((res) => {
        const user: User = {
          full_name: res.full_name,
          email: res.email,
          rol: res.rol,
          permisos: res.permisos ?? []
        };
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        this.currentUser.set(user);
      })
    );
  }

  private guardarSesion(res: LoginResponse): void {
    localStorage.setItem(TOKEN_KEY, res.access_token);
    localStorage.setItem(USER_KEY, JSON.stringify(res.user));
    this.currentUser.set(res.user);
  }

  private clearSession(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.currentUser.set(null);
    this.router.navigate(['/login']);
  }

  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  private getUserFromStorage(): User | null {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  /**
   * El Administrador siempre pasa (igual que el middleware `permiso` del
   * backend). Para los demás roles se revisa el array `permisos` que trae
   * /auth/login y /auth/me.
   */
  tienePermiso(permiso: string): boolean {
    const user = this.currentUser();
    if (!user) return false;
    if (user.rol === 'Administrador') return true;
    return (user.permisos ?? []).includes(permiso);
  }

  tieneRol(...roles: string[]): boolean {
    const user = this.currentUser();
    return !!user && roles.includes(user.rol);
  }

}
