import { Routes } from '@angular/router';

export const USUARIOS_ROUTES: Routes = [
  {
    path: '',
    children: [
      { path: '', redirectTo: 'list', pathMatch: 'full' },
      {
        path: 'list',
        loadComponent: () =>
          import('./list-usuario/list-usuario.component').then((m) => m.ListUsuarioComponent),
        data: { title: 'Usuarios' },
      },
    ],
  },
];
