import { Routes } from '@angular/router';

export const ATRIBUTOS_ROUTES: Routes = [
  {
    path: '',
    children: [
      { path: '', redirectTo: 'list', pathMatch: 'full' },
      {
        path: 'list',
        loadComponent: () =>
          import('./list-atributo/list-atributo.component').then((m) => m.ListAtributoComponent),
        data: { title: 'Atributos' },
      },
    ],
  },
];
