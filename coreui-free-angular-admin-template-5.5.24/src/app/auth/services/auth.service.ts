import { Injectable, computed, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { Router } from '@angular/router';

import { URL_SERVICIOS } from '../../config/config';
import { User } from '../interfaces/user.model';
import { LoginResponse } from '../interfaces/login-response.model';

const TOKEN_KEY = 'access_token';
const USER_KEY = 'auth_user';

export interface MensajeResponse {
  message: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  private apiUrl = URL_SERVICIOS;

  /** Señal reactiva con el usuario actual (header, menús, etc.) */
  currentUser = signal<User | null>(this.getUserFromStorage());

  /** Permisos del usuario actual, derivados de currentUser */
  permisos = computed<string[]>(() => this.currentUser()?.permisos ?? []);

  constructor(private http: HttpClient, private router: Router) {}

  // ───────────────────────── Sesión ─────────────────────────

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
   * Paso 2: valida el código recibido por correo (sin cambiar aún la contraseña).
   */
  verifyResetCode(code: string): Observable<{ message: number }> {
    return this.http.post<{ message: number }>(`${this.apiUrl}/auth/verified_code`, { code });
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
          permisos: res.permisos ?? [],
          avatar_url: res.avatar_url ?? null,
        };
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        this.currentUser.set(user);
      })
    );
  }

  /** Actualiza datos del usuario en sesión (nombre, foto) sin volver a loguear. */
  actualizarUsuario(cambios: Partial<User>): void {
    const actual = this.currentUser();
    if (!actual) return;
    const user = { ...actual, ...cambios };
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this.currentUser.set(user);
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

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  private getUserFromStorage(): User | null {
    const raw = localStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as User;
    } catch {
      localStorage.removeItem(USER_KEY);
      return null;
    }
  }

  // ─────────────────────── Autorización ───────────────────────

  /** true si el usuario logueado posee el permiso indicado */
  tienePermiso(permiso: string): boolean {
    return this.permisos().includes(permiso);
  }

  /** true si posee AL MENOS UNO de los permisos (criterio del middleware backend) */
  tieneAlgunPermiso(...permisos: string[]): boolean {
    return permisos.some((p) => this.tienePermiso(p));
  }

  /** true si posee TODOS los permisos indicados */
  tieneTodosLosPermisos(...permisos: string[]): boolean {
    return permisos.every((p) => this.tienePermiso(p));
  }

  tieneRol(rol: string): boolean {
    return this.currentUser()?.rol === rol;
  }

  // ─────────────────── Recuperación de contraseña ───────────────────

  /** Solicita el envío del código de recuperación al correo indicado */
  forgotPassword(email: string): Observable<MensajeResponse> {
    return this.http.post<MensajeResponse>(`${this.apiUrl}/auth/forgot-password`, { email });
  }

  /** Establece la nueva contraseña usando el código recibido por correo */
  resetPassword(code: string, new_password: string): Observable<MensajeResponse> {
    return this.http.post<MensajeResponse>(`${this.apiUrl}/auth/reset-password`, {
      code,
      new_password
    });
  }

  // ─────────────────── Verificación de sesión (guard) ───────────────────

  /** Valida el token contra el backend y refresca el usuario en memoria/storage */
  verificarSesion(): Observable<User> {
    return this.http.post<any>(`${this.apiUrl}/auth/me`, {}).pipe(
      tap((res) => {
        const user: User = {
          full_name: res.full_name,
          email: res.email,
          rol: res.rol,
          permisos: res.permisos,
        };
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        this.currentUser.set(user);
      })
    );
  }
}
