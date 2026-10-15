import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: 'historial-password',
    loadComponent: () => import('./historial-password/historial-password.component').then(m => m.HistorialPasswordComponent),
    data: { title: 'Historial de Contraseñas' },
  },
  {
    path: 'sesiones',
    loadComponent: () => import('./sesiones/sesiones.component').then(m => m.SesionesComponent),
    data: { title: 'Sesiones Activas' },
  },
  {
    path: 'intentos-sesion',
    loadComponent: () => import('./intentos-sesion/intentos-sesion.component').then(m => m.IntentosSesionComponent),
    data: { title: 'Intentos de Sesión' },
  },
  {
    path: 'tokens',
    loadComponent: () => import('./tokens/tokens.component').then(m => m.TokensComponent),
    data: { title: 'Tokens de Verificación' },
  },
  { path: '', pathMatch: 'full', redirectTo: 'sesiones' },
];
