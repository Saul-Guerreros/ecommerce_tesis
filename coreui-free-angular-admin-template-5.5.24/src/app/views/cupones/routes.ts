import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    data: {
      title: 'Cupones',
    },
    children: [
      {
        path: '',
        redirectTo: 'list',
        pathMatch: 'full',
      },
      {
        path: 'list',
        loadComponent: () =>
          import('./list-cupones/list-cupones.component').then((m) => m.ListCuponesComponent),
        data: { title: 'Listado de Cupones' },
      },
      {
        path: 'register',
        loadComponent: () =>
          import('./create-cupones/create-cupones.component').then((m) => m.CreateCuponesComponent),
        data: { title: 'Nuevo Cupon' },
      },
      {
        path: 'list/edit/:id',
        loadComponent: () =>
          import('./edit-cupones/edit-cupones.component').then((m) => m.EditCuponesComponent),
        data: { title: 'Editar Cupon' },
      },
    ],
  },
];
