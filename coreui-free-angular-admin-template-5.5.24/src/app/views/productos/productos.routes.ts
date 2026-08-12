import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    data: { title: 'Productos' },
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./producto-list/producto-list.component').then((m) => m.ProductoListComponent),
        data: { title: 'Productos' },
      },
    ],
  },
];